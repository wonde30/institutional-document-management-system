## Domain 4: Correspondence & External Parties (11 Models)

### Model: `Correspondence`
- **(1) Table & PK:** table=`correspondences`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[document_id, type_id, priority_id, purpose_code, external_reference, correspondence_date, first_receiving_unit_id, received_at, requested_due_at, registered_by, registered_at]`
- **(3) Casts:** `correspondence_date`: `date`, `received_at`: `datetime`, `requested_due_at`: `datetime`, `registered_at`: `datetime`
- **(4) Relationships (9):**
  - `document()`: **BelongsTo** -> `Document` (FK=`document_id`, Local/OwnerKey=`id`)
  - `type()`: **BelongsTo** -> `CorrespondenceType` (FK=`type_id`, Local/OwnerKey=`id`)
  - `priority()`: **BelongsTo** -> `Priority` (FK=`priority_id`, Local/OwnerKey=`id`)
  - `firstReceivingUnit()`: **BelongsTo** -> `OrganizationUnit` (FK=`first_receiving_unit_id`, Local/OwnerKey=`id`)
  - `registrar()`: **BelongsTo** -> `User` (FK=`registered_by`, Local/OwnerKey=`id`)
  - `participants()`: **HasMany** -> `CorrespondenceParticipant` (FK=`correspondence_id`, Local/OwnerKey=`id`)
  - `dispatches()`: **HasManyThrough** -> `CorrespondenceDispatch` (FK=`None`, Local/OwnerKey=`None`)
  - `movements()`: **HasMany** -> `DocumentMovement` (FK=`document_id`, Local/OwnerKey=`document_id`)
  - `assignments()`: **HasMany** -> `DocumentAssignment` (FK=`document_id`, Local/OwnerKey=`document_id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** `accessibleBy` (local)
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `CorrespondenceType`
- **(1) Table & PK:** table=`correspondence_types`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[code, name, description, active]`
- **(3) Casts:** `active`: `boolean`
- **(4) Relationships:** None
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `CorrespondenceProcedure`
- **(1) Table & PK:** table=`correspondence_procedures`, PK=`correspondence_id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[correspondence_id, procedure_version_id, applied_by, applied_at]`
- **(3) Casts:** `correspondence_id`: `int`, `applied_at`: `datetime`
- **(4) Relationships (3):**
  - `correspondence()`: **BelongsTo** -> `Correspondence` (FK=`correspondence_id`, Local/OwnerKey=`id`)
  - `procedureVersion()`: **BelongsTo** -> `OfficeProcedureVersion` (FK=`procedure_version_id`, Local/OwnerKey=`id`)
  - `appliedBy()`: **BelongsTo** -> `User` (FK=`applied_by`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `CorrespondenceParticipant`
- **(1) Table & PK:** table=`correspondence_participants`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[correspondence_id, party_id, participation_type]`
- **(3) Casts:** none (except default PK id: int)
- **(4) Relationships (3):**
  - `correspondence()`: **BelongsTo** -> `Correspondence` (FK=`correspondence_id`, Local/OwnerKey=`id`)
  - `party()`: **BelongsTo** -> `CommunicationParty` (FK=`party_id`, Local/OwnerKey=`id`)
  - `dispatches()`: **HasMany** -> `CorrespondenceDispatch` (FK=`participant_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `CommunicationParty`
- **(1) Table & PK:** table=`communication_parties`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[unit_id, user_id, external_party_id]`
- **(3) Casts:** none (except default PK id: int)
- **(4) Relationships (3):**
  - `unit()`: **BelongsTo** -> `OrganizationUnit` (FK=`unit_id`, Local/OwnerKey=`id`)
  - `user()`: **BelongsTo** -> `User` (FK=`user_id`, Local/OwnerKey=`id`)
  - `externalParty()`: **BelongsTo** -> `ExternalParty` (FK=`external_party_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `ExternalParty`
- **(1) Table & PK:** table=`external_parties`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[name, party_type, external_identifier, created_at]`
- **(3) Casts:** `created_at`: `datetime`
- **(4) Relationships:** None
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `ExternalPartyContact`
- **(1) Table & PK:** table=`external_party_contacts`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[external_party_id, contact_type, value, label]`
- **(3) Casts:** none (except default PK id: int)
- **(4) Relationships (1):**
  - `externalParty()`: **BelongsTo** -> `ExternalParty` (FK=`external_party_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `CorrespondenceDispatch`
- **(1) Table & PK:** table=`correspondence_dispatches`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[participant_id, document_version_id, method_id, dispatched_by, dispatched_at, tracking_reference, evidence_file_id, idempotency_key]`
- **(3) Casts:** `dispatched_at`: `datetime`
- **(4) Relationships (5):**
  - `participant()`: **BelongsTo** -> `CorrespondenceParticipant` (FK=`participant_id`, Local/OwnerKey=`id`)
  - `documentVersion()`: **BelongsTo** -> `DocumentVersion` (FK=`document_version_id`, Local/OwnerKey=`id`)
  - `method()`: **BelongsTo** -> `DispatchMethod` (FK=`method_id`, Local/OwnerKey=`id`)
  - `dispatchedBy()`: **BelongsTo** -> `User` (FK=`dispatched_by`, Local/OwnerKey=`id`)
  - `evidenceFile()`: **BelongsTo** -> `FileObject` (FK=`evidence_file_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `DispatchMethod`
- **(1) Table & PK:** table=`dispatch_methods`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[code, name, description, active]`
- **(3) Casts:** `active`: `boolean`
- **(4) Relationships:** None
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `OfficeProcedureVersion`
- **(1) Table & PK:** table=`office_procedure_versions`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[unit_id, correspondence_type_id, purpose_code, version_number, receipt_mode, registry_unit_id, issue_mode, workflow_version_id, confirmed_by, confirmation_source, confirmed_at, effective_from, effective_until]`
- **(3) Casts:** `version_number`: `integer`, `confirmed_at`: `datetime`, `effective_from`: `datetime`, `effective_until`: `datetime`
- **(4) Relationships (5):**
  - `unit()`: **BelongsTo** -> `OrganizationUnit` (FK=`unit_id`, Local/OwnerKey=`id`)
  - `correspondenceType()`: **BelongsTo** -> `CorrespondenceType` (FK=`correspondence_type_id`, Local/OwnerKey=`id`)
  - `registryUnit()`: **BelongsTo** -> `OrganizationUnit` (FK=`registry_unit_id`, Local/OwnerKey=`id`)
  - `workflowVersion()`: **BelongsTo** -> `WorkflowVersion` (FK=`workflow_version_id`, Local/OwnerKey=`id`)
  - `confirmedBy()`: **BelongsTo** -> `User` (FK=`confirmed_by`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `NumberingPolicy`
- **(1) Table & PK:** table=`numbering_policies`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[code, name, prefix, include_unit_code, include_category_code, year_format, sequence_length, reset_policy, active]`
- **(3) Casts:** `include_unit_code`: `boolean`, `include_category_code`: `boolean`, `sequence_length`: `integer`, `active`: `boolean`
- **(4) Relationships:** None
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified
