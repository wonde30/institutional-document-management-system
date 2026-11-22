## Domain 7: Staff HR & Physical Records (11 Models)

### Model: `StaffProfile`
- **(1) Table & PK:** table=`staff_profiles`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[user_id, employee_number, biography]`
- **(3) Casts:** `created_at`: `datetime`, `updated_at`: `datetime`
- **(4) Relationships (1):**
  - `user()`: **BelongsTo** -> `User` (FK=`user_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `StaffEmploymentRecord`
- **(1) Table & PK:** table=`staff_employment_records`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[staff_id, unit_id, position_title, starts_on, ends_on, evidence_document_id]`
- **(3) Casts:** `starts_on`: `date`, `ends_on`: `date`
- **(4) Relationships (3):**
  - `staff()`: **BelongsTo** -> `StaffProfile` (FK=`staff_id`, Local/OwnerKey=`id`)
  - `unit()`: **BelongsTo** -> `OrganizationUnit` (FK=`unit_id`, Local/OwnerKey=`id`)
  - `evidenceDocument()`: **BelongsTo** -> `Document` (FK=`evidence_document_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `StaffQualification`
- **(1) Table & PK:** table=`staff_qualifications`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[staff_id, qualification_type_id, institution_id, field_of_study, awarded_on, evidence_document_id]`
- **(3) Casts:** `awarded_on`: `date`
- **(4) Relationships (4):**
  - `staff()`: **BelongsTo** -> `StaffProfile` (FK=`staff_id`, Local/OwnerKey=`id`)
  - `qualificationType()`: **BelongsTo** -> `QualificationType` (FK=`qualification_type_id`, Local/OwnerKey=`id`)
  - `institution()`: **BelongsTo** -> `Institution` (FK=`institution_id`, Local/OwnerKey=`id`)
  - `evidenceDocument()`: **BelongsTo** -> `Document` (FK=`evidence_document_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `QualificationType`
- **(1) Table & PK:** table=`qualification_types`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[code, name, description, active]`
- **(3) Casts:** `active`: `boolean`
- **(4) Relationships:** None
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `StaffCertificate`
- **(1) Table & PK:** table=`staff_certificates`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[staff_id, title, issuer_id, issued_on, expires_on, evidence_document_id]`
- **(3) Casts:** `issued_on`: `date`, `expires_on`: `date`
- **(4) Relationships (3):**
  - `staff()`: **BelongsTo** -> `StaffProfile` (FK=`staff_id`, Local/OwnerKey=`id`)
  - `issuer()`: **BelongsTo** -> `Institution` (FK=`issuer_id`, Local/OwnerKey=`id`)
  - `evidenceDocument()`: **BelongsTo** -> `Document` (FK=`evidence_document_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `StaffDocument`
- **(1) Table & PK:** table=`staff_documents`, PK=`id`, incrementing=`False`, keyType=`int`
- **(2) Fillable:** `[staff_id, document_id, record_type_id]`
- **(3) Casts:** none (except default PK id: int)
- **(4) Relationships (3):**
  - `staff()`: **BelongsTo** -> `StaffProfile` (FK=`staff_id`, Local/OwnerKey=`id`)
  - `document()`: **BelongsTo** -> `Document` (FK=`document_id`, Local/OwnerKey=`id`)
  - `recordType()`: **BelongsTo** -> `HrRecordType` (FK=`record_type_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `HrRecordType`
- **(1) Table & PK:** table=`hr_record_types`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[code, name, description, active]`
- **(3) Casts:** `active`: `boolean`
- **(4) Relationships:** None
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `PhysicalOriginal`
- **(1) Table & PK:** table=`physical_originals`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[document_id, physical_reference, retention_requirement, retention_policy_version_id, created_by, created_at]`
- **(3) Casts:** `created_at`: `datetime`
- **(4) Relationships (5):**
  - `document()`: **BelongsTo** -> `Document` (FK=`document_id`, Local/OwnerKey=`id`)
  - `retentionPolicyVersion()`: **BelongsTo** -> `RetentionPolicyVersion` (FK=`retention_policy_version_id`, Local/OwnerKey=`id`)
  - `createdBy()`: **BelongsTo** -> `User` (FK=`created_by`, Local/OwnerKey=`id`)
  - `custodyEvents()`: **HasMany** -> `PhysicalCustodyEvent` (FK=`physical_original_id`, Local/OwnerKey=`id`)
  - `digitizationRecords()`: **HasMany** -> `DigitizationRecord` (FK=`physical_original_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `PhysicalStorageLocation`
- **(1) Table & PK:** table=`physical_storage_locations`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[parent_id, unit_id, code, name, active]`
- **(3) Casts:** `active`: `boolean`
- **(4) Relationships (2):**
  - `parent()`: **BelongsTo** -> `PhysicalStorageLocation` (FK=`parent_id`, Local/OwnerKey=`id`)
  - `unit()`: **BelongsTo** -> `OrganizationUnit` (FK=`unit_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `PhysicalCustodyEvent`
- **(1) Table & PK:** table=`physical_custody_events`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[physical_original_id, location_id, custodian_id, action, actor_id, occurred_at, reason]`
- **(3) Casts:** `occurred_at`: `datetime`
- **(4) Relationships (4):**
  - `physicalOriginal()`: **BelongsTo** -> `PhysicalOriginal` (FK=`physical_original_id`, Local/OwnerKey=`id`)
  - `location()`: **BelongsTo** -> `PhysicalStorageLocation` (FK=`location_id`, Local/OwnerKey=`id`)
  - `custodian()`: **BelongsTo** -> `User` (FK=`custodian_id`, Local/OwnerKey=`id`)
  - `actor()`: **BelongsTo** -> `User` (FK=`actor_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `PhysicalDispositionTarget`
- **(1) Table & PK:** table=`physical_disposition_targets`, PK=`id`, incrementing=`False`, keyType=`int`
- **(2) Fillable:** `[request_id, physical_original_id]`
- **(3) Casts:** none (except default PK id: int)
- **(4) Relationships (2):**
  - `request()`: **BelongsTo** -> `DispositionRequest` (FK=`request_id`, Local/OwnerKey=`id`)
  - `physicalOriginal()`: **BelongsTo** -> `PhysicalOriginal` (FK=`physical_original_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified
