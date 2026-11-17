# Data Import & Export Catalogue

**System:** Wollo University Administrative and Document Management System (WU-DMS)

This document is the authoritative registry of the dataset operations the system
performs. The registry itself lives in the code, in
`server/config/data_transfer.php`, and this document describes it.

There is **no generic data management facility**. There is no arbitrary table
access, no unrestricted SQL, no database dump and no generic model
serialization. An operation that is not declared in the catalogue cannot be
performed by any route.

---

## 1. Supported operations

| Dataset | Direction | Format | Capability | Scope | Max records |
|---|---|---|---|---|---|
| `organization_units` | import | CSV | `organization.manage` | active organizational unit | 2 000 |
| `organization_memberships` | import | CSV | `organization.manage` | active organizational unit | 2 000 |
| `document_catalog` | export | CSV | `documents.view` | permitted units | 10 000 |
| `correspondence_registry` | export | CSV | `correspondence.view` | permitted units | 10 000 |
| `organization_directory` | export | CSV | `organization.view` | permitted units | 10 000 |
| `audit_events` | export | CSV | `audit.view` | global | 10 000 |

Two capabilities govern the module:

| Capability | Grants |
|---|---|
| `data.import` | Receiving, inspecting, executing and discarding an import, and viewing the import history |
| `data.export` | Generating an export, retrieving the generated file, and viewing the export history |

The capability that governs the **content** of a dataset is separate from the
capability that governs the **operation**. A caller must hold `data.export`
*and* the dataset's capability to export that dataset.

---

## 2. Deliberately excluded datasets

The following were considered and are **not** supported. The reason is given so
the omission is a decision rather than an oversight.

| Excluded | Reason |
|---|---|
| Document records and their binaries | An import would bypass the document registration workflow, the reference number sequence and the version history. A repository extraction would disclose file objects. |
| Correspondence records | An import would bypass the registration, routing and acknowledgment workflow. |
| Staff records and HR dossiers | Restricted personal data with no operational need for bulk transfer. |
| Permissions, roles, authorization scopes and user grants | A bulk grant operation is a privilege escalation vector and must remain a deliberate, individually audited administrative action. |
| Sessions, personal access tokens, credentials | Security material. |
| Audit detail payloads | The free form `details_json` may carry operational content that a ledger export must not disclose. Only the structured columns are exported. |
| Retention policies and reference sequences | System internals. |
| Every other table | No operational requirement. |

---

## 3. Formats

**CSV only.** The spreadsheet library that the project once carried was
removed in an earlier phase because it was unused, and re-introducing it for
one screen would add a dependency for no functional gain. The interface no
longer advertises XLSX.

The generated file is UTF-8 with a byte order mark, so a spreadsheet
application opens Amharic text correctly.

### 3.1 Formula injection

A cell whose content begins with `=`, `+`, `-`, `@`, a tab or a carriage return
is prefixed with an apostrophe, so a spreadsheet application always reads it as
text. This is applied to the header and to every value.

---

## 4. Import lifecycle

```
Upload -> Inspect -> Validate -> Preview -> Confirm -> Import -> Results
```

| Stage | What happens | Business records created |
|---|---|---|
| Upload | The file is validated (extension, MIME type, size) and stored on the private disk under a server generated name. | No |
| Inspect | The header row is validated, the columns are mapped to the catalogue, and every row is validated. | No |
| Preview | The totals, the row level failures and the warnings are returned. | No |
| Confirm | The operator explicitly confirms. | No |
| Import | The authorization and the business rules are re-evaluated, and the valid rows are created inside a single transaction. | Yes |
| Results | The counts and the outcome are recorded. | — |

### 4.1 File handling

| Property | Rule |
|---|---|
| Extension | `.csv` only |
| MIME type | `text/csv`, `text/plain`, `application/csv`, `application/vnd.ms-excel`, `text/x-csv` |
| Size limit | `data_imports.max_file_size_mb`, a runtime setting, default 20 MB. It is **not** the document upload limit, because a dataset file is a structured file rather than a repository record. |
| Storage | The private disk, under `imports/{operation id}/{uuid}.csv` |
| File name | The name the client supplied is recorded for the operator and is **never** used to build a path. |
| Path traversal | Impossible: the stored path is built from the operation identifier and a generated UUID. |
| Cleanup | The file is removed when the operation is discarded. |

### 4.2 Column mapping

The columns are declared by the catalogue. A column that is not part of the
catalogue is reported as a warning, never silently discarded:

> Columns that are not part of the catalogue were ignored: `foo, bar`.

A file that does not carry the required headers is refused before any row is
read.

### 4.3 Import validation rules

**`organization_units`**

| Column | Required | Type | Rule |
|---|---|---|---|
| `code` | yes | string | at most 64 characters; must not already exist |
| `name` | yes | string | at most 200 characters |
| `unit_type_code` | yes | string | must be a registered, active organizational unit type |
| `parent_code` | no | string | must exist and must be the active unit or a descendant of it |
| `is_active` | no | boolean | defaults to true |

**`organization_memberships`**

| Column | Required | Type | Rule |
|---|---|---|---|
| `user_email` | yes | string | must match an active institutional account |
| `position_title` | no | string | at most 160 characters |
| `starts_at` | no | date | a valid date |
| `ends_at` | no | date | must not precede the start date |

A membership that already exists for the account in the active unit is
reported as a duplicate.

### 4.4 Duplicate handling

**Reject.** A row whose key already exists is reported as a duplicate and is
skipped. The operation never overwrites an existing record. There is no update
mode: a bulk update is not authorised by any business requirement, and a
silent overwrite would destroy the record history.

### 4.5 Transaction and outcome

The valid rows are created inside **one** transaction. The outcome is therefore
honest and is one of:

| Status | Meaning |
|---|---|
| `completed` | Every valid record was created |
| `completed_with_errors` | The valid records were created and the failed rows are listed in the error report |
| `failed` | The transaction was rolled back. **No** record was created. |
| `discarded` | The operation was discarded before execution |

The operation is never reported as completed before its records are committed.

### 4.6 Partial failure

A row that fails validation at execution time is skipped and recorded. A row
that fails during the write rolls the whole transaction back, and the operation
is reported as `failed` with zero imported records. There is no state in which
the interface claims a partial success that did not happen.

---

## 5. Export authorization

An export is bounded by the caller's own access, in two independent layers.

1. **The candidate set.** `User::getPermittedUnitIds()` returns the units in
   which the caller holds the dataset's capability. A `global` grant returns
   `null`, meaning unrestricted. A filtered query is intersected with that set.
2. **The record policy.** Every candidate record is then evaluated with the
   record level policy (`Document::allowsUser()`), which applies the
   confidentiality clearance, the discretionary document grants and the
   creator and custodian rule.

The active organizational unit is a **presentation context**, never a
substitute for either layer. A filter narrows the result and can never broaden
it. A caller cannot widen their own scope by manipulating the request.

### 5.1 Field allowlists

Only the columns declared by the catalogue are written. No model is
serialized. The following are never exported: credentials, tokens, session
identifiers, audit detail payloads, internal identifiers and confidential
supporting fields.

| Dataset | Exported fields |
|---|---|
| `document_catalog` | reference_number, title, summary, category, confidentiality, owner_unit, document_date, registered_at, lifecycle |
| `correspondence_registry` | reference_number, purpose_code, type, priority, external_reference, correspondence_date, receiving_unit, received_at, requested_due_at |
| `organization_directory` | unit_code, unit_name, unit_type, parent_unit_code, member_name, member_email, member_position |
| `audit_events` | occurred_at, action, target_type, target_id, actor_name, ip_address |

### 5.2 Filters

| Dataset | Filters |
|---|---|
| `document_catalog` | lifecycle, category_code, confidentiality_code, date_from, date_to |
| `correspondence_registry` | purpose_code, type_code, date_from, date_to |
| `organization_directory` | unit_type_code |
| `audit_events` | action, date_from, date_to |

A filter that is not declared by the dataset is discarded.

---

## 6. File lifecycle and retention

### 6.1 Export artefacts

| Property | Rule |
|---|---|
| Storage | The private disk, under `exports/{operation id}/{uuid}.csv` |
| Retrieval | `GET /api/v1/data/exports/{id}/download` |
| Authorization at retrieval | The dataset capability is **re-evaluated at the moment of the download**, so a revoked capability stops the retrieval immediately |
| Expiry | The file is retrievable for 24 hours, after which the endpoint reports that it is no longer available |
| Auditing | The download is recorded |

### 6.2 Temporary import files

An uploaded dataset file is **temporary only while the operation that owns it
is still waiting to be applied**. The cleanup is implemented by
`php artisan dms:cleanup-import-uploads` and is registered to run daily at
02:30, which requires the deployment to run the Laravel scheduler.

| Operation state | The file |
|---|---|
| `uploaded`, `validated` | Removed once the retention window has passed |
| `importing` | Never touched |
| `completed`, `completed_with_errors`, `failed` | Never touched: the source file is the only record of what was submitted |
| `discarded` | The stale reference is cleared; the operation history is preserved |

The retention window is the runtime setting
`data_imports.temporary_file_retention_hours`, default 168 hours, overridable
per run with `--hours`. The command supports `--dry-run`, is idempotent, and
never removes a stored path outside the `imports/` area of the private disk.

The complete operational description, including the scheduler requirement, is
in [`docs/operations/OPERATIONS.md`](../operations/OPERATIONS.md).

---

## 7. Audit trail

The existing `AuditService` and the `audit_events` table are used. No second
audit framework was introduced.

| Event | Recorded information |
|---|---|
| `data.import.inspected` | operation id, dataset, scope, original file name, format, total, valid, invalid and duplicate counts, warnings |
| `data.import.executed` | operation id, dataset, scope, total, imported and failed counts |
| `data.import.failed` | operation id, dataset, scope, the reason |
| `data.import.discarded` | operation id, dataset |
| `data.export.generated` | operation id, dataset, scope, the applied filters, the format, the record count, the expiry |
| `data.export.failed` | operation id, dataset, scope, the reason |
| `data.export.downloaded` | operation id, dataset, the record count |

An event is written only when the corresponding operation actually happened. A
failed update writes no completion event, and the audit event is written inside
the same transaction as the change, so a rolled back import leaves no misleading
record. No credential, token or document content is recorded.

---

## 8. Known limitations

| Limitation | Consequence |
|---|---|
| CSV only | A spreadsheet user must export to CSV before uploading. This was chosen over re-introducing a spreadsheet dependency for one screen. |
| Imports are limited to organizational reference data | Business records with a registration workflow cannot be created in bulk. This is deliberate: a bulk write would bypass the workflow, the reference sequence and the record history. |
| Exports are synchronous | An export of more than 10 000 records is refused rather than queued. The deployment does not guarantee a queue worker, and a simulated progress indicator would be dishonest. |
| The error report is generated on demand | A report is not produced for an operation that was never inspected. |
| Temporary files are not swept automatically | An inspected operation that is never executed or discarded keeps its uploaded file until the operator discards it. A scheduled cleanup command is the natural follow-up. |
| The organization directory lists member email addresses | It requires `organization.view` and is bounded by the permitted units. |

---

## 9. Reproducing the verification

```bash
cd server
php artisan migrate
php artisan test --filter=DataTransferTest
php artisan test --filter=NavigationPermissionContractTest
php artisan test

cd ../client
npx vue-tsc -b --noEmit
npx vitest run tests/dataImportsExports.spec.ts
npx vitest run
npx vite build
```
