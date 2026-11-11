# Build Status

**System:** Wollo University Administrative and Document Management System (WU-DMS)
**Verification run:** 2026-11-10
**Baseline commit:** single consolidated root commit on `main`

---

## 1. Verification Scorecard

| Check | Command | Result | Evidence |
|---|---|---|---|
| Backend test suite | `php artisan test` | **PASS** | 363 tests, 363 passed, 2237 assertions, 148.8 s |
| Frontend test suite | `npx vitest run` | **PASS** | 25 files, 152 tests, 152 passed, 5.0 s |
| Frontend type check | `npx vue-tsc -b --noEmit` | **PASS** | exit code 0, no diagnostics |
| Production build | `npx vite build` | **PASS** | exit code 0, `✓ built in 772ms` |
| Dependency advisories | `composer audit` | **PASS** | "No security vulnerability advisories found." |
| Manifest validity | `composer validate --no-check-publish` | **PASS** | "./composer.json is valid" |
| Lockfile synchronisation | `npm ci --dry-run` | **PASS** | "up to date" |

No check was skipped, stubbed or reported from a cached result. Every figure
above is the output of a command executed in this environment against the
working tree as it now stands.

**Database used for verification:** MySQL 8.4 (`wollo_dms_test`), the project's
real test database configuration. SQLite was not substituted at any point.

---

## 2. Phase 2 — Active Organizational Unit Context

### 2.1 Status

**PASS — verified and working.** Open Item #3 from the previous cycle is
closed: the capability set published to the client is now calculated for an
active organizational context instead of being the union of every scoped
grant.

### 2.2 What was implemented

| Layer | Change |
|---|---|
| Server | `App\Services\Authorization\OrganizationalContextService` computes the available units, resolves the active unit and publishes the context. |
| Server | `POST /api/v1/auth/active-unit` switches the context; the unit is validated against the units the caller may select. |
| Server | `GET /api/v1/auth/me` publishes `active_unit`, `active_unit_id`, `available_units`, `unit_selection_required` and the three capability sets. |
| Server | `GET /api/v1/navigation` filters the catalog with the **active context** capability set. |
| Server | `User::getCapabilitySets()` resolves the active, global and all-scope capability sets in a single pass over the grants. |
| Client | `useAuthStore` publishes `availableUnits`, `activeUnit`, `activeUnitId`, `unitSelectionRequired`, `isSwitchingUnit` and `switchUnit()`. |
| Client | The sidebar renders an accessible context switcher and an explicit notice when a selection is required. |
| Client | The router guard awaits an in-flight switch so that a navigation never evaluates a stale capability set. |

### 2.3 Authorization test matrix — actual results

All results below are from `php artisan test --filter=ActiveOrganizationalUnitTest`
(16 tests, 106 assertions, all passing) and the full suite (308 tests).

| Scenario | Expected behaviour | Result |
|---|---|---|
| Global permission | Available in every unit context | PASS |
| Role-based unit grant | Available only in the applicable scope | PASS |
| Direct user unit grant | Available only in the applicable scope | PASS |
| College subtree grant | Applies to the college and valid descendants | PASS |
| Department grant | Does not grant unrelated department access | PASS |
| Sibling department | No inherited access without a valid grant | PASS |
| No active unit | No union of scoped permissions (global only) | PASS |
| Unauthorized unit selection | Rejected by the server (403) | PASS |
| Unit switch | Recalculates the effective capabilities | PASS |
| Revoked permission | No longer advertised | PASS |
| Direct API request | Resource-level authorization still enforced | PASS |
| Cross-unit record read | 403 | PASS |
| Cross-unit record update | 403, record unchanged | PASS |
| Cross-unit record deletion | No deletion endpoint is exposed (405) | PASS |
| Unauthorized creation under another unit | 403 | PASS |
| Descendant resolution | Subtree anchor covers its descendants | PASS |
| Global versus scoped precedence | Both respected; global applies unscoped | PASS |
| Inactive unit | Cannot be selected (403) | PASS |
| Navigation before / during / after switching | Follows the active context | PASS |
| Session expiration (401) | Client session cleared | PASS (frontend suite) |
| Forbidden operation (403) | Session retained | PASS (frontend suite) |

### 2.4 Query cost

The context resolution was measured with the query log enabled against the
staging database for `demo.dean.informatics@wu.edu.et` (1 role grant, 4 direct
grants, 4 available units):

| State | Queries for one context build |
|---|---|
| Before the optimisation (per-grant subtree walk, repeated grant loads) | **116** |
| After: batched subtree expansion, single grant load per context | **19** |

The reduction is a genuine fix, not a cache: the grants are **never** cached
on the model, so a grant that is added or revoked is always observed by the
next decision. An earlier iteration memoised the grants and was **rejected**
because it made a revoked capability remain advertised — the regression was
caught by `test_revoked_permission_is_no_longer_advertised`.

### 2.5 Test inventory added in this phase

| Suite | Tests | Coverage |
|---|---|---|
| `ActiveOrganizationalUnitTest` | 16 | The full matrix in §2.3, against a real university → college → department hierarchy |
| `NavigationPermissionContractTest::test_visible_navigation_is_backed_by_the_published_capability_set` | 1 | Every offered navigation item is backed by a published capability |
| `client/tests/activeUnitContext.spec.ts` | 9 | Context publication, switching, stale-capability withdrawal, refused switch, single-switch enforcement, switcher rendering |

---

## 3. Phase 3 — Runtime System Settings Module

### 3.1 Status

**PASS — implemented and verified.** Open Item #1 from the previous cycle is
closed: the `System Settings` screen was a static placeholder that displayed a
success message without contacting the server. It is now backed by a real,
validated, audited and persisted configuration module.

### 3.2 What was implemented

| Layer | Change |
|---|---|
| Catalogue | `server/config/settings.php` declares every setting: key, label, description, category, type, default, validation rules, scope and editable status. The catalogue is the allowlist; a key that is not declared cannot be stored, edited or reset. |
| Storage | New `system_settings` table with typed value columns, a scope discriminator, a unique constraint per (key, scope), two foreign keys and three check constraints. |
| Service | `App\Services\Settings\SettingsService` resolves values, validates them against the catalogue, writes them atomically and records the audit event inside the same transaction. |
| API | `SettingsController` under `/api/v1/settings` — catalogue, categories, update, reset, category reset, plus a public projection. |
| Runtime | `ApplyRuntimeSettings` applies the session lifetime before the session starts; the document endpoints consume the upload limit and the default classification. |
| Interface | `SystemSettingsView` rewritten with category navigation, typed editors, unsaved-change tracking, save/cancel, reset with confirmation, and loading, empty, error, saving and success states. |

### 3.3 The catalogue actually shipped

| Identifier | Type | Default | Scope | Runtime effect |
|---|---|---|---|---|
| `institution.display_name` | string | `Wollo University` | global | Published to the client; applied to the browser title and the public portal |
| `documents.max_upload_size_mb` | integer | `100` | global | The maximum accepted upload, applied to both document upload endpoints |
| `documents.default_confidentiality_level_code` | select | `internal` | global or unit | The classification applied when a document is registered without an explicit level |
| `session.lifetime_minutes` | integer | `120` | global | The idle session lifetime, applied before the session starts |
| `documents.reference_number_scheme` | string | `WU/DOC/{YEAR}/{SEQUENCE}` | global | Not editable. A source managed invariant, displayed for reference only |

Five candidate settings were **deliberately excluded** because the application
does not consume them. Shipping them would have produced settings that appear
to work but have no effect. The reasoning is recorded in
`docs/CONFIGURATION_CATALOG.md` §3.3.

### 3.4 Authorization and security test matrix — actual results

`SystemSettingsTest` — 25 tests, 101 assertions, all passing.

| Scenario | Expected behaviour | Result |
|---|---|---|
| Authorized settings retrieval | Catalogue, categories and deployment boundary returned | PASS |
| Unauthorized settings retrieval | 403 without `system.configure` | PASS |
| Unauthenticated request | 401 | PASS |
| Valid setting update | Persisted and returned | PASS |
| Invalid value | 422, nothing stored | PASS |
| Wrong value type | 422 | PASS |
| Unknown setting key | 422 | PASS |
| Protected setting update (a declared but non-editable setting) | 422 | PASS |
| Infrastructure secret update (`app.key`, `db.password`, `mail.password`, `aws.secret_access_key`) | 422, never writable | PASS |
| Select setting with an undeclared value | 422 | PASS |
| Select setting with a declared value | 200, persisted | PASS |
| Reset to default | Value removed, default in force | PASS |
| Category reset | Only that category reset | PASS |
| Audit event creation | Key, previous value, new value, scope, operation, actor | PASS |
| Audit rollback after a failed operation | No audit event written | PASS |
| No partial write on a rejected batch | No row written, no audit event | PASS |
| Duplicate configuration | Prevented by the database constraint | PASS |
| Global and unit scope isolation | Separate rows, unit value overrides | PASS |
| Cross-unit administrative access | 403 | PASS |
| Unit scope on a global-only setting | 422 | PASS |
| Deployment boundary secrecy | Sensitive entries carry no value | PASS |
| Public projection | Only the declared public keys, no authentication | PASS |
| Session lifetime applied | `config('session.lifetime')` reflects the stored value | PASS |
| Upload limit applied | A 2 MB upload is refused at 1 MB and accepted at 5 MB | PASS |
| Default classification applied | The configured level is used; an explicit level always wins | PASS |

### 3.5 Frontend test matrix — actual results

`client/tests/systemSettings.spec.ts` — 14 tests, all passing.

Settings loading · category selection · the control that matches the declared
type · a non-editable setting marked as fixed · the deployment boundary with no
secret value · unsaved-change tracking · cancel · save through the API ·
validation failure surfaced · reset confirmation requested before any request ·
reset cancelled · category reset · a failure to load · a failed save that does
not claim success · the catalogue re-read after a reset — **all PASS**.

### 3.6 A database constraint discovered during implementation

The first design used a **stored generated column** for the scope
discriminator, mirroring the existing `authorization_scopes` table. The
migration failed on MySQL 8.4 with error 1215. The cause was isolated with a
sequence of probes:

| Shape | Result |
|---|---|
| Generated column + foreign key added by `ALTER` + `ON DELETE CASCADE` | **Rejected** (1215) |
| Generated column + foreign key added by `ALTER`, no cascade | Allowed |
| Generated column + foreign key declared inline in `CREATE` | Allowed |
| Plain column + `ON DELETE CASCADE` + a consistency check constraint | Allowed |

MySQL refuses a cascading referential action on a table that carries a stored
generated column. The final design uses a plain `scope_key` column together
with a check constraint that keeps it in agreement with `scope_unit_id`, which
preserves both the cascade and the uniqueness guarantee.

### 3.7 Defects found by the new tests during implementation

| Defect | Closure |
|---|---|
| The validation rules lost their field key, so validation ran against the fields `0`, `1`, `2` and every value was refused | The rules are bound to the `value` field again |
| A batch update wrote each setting in its own transaction, so a rejected value left the earlier settings changed | The whole batch is applied in one transaction |
| A reset referenced a variable that was not captured by its closure | The closure captures the previous value |
| The previous value recorded in the audit was `null` when no override existed | It records the value actually in force, which is the catalogue default |
| The public settings route was registered inside the `auth` prefix group and resolved to 404 | It is registered as `/api/v1/settings/public` |
| The classification default was not captured by the document creation transaction | The closure captures it |
| The document controller constructor changed its signature, breaking a test that instantiates it directly | The controller resolves the service from the container, as the rest of the code base does |
| The select control was rendered with `BaseInput`, which has no `options` prop, so it rendered as a text input | The select control uses `BaseSelect` |

---

## 4. Phase 4 — Dataset Import & Export Module

### 4.1 Status

**PASS — implemented and verified.** Open Item #2 from the previous cycle is
closed. The `Data Imports & Exports` screen was a static table of hardcoded
rows whose buttons reported "Export job queued" and "Batch import validated and
queued" without contacting the server at all. It is now backed by a real,
audited, database-backed module.

### 4.2 What was implemented

| Layer | Change |
|---|---|
| Catalogue | `config/data_transfer.php` declares the supported datasets: two importable, four exportable, CSV only. Nothing outside the catalogue can be performed. |
| Storage | Three tables: `data_import_operations`, `data_import_row_errors`, `data_export_operations`. |
| Service | `DataTransferService` implements the import lifecycle and the export generation. `CsvWriter` writes UTF-8 files with spreadsheet formula injection neutralised. |
| API | `DataTransferController` under `/api/v1/data`: dataset catalogue, import upload, inspection, execution, error report, discard, export history, generation and download. |
| Authorization | Two new capabilities, `data.import` and `data.export`, govern the operations. The dataset's own capability governs the content. |
| Runtime setting | `data_imports.max_file_size_mb`, default 20 MB. It is deliberately distinct from the document upload limit. |
| Interface | `DataImportsExportsView` rewritten with the real workflow, real upload progress, a validation preview, row level errors, confirmation, actual results and operation history. |

### 4.3 Supported operations

| Dataset | Direction | Capability | Scope |
|---|---|---|---|
| `organization_units` | import | `organization.manage` | active organizational unit |
| `organization_memberships` | import | `organization.manage` | active organizational unit |
| `document_catalog` | export | `documents.view` | permitted units |
| `correspondence_registry` | export | `correspondence.view` | permitted units |
| `organization_directory` | export | `organization.view` | permitted units |
| `audit_events` | export | `audit.view` | global |

The full specification is in `docs/DATA_IMPORT_EXPORT_CATALOG.md`.

### 4.4 Deliberate exclusions

Business records that carry a registration workflow (documents, correspondence)
are **not** importable: a bulk write would bypass the workflow, the reference
number sequence and the record history. Staff records, the RBAC tables,
sessions and credentials are not exported. The spreadsheet library was removed in
an earlier phase and was not re-introduced for one screen, so **XLSX is no
longer advertised**; the interface previously claimed support it never had.

### 4.5 Authorization and security test matrix — actual results

`DataTransferTest` — 30 tests, 87 assertions, all passing.

| Scenario | Expected behaviour | Result |
|---|---|---|
| Import upload authorization | 403 without `data.import` | PASS |
| Unsupported file format | 422 | PASS |
| Unreadable file content | 422 | PASS |
| Oversized file | 422 | PASS |
| Invalid headers | 422 | PASS |
| Required field validation | Row level failure, nothing stored | PASS |
| Type validation | Row level failure | PASS |
| Duplicate detection | Reported as a duplicate | PASS |
| Preview without persistence | No record created | PASS |
| Authorized import | The records are created | PASS |
| Unauthorized import | 403 | PASS |
| Cross-unit import | 403 | PASS |
| Invalid foreign key reference | Row level failure | PASS |
| Transaction rollback | `failed`, zero imported | PASS |
| Batch failure behaviour | Valid records committed, failures reported | PASS |
| Audit event creation | Recorded on inspection and on execution | PASS |
| No completion audit after a failure | No completion event | PASS |
| Export authorization | 403 without `data.export` | PASS |
| Export filters | Only the matching records are exported | PASS |
| Cross-unit export protection | The foreign unit's records are absent | PASS |
| Confidentiality restriction | The record policy is applied per record | PASS |
| Export field allowlisting | Only the declared columns are written | PASS |
| CSV injection protection | The formula is prefixed | PASS |
| Amharic text | Preserved in the file | PASS |
| Download authorization | 403 without the dataset capability | PASS |
| Download returns the file | The real generated file | PASS |
| Expired export | 404 | PASS |
| Permission revoked | The download stops immediately | PASS |
| Temporary file cleanup | The uploaded file is removed on discard | PASS |

### 4.6 Frontend test matrix — actual results

`client/tests/dataImportsExports.spec.ts` — 9 tests, all passing.

Loading of the catalogue and the history · the required columns of the selected
dataset · the inspection and the validation preview · an upload failure without a
false preview · the import executed only after the preview was accepted · the
export generated with the applied filters · a failure to load · the forbidden
module without the workflow · the empty state — **all PASS**.

### 4.7 Defects found by the new tests during implementation

| Defect | Closure |
|---|---|
| The dataset dispatcher used `array_key_first()`, which returns the key and not the value, so no export branch ever matched and every export was empty | The dispatcher names the dataset explicitly |
| The row error records were created without the operation identifier | The identifier is written with every error |
| The dataset capability was checked without a scope, so a unit scoped holder was refused | The scope is resolved before the check |
| An export operation without a scope was treated as a single resource and refused | A scope less export covers every permitted unit and is not resource bound |
| Reading the uploaded file of an operation with no stored path raised an error | The operation is refused with a clear message |
| The navigation item for the screen required `roles.view`, which the API never required | All three declarations now require the data capabilities |
| `hasPermission` accepted only a string, so an array capability raised a type error | The store accepts a list with "any of" semantics, matching the route guard |

### 4.8 Performance observations

| Observation | Value |
|---|---|
| Export memory use | Bounded. The rows are produced by a generator and written straight to the file; no result set is materialised. |
| Export query shape | One query per dataset with the eager loaded relations. `cursor()` streams the result. |
| Import memory use | Bounded. The file is read row by row and the valid rows are written inside a single transaction. |
| Export row ceiling | 10 000 records, enforced by the query limit. |
| Import row ceiling | 2 000 records, enforced during the inspection. |
| Export retention | 24 hours, after which the file is no longer retrievable. |

No queue was introduced. The deployment does not guarantee a worker, and a
simulated progress indicator would be dishonest.

---

## 5. Test Inventory

### 5.1 Backend (PHPUnit / Pest) - 363 tests

| Suite | Purpose |
|---|---|
| `ActiveOrganizationalUnitTest` | Active unit context and scope-aware authorization |
| `AuthorizationBoundaryEnforcementTest` | Record-level boundary enforcement across six record families |
| `NavigationPermissionContractTest` | Four-way agreement between the API contract, the dynamic catalog, the front-end router and the static fallback; registry integrity |
| `AccountRecoveryTest` | Recovery delivery, non-enumeration, session termination, address confirmation |
| `SecurityAndAccessControlAuditTest` | Cross-department access, anti-self-approval, confidentiality |
| `SecurityPenetrationTest` | Session invalidation, account lockout, replay resistance |
| Remaining 22 suites | Workflow, correspondence, repository, archive, PKI, disaster recovery, schema invariants, scheduling, transactions, credentials |

### 5.2 Frontend (Vitest) - 152 tests

| Suite | Purpose |
|---|---|
| `activeUnitContext.spec.ts` | Organizational context publication and switching |
| `authSessionLifecycle.spec.ts` | 401 clears the in-memory session, 403 preserves it, route guards |
| `accountRecovery.spec.ts` | Recovery request reaches the API, failure is surfaced, confirmation consumes the link |
| `DocumentDetailView.spec.ts` | Document detail rendering and action states |
| `AppSidebar.spec.ts` | Collapsible navigation |
| Remaining 18 suites | Component, store and composable behaviour |

---

## 6. Defects Found and Closed

Every item below was discovered by inspection of the running code, reproduced,
and closed with a regression test.

| # | Severity | Defect | Root cause | Closure |
|---|---|---|---|---|
| 1 | **High** | `POST /extractions/{id}/verify` allowed a caller with a global `documents.update` grant to rewrite the verified OCR text of any department's document. The nested sibling route enforced ownership; the flat alias did not. | The middleware resolved record ownership by path substring and had no branch for the extraction family, so the request fell through to a capability-only check. | `RouteScopeResolver` resolves the owning document; `AuthorizationBoundaryEnforcementTest` asserts both routes enforce the same boundary |
| 2 | **High** | `POST /movements/{id}/acknowledge` allowed the **originating** unit to issue a custody receipt for its own outbound transfer. | No branch for the movement family; the sibling correspondence route resolved `to_unit_id` but the direct route did not. | Resolver binds the movement to `to_unit_id`; test asserts the originating unit is refused and the receiving unit succeeds |
| 3 | **High** | `POST /assignments/{id}/participants`, `/events` and `/responses` performed no ownership check; the controller and service contain no authorization logic at all. | The assignment family had no resolution branch. | Resolver binds the directive to `assigned_unit_id`; test asserts refusal and absence of side effects |
| 4 | **High** | `POST /workflows/tasks/{id}/reassign` was unreachable for **every** account, including the super administrator. | The route required `workflows.manage`, which is not in the permission registry and therefore cannot be granted to any role. | Corrected to the registered capability `workflow.reassign`; the contract test now fails the build if any route capability is unregistered |
| 5 | **Medium** | The capability decision and the published capability list used different scope rules, so the client could advertise authority the server would refuse — and a subtree grant was invisible for descendant units. | `hasPermission()` and `getEffectivePermissionCodes()` each implemented their own scope filter. | Both now delegate to one `scopeAppliesToUnit()` implementation; test asserts they agree |
| 6 | **Medium** | The account recovery, password reset and address confirmation screens reported success without contacting the server. `SystemSettingsView` and `DataImportsExportsView` are static screens; the settings screen persists nothing and there is no `system_settings` table. | Placeholder user interface. | Recovery and verification are now real API-backed flows; the settings and import/export screens remain open (§6) |
| 7 | **Medium** | A 401 response cleared `localStorage` but left the in-memory Pinia session intact, so the application shell kept rendering an authenticated workspace for a dead session. | The response interceptor had no access to the store. | `sessionEvents.ts` bridges the client to the store; `authSessionLifecycle.spec.ts` asserts the state is cleared |
| 8 | **Medium** | The session bootstrap was marked complete **before** it ran, so a transient failure stranded the application on an empty session until a manual reload. | The flag was set outside the `try` block. | Bootstrap is retried while the session state is `unknown`; the duplicate flag was removed in favour of the store's `sessionState` |
| 9 | **Medium** | Route metadata declared capabilities that the serving API route did not require (`/staff`, `/projects`, `/signatures`, `/admin/operations`, `/admin/settings`, `/search`), so the client admitted or refused surfaces inconsistently with the server. | Four independent permission declarations with no contract. | All four declarations aligned; `NavigationPermissionContractTest` asserts their agreement |
| 10 | **Medium** | The dynamic navigation catalog omitted the Templates surface entirely, so it disappeared from the menu once the dynamic catalog loaded, while remaining visible in the static fallback. | The catalog was not derived from the API contract. | Templates added; the static fallback and the dynamic catalog are now asserted to agree |
| 11 | **Low** | Validation failures were returned without the `success` discriminator used by every other API response. | The framework's default validation rendering was used. | A validation renderer in `bootstrap/app.php` emits the documented envelope; the change is additive, so existing assertions are unaffected |
| 12 | **Low** | A document that did not exist passed the authorization middleware and was rejected only by the controller. | The middleware returned "allow" for an unresolved record. | The middleware now fails closed with HTTP 404 |
| 13 | **Medium** | Resolving the organizational context executed **116** database queries for a single request, because the subtree walk was repeated once per grant and the grants were loaded once per capability resolution. | `getDescendantIds()` was called per scope, and each capability resolution re-queried the grants. | The subtree expansion is batched across all anchors and the grants are loaded once per context: **19 queries**. Grants are never cached on the model, so a revoked capability is never advertised |

### 6.1 Dependency remediation

| Package | From | To | Reason |
|---|---|---|---|
| `symfony/http-foundation` | 8.1.9 | 8.1.11 | CVE-2026-118514, PKSA-pr6q-2w5s-kzzf, PKSA-nxjs-s6hn-vb6m, PKSA-b2gg-vk1g-6d5q |
| `symfony/http-kernel` | 8.1.9 | 8.1.11 | CVE-2026-123339, GHSA-wg5q-m2ww-f3c9 |
| `symfony/routing` | 8.1.9 | 8.1.11 | CVE-2026-118516 |

`composer audit` now reports **no** advisories.

---

## 7. Architecture Delivered

| Component | Purpose |
|---|---|
| `app/Services/Authorization/RouteScopeResolver.php` | Resolves the record behind a request to its owning document or unit |
| `app/Services/Authorization/RouteAuthorizationTarget.php` | Immutable description of the resolved target and the policy it selects |
| `app/Services/Authorization/OrganizationalContextService.php` | Available units, active unit resolution, context publication |
| `app/Http/Middleware/CheckBaselinePermission.php` | The single authoritative authorization gate |
| `app/Models/User.php` | The single scope-matching implementation (`scopeAppliesToUnit`) and the single-pass capability resolution (`getCapabilitySets`) |
| `bootstrap/app.php` | Middleware priority, validation error envelope |
| `client/src/api/sessionEvents.ts` | Decouples the HTTP client from the authentication store |
| `client/src/stores/auth.ts` | Session state machine and the organizational context |
| `config/settings.php` | The authoritative runtime configuration catalogue |
| `app/Services/Settings/SettingsService.php` | Resolves, validates, persists and audits configuration values |
| `app/Models/SystemSetting.php` | A stored configuration value and its scope |
| `app/Http/Controllers/Api/V1/SettingsController.php` | The configuration REST contract |
| `app/Http/Middleware/ApplyRuntimeSettings.php` | Applies the runtime configuration before the session starts |
| `client/src/composables/useInstitution.ts` | The published institutional identity applied to the interface |
| `config/data_transfer.php` | The authoritative dataset import and export catalogue |
| `app/Services/DataTransfer/DataTransferService.php` | The import lifecycle and the export generation |
| `app/Services/DataTransfer/CsvWriter.php` | UTF-8 CSV writing with spreadsheet formula injection neutralised |
| `app/Http/Controllers/Api/V1/DataTransferController.php` | The dataset import and export REST contract |
| `app/Models/DataImportOperation.php` | The history of an import operation |
| `app/Models/DataImportRowError.php` | A row level validation failure |
| `app/Models/DataExportOperation.php` | The history of an export operation and its artefact |

---

## 8. Open Items

| # | Severity | Item | Impact | Recommended action |
|---|---|---|---|---|
| 1 | ~~**Medium**~~ **CLOSED** | ~~`SystemSettingsView` presents an editable settings form that persists nothing and shows a success toast. There is no `system_settings` table and no settings API.~~ | An administrator believed a configuration was applied when nothing changed. | **Closed in Phase 3.** The catalogue, the storage, the API, the audit trail and the interface are implemented and verified. See §3. |
| 2 | ~~**Medium**~~ **CLOSED** | ~~`DataImportsExportsView` is a static screen with no import or export operation.~~ | Advertised bulk data operations did not exist. | **Closed in Phase 4.** The dataset catalogue, the storage, the API, the audit trail and the interface are implemented and verified. See §4 and `docs/DATA_IMPORT_EXPORT_CATALOG.md`. |
| 3 | **Low** | The navigation catalog and the static fallback are maintained as two parallel declarations. | A future divergence is possible. | Mitigated today by `NavigationPermissionContractTest`; a single generated source would remove the duplication entirely. |
| 4 | **Low** | Assignment participant removal is authorized by unit but records no audit event. | The audit trail is incomplete for that operation. | Add an `AuditEvent` write to `AssignmentService::removeParticipant`. |
| 5 | **Low** | No continuous integration workflow is defined. | Regression detection depends on a local run. | Add a GitHub Actions workflow running the four commands in §1. |
| 6 | **Low** | The active unit is stored in the session, so it is shared between the browser tabs of one session. | Switching in one tab changes the context of the other. | Accepted. A per-tab preference would still have to be validated by the server, so it buys little. |
| 7 | **Informational** | `MAIL_MAILER=log` is the default in `.env.example`. | Recovery and confirmation links are written to the log instead of being delivered. | Set a real transport for any deployment where recovery must work. See `docs/CONFIGURATION_CATALOG.md` §4. |

---

## 9. Environment Notes

| Item | Detail |
|---|---|
| PHP | 8.5.0 (Herd Lite) |
| Composer | 2.8.12 |
| Node.js | 24.22.0 |
| npm | 11.19.1 |
| Database | MySQL 8.4, InnoDB, `utf8mb4_0900_as_cs` |
| Test database | `wollo_dms_test` (refreshed per suite) |
| Staging data inspected | 16 organizational units, 30 memberships, 14 authorization scopes, 30 role grants, 40 direct grants |
| Remote | `https://github.com/ATPLC/DMS.git` — **not reachable from this environment** (private repository, no cached credentials, no `gh` CLI). The push must be performed from an authenticated machine. |
