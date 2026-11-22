## Domain 9: Accreditation Frameworks & Cycles (6 Models)

### Model: `AccreditationFramework`
- **(1) Table & PK:** table=`accreditation_frameworks`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[code, name, description, active]`
- **(3) Casts:** `active`: `boolean`
- **(4) Relationships (1):**
  - `versions()`: **HasMany** -> `AccreditationFrameworkVersion` (FK=`framework_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `AccreditationFrameworkVersion`
- **(1) Table & PK:** table=`accreditation_framework_versions`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[framework_id, version_label, effective_from]`
- **(3) Casts:** `effective_from`: `date`
- **(4) Relationships (3):**
  - `framework()`: **BelongsTo** -> `AccreditationFramework` (FK=`framework_id`, Local/OwnerKey=`id`)
  - `standards()`: **HasMany** -> `AccreditationStandard` (FK=`framework_version_id`, Local/OwnerKey=`id`)
  - `cycles()`: **HasMany** -> `AccreditationCycle` (FK=`framework_version_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `AccreditationStandard`
- **(1) Table & PK:** table=`accreditation_standards`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[framework_version_id, parent_id, code, title, description]`
- **(3) Casts:** none (except default PK id: int)
- **(4) Relationships (4):**
  - `frameworkVersion()`: **BelongsTo** -> `AccreditationFrameworkVersion` (FK=`framework_version_id`, Local/OwnerKey=`id`)
  - `parent()`: **BelongsTo** -> `AccreditationStandard` (FK=`parent_id`, Local/OwnerKey=`id`)
  - `children()`: **HasMany** -> `AccreditationStandard` (FK=`parent_id`, Local/OwnerKey=`id`)
  - `evidence()`: **HasMany** -> `AccreditationEvidence` (FK=`standard_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `AccreditationCycle`
- **(1) Table & PK:** table=`accreditation_cycles`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[framework_version_id, unit_id, name, starts_on, ends_on]`
- **(3) Casts:** `starts_on`: `date`, `ends_on`: `date`
- **(4) Relationships (3):**
  - `frameworkVersion()`: **BelongsTo** -> `AccreditationFrameworkVersion` (FK=`framework_version_id`, Local/OwnerKey=`id`)
  - `unit()`: **BelongsTo** -> `OrganizationUnit` (FK=`unit_id`, Local/OwnerKey=`id`)
  - `evidence()`: **HasMany** -> `AccreditationEvidence` (FK=`cycle_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `AccreditationReview`
- **(1) Table & PK:** table=`accreditation_reviews`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[evidence_id, reviewer_id, decision, comment, reviewed_at]`
- **(3) Casts:** `reviewed_at`: `datetime`
- **(4) Relationships (2):**
  - `evidence()`: **BelongsTo** -> `AccreditationEvidence` (FK=`evidence_id`, Local/OwnerKey=`id`)
  - `reviewer()`: **BelongsTo** -> `User` (FK=`reviewer_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `AccreditationEvidence`
- **(1) Table & PK:** table=`accreditation_evidence`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[cycle_id, standard_id, document_version_id, submitted_by, submitted_at, note]`
- **(3) Casts:** `submitted_at`: `datetime`
- **(4) Relationships (6):**
  - `cycle()`: **BelongsTo** -> `AccreditationCycle` (FK=`cycle_id`, Local/OwnerKey=`id`)
  - `standard()`: **BelongsTo** -> `AccreditationStandard` (FK=`standard_id`, Local/OwnerKey=`id`)
  - `documentVersion()`: **BelongsTo** -> `DocumentVersion` (FK=`document_version_id`, Local/OwnerKey=`id`)
  - `submittedBy()`: **BelongsTo** -> `User` (FK=`submitted_by`, Local/OwnerKey=`id`)
  - `reviews()`: **HasMany** -> `AccreditationReview` (FK=`evidence_id`, Local/OwnerKey=`id`)
  - `latestReview()`: **HasOne** -> `AccreditationReview` (FK=`evidence_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified
