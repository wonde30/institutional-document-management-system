## Domain 6: Archive, Retention & Legal Holds (10 Models)

### Model: `ArchiveCollection`
- **(1) Table & PK:** table=`archive_collections`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[parent_id, code, name, description]`
- **(3) Casts:** none (except default PK id: int)
- **(4) Relationships (1):**
  - `parent()`: **BelongsTo** -> `ArchiveCollection` (FK=`parent_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `ArchiveEntry`
- **(1) Table & PK:** table=`archive_entries`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[document_id, collection_id, retention_policy_version_id, archived_by, archived_at, trigger_occurred_at, next_review_at]`
- **(3) Casts:** `archived_at`: `datetime`, `trigger_occurred_at`: `datetime`, `next_review_at`: `datetime`
- **(4) Relationships (5):**
  - `document()`: **BelongsTo** -> `Document` (FK=`document_id`, Local/OwnerKey=`id`)
  - `collection()`: **BelongsTo** -> `ArchiveCollection` (FK=`collection_id`, Local/OwnerKey=`id`)
  - `retentionPolicyVersion()`: **BelongsTo** -> `RetentionPolicyVersion` (FK=`retention_policy_version_id`, Local/OwnerKey=`id`)
  - `archivedBy()`: **BelongsTo** -> `User` (FK=`archived_by`, Local/OwnerKey=`id`)
  - `dispositionRequests()`: **HasMany** -> `DispositionRequest` (FK=`archive_entry_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `ArchiveAction`
- **(1) Table & PK:** table=`archive_actions`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[archive_entry_id, action, actor_id, occurred_at, reason, evidence_document_id]`
- **(3) Casts:** `occurred_at`: `datetime`
- **(4) Relationships (3):**
  - `archiveEntry()`: **BelongsTo** -> `ArchiveEntry` (FK=`archive_entry_id`, Local/OwnerKey=`id`)
  - `actor()`: **BelongsTo** -> `User` (FK=`actor_id`, Local/OwnerKey=`id`)
  - `evidenceDocument()`: **BelongsTo** -> `Document` (FK=`evidence_document_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `DispositionDecision`
- **(1) Table & PK:** table=`disposition_decisions`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[request_id, decided_by, decision, reason, decided_at]`
- **(3) Casts:** `decided_at`: `datetime`
- **(4) Relationships (2):**
  - `request()`: **BelongsTo** -> `DispositionRequest` (FK=`request_id`, Local/OwnerKey=`id`)
  - `decidedBy()`: **BelongsTo** -> `User` (FK=`decided_by`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `DispositionExecution`
- **(1) Table & PK:** table=`disposition_executions`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[request_id, executed_by, executed_at, evidence_document_id, external_receipt]`
- **(3) Casts:** `executed_at`: `datetime`
- **(4) Relationships (3):**
  - `request()`: **BelongsTo** -> `DispositionRequest` (FK=`request_id`, Local/OwnerKey=`id`)
  - `executedBy()`: **BelongsTo** -> `User` (FK=`executed_by`, Local/OwnerKey=`id`)
  - `evidenceDocument()`: **BelongsTo** -> `Document` (FK=`evidence_document_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `DispositionRequest`
- **(1) Table & PK:** table=`disposition_requests`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[archive_entry_id, requested_by, requested_action, requested_at, justification]`
- **(3) Casts:** `requested_at`: `datetime`
- **(4) Relationships (4):**
  - `archiveEntry()`: **BelongsTo** -> `ArchiveEntry` (FK=`archive_entry_id`, Local/OwnerKey=`id`)
  - `requestedBy()`: **BelongsTo** -> `User` (FK=`requested_by`, Local/OwnerKey=`id`)
  - `decision()`: **HasOne** -> `DispositionDecision` (FK=`request_id`, Local/OwnerKey=`id`)
  - `execution()`: **HasOne** -> `DispositionExecution` (FK=`request_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `RetentionPolicy`
- **(1) Table & PK:** table=`retention_policies`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[code, name, description, document_category_id, active]`
- **(3) Casts:** `document_category_id`: `integer`, `active`: `boolean`
- **(4) Relationships (3):**
  - `versions()`: **HasMany** -> `RetentionPolicyVersion` (FK=`policy_id`, Local/OwnerKey=`id`)
  - `activeVersion()`: **HasOne** -> `RetentionPolicyVersion` (FK=`policy_id`, Local/OwnerKey=`id`)
  - `documentCategory()`: **BelongsTo** -> `DocumentCategory` (FK=`document_category_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `RetentionPolicyVersion`
- **(1) Table & PK:** table=`retention_policy_versions`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[policy_id, version_number, trigger_code, duration_months, disposition_action, approved_by, approved_at, effective_from]`
- **(3) Casts:** `version_number`: `integer`, `duration_months`: `integer`, `approved_at`: `datetime`, `effective_from`: `date`
- **(4) Relationships (2):**
  - `policy()`: **BelongsTo** -> `RetentionPolicy` (FK=`policy_id`, Local/OwnerKey=`id`)
  - `approvedBy()`: **BelongsTo** -> `User` (FK=`approved_by`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `LegalHold`
- **(1) Table & PK:** table=`legal_holds`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[code, reason, placed_by, placed_at, released_by, released_at]`
- **(3) Casts:** `placed_at`: `datetime`, `released_at`: `datetime`
- **(4) Relationships (3):**
  - `placedBy()`: **BelongsTo** -> `User` (FK=`placed_by`, Local/OwnerKey=`id`)
  - `releasedBy()`: **BelongsTo** -> `User` (FK=`released_by`, Local/OwnerKey=`id`)
  - `documents()`: **BelongsToMany** -> `Document` (FK=`hold_id`, Local/OwnerKey=`document_id`, pivot=`legal_hold_documents`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `LegalHoldDocument`
- **(1) Table & PK:** table=`legal_hold_documents`, PK=`id`, incrementing=`False`, keyType=`int`
- **(2) Fillable:** `[hold_id, document_id]`
- **(3) Casts:** none (except default PK id: int)
- **(4) Relationships (2):**
  - `hold()`: **BelongsTo** -> `LegalHold` (FK=`hold_id`, Local/OwnerKey=`id`)
  - `document()`: **BelongsTo** -> `Document` (FK=`document_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified
