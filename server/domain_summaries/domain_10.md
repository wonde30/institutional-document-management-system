## Domain 10: Digital Signatures & Stamps (8 Models)

### Model: `SignatureRequest`
- **(1) Table & PK:** table=`signature_requests`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[document_version_id, requested_by, signer_id, requested_at, expires_at, purpose]`
- **(3) Casts:** `requested_at`: `datetime`, `expires_at`: `datetime`
- **(4) Relationships (4):**
  - `documentVersion()`: **BelongsTo** -> `DocumentVersion` (FK=`document_version_id`, Local/OwnerKey=`id`)
  - `requestedBy()`: **BelongsTo** -> `User` (FK=`requested_by`, Local/OwnerKey=`id`)
  - `signer()`: **BelongsTo** -> `User` (FK=`signer_id`, Local/OwnerKey=`id`)
  - `digitalSignature()`: **HasOne** -> `DigitalSignature` (FK=`request_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `DigitalSignature`
- **(1) Table & PK:** table=`digital_signatures`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[request_id, signing_identity_id, source_file_id, signed_file_id, signature_format, signed_at, provider_transaction_reference, verification_status, trust_tier, certificate_metadata]`
- **(3) Casts:** `signed_at`: `datetime`, `certificate_metadata`: `array`
- **(4) Relationships (5):**
  - `request()`: **BelongsTo** -> `SignatureRequest` (FK=`request_id`, Local/OwnerKey=`id`)
  - `signingIdentity()`: **BelongsTo** -> `SigningIdentity` (FK=`signing_identity_id`, Local/OwnerKey=`id`)
  - `sourceFile()`: **BelongsTo** -> `FileObject` (FK=`source_file_id`, Local/OwnerKey=`id`)
  - `signedFile()`: **BelongsTo** -> `FileObject` (FK=`signed_file_id`, Local/OwnerKey=`id`)
  - `verifications()`: **HasMany** -> `SignatureVerification` (FK=`signature_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `SigningIdentity`
- **(1) Table & PK:** table=`signing_identities`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[user_id, provider_code, provider_identity_reference, public_certificate_file_id, valid_from, valid_until, revoked_at]`
- **(3) Casts:** `valid_from`: `datetime`, `valid_until`: `datetime`, `revoked_at`: `datetime`
- **(4) Relationships (2):**
  - `user()`: **BelongsTo** -> `User` (FK=`user_id`, Local/OwnerKey=`id`)
  - `publicCertificateFile()`: **BelongsTo** -> `FileObject` (FK=`public_certificate_file_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `SignatureVerification`
- **(1) Table & PK:** table=`signature_verifications`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[signature_id, verified_at, outcome, certificate_status, timestamp_status, details_json]`
- **(3) Casts:** `verified_at`: `datetime`, `details_json`: `array`
- **(4) Relationships (1):**
  - `signature()`: **BelongsTo** -> `DigitalSignature` (FK=`signature_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `StampDefinition`
- **(1) Table & PK:** table=`stamp_definitions`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[unit_id, name, active]`
- **(3) Casts:** `active`: `boolean`
- **(4) Relationships (2):**
  - `unit()`: **BelongsTo** -> `OrganizationUnit` (FK=`unit_id`, Local/OwnerKey=`id`)
  - `versions()`: **HasMany** -> `StampVersion` (FK=`stamp_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `StampVersion`
- **(1) Table & PK:** table=`stamp_versions`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[stamp_id, version_number, image_file_id, effective_from, retired_at]`
- **(3) Casts:** `version_number`: `integer`, `effective_from`: `datetime`, `retired_at`: `datetime`
- **(4) Relationships (2):**
  - `stamp()`: **BelongsTo** -> `StampDefinition` (FK=`stamp_id`, Local/OwnerKey=`id`)
  - `imageFile()`: **BelongsTo** -> `FileObject` (FK=`image_file_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `StampAuthorization`
- **(1) Table & PK:** table=`stamp_authorizations`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[stamp_id, user_id, granted_by, valid_from, valid_until]`
- **(3) Casts:** `valid_from`: `datetime`, `valid_until`: `datetime`
- **(4) Relationships (3):**
  - `stamp()`: **BelongsTo** -> `StampDefinition` (FK=`stamp_id`, Local/OwnerKey=`id`)
  - `user()`: **BelongsTo** -> `User` (FK=`user_id`, Local/OwnerKey=`id`)
  - `grantedBy()`: **BelongsTo** -> `User` (FK=`granted_by`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `StampApplication`
- **(1) Table & PK:** table=`stamp_applications`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[stamp_version_id, document_version_id, source_file_id, result_file_id, applied_by, applied_at, reason]`
- **(3) Casts:** `applied_at`: `datetime`
- **(4) Relationships (5):**
  - `stampVersion()`: **BelongsTo** -> `StampVersion` (FK=`stamp_version_id`, Local/OwnerKey=`id`)
  - `documentVersion()`: **BelongsTo** -> `DocumentVersion` (FK=`document_version_id`, Local/OwnerKey=`id`)
  - `sourceFile()`: **BelongsTo** -> `FileObject` (FK=`source_file_id`, Local/OwnerKey=`id`)
  - `resultFile()`: **BelongsTo** -> `FileObject` (FK=`result_file_id`, Local/OwnerKey=`id`)
  - `appliedBy()`: **BelongsTo** -> `User` (FK=`applied_by`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified
