## Domain 2: Repository, Core Documents & Files (21 Models)

### Model: `Document`
- **(1) Table & PK:** table=`documents`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[reference_number, title, summary, category_id, confidentiality_id, owner_unit_id, custodian_id, created_by, document_date, registered_at, lifecycle, lock_version]`
- **(3) Casts:** `document_date`: `date`, `registered_at`: `datetime`, `lock_version`: `integer`, `created_at`: `datetime`, `updated_at`: `datetime`
- **(4) Relationships (23):**
  - `category()`: **BelongsTo** -> `DocumentCategory` (FK=`category_id`, Local/OwnerKey=`id`)
  - `confidentiality()`: **BelongsTo** -> `ConfidentialityLevel` (FK=`confidentiality_id`, Local/OwnerKey=`id`)
  - `ownerUnit()`: **BelongsTo** -> `OrganizationUnit` (FK=`owner_unit_id`, Local/OwnerKey=`id`)
  - `custodian()`: **BelongsTo** -> `User` (FK=`custodian_id`, Local/OwnerKey=`id`)
  - `creator()`: **BelongsTo** -> `User` (FK=`created_by`, Local/OwnerKey=`id`)
  - `versions()`: **HasMany** -> `DocumentVersion` (FK=`document_id`, Local/OwnerKey=`id`)
  - `currentVersion()`: **HasOne** -> `DocumentVersion` (FK=`document_id`, Local/OwnerKey=`id`)
  - `tags()`: **BelongsToMany** -> `Tag` (FK=`document_id`, Local/OwnerKey=`tag_id`, pivot=`document_tags`)
  - `outgoingRelations()`: **HasMany** -> `DocumentRelation` (FK=`source_id`, Local/OwnerKey=`id`)
  - `relations()`: **HasMany** -> `DocumentRelation` (FK=`source_id`, Local/OwnerKey=`id`)
  - `incomingRelations()`: **HasMany** -> `DocumentRelation` (FK=`target_id`, Local/OwnerKey=`id`)
  - `relatedDocuments()`: **BelongsToMany** -> `Document` (FK=`source_id`, Local/OwnerKey=`target_id`, pivot=`document_relations`)
  - `grants()`: **HasMany** -> `DocumentGrant` (FK=`document_id`, Local/OwnerKey=`id`)
  - `comments()`: **HasMany** -> `DocumentComment` (FK=`document_id`, Local/OwnerKey=`id`)
  - `releaseEvents()`: **HasMany** -> `DocumentReleaseEvent` (FK=`document_id`, Local/OwnerKey=`id`)
  - `correspondence()`: **HasOne** -> `Correspondence` (FK=`document_id`, Local/OwnerKey=`id`)
  - `archiveEntry()`: **HasOne** -> `ArchiveEntry` (FK=`document_id`, Local/OwnerKey=`id`)
  - `movements()`: **HasMany** -> `DocumentMovement` (FK=`document_id`, Local/OwnerKey=`id`)
  - `assignments()`: **HasMany** -> `DocumentAssignment` (FK=`document_id`, Local/OwnerKey=`id`)
  - `physicalOriginals()`: **HasMany** -> `PhysicalOriginal` (FK=`document_id`, Local/OwnerKey=`id`)
  - `legalHolds()`: **BelongsToMany** -> `LegalHold` (FK=`document_id`, Local/OwnerKey=`hold_id`, pivot=`legal_hold_documents`)
  - `assignmentResponses()`: **HasMany** -> `AssignmentResponse` (FK=`response_document_id`, Local/OwnerKey=`id`)
  - `fulfilledAssignments()`: **BelongsToMany** -> `DocumentAssignment` (FK=`response_document_id`, Local/OwnerKey=`assignment_id`, pivot=`assignment_responses`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** `accessibleBy` (local)
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** YES (`in $fillable, in $casts (integer)`)
- **(10) N+1 Risks / Custom Query Methods:**
  - WARNING: `allowsUser`: DB:: queries, loops over relation 'confidentiality', loops over relation 'custodian'

### Model: `DocumentCategory`
- **(1) Table & PK:** table=`document_categories`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[code, name, description, active]`
- **(3) Casts:** `active`: `boolean`
- **(4) Relationships:** None
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `ConfidentialityLevel`
- **(1) Table & PK:** table=`confidentiality_levels`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[code, name, description, active]`
- **(3) Casts:** `active`: `boolean`
- **(4) Relationships:** None
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `DocumentVersion`
- **(1) Table & PK:** table=`document_versions`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[document_id, version_number, ingestion_method, template_version_id, template_data, created_by, change_note, created_at]`
- **(3) Casts:** `version_number`: `integer`, `template_data`: `array`, `created_at`: `datetime`
- **(4) Relationships (7):**
  - `document()`: **BelongsTo** -> `Document` (FK=`document_id`, Local/OwnerKey=`id`)
  - `templateVersion()`: **BelongsTo** -> `TemplateVersion` (FK=`template_version_id`, Local/OwnerKey=`id`)
  - `createdBy()`: **BelongsTo** -> `User` (FK=`created_by`, Local/OwnerKey=`id`)
  - `versionFiles()`: **HasMany** -> `VersionFile` (FK=`document_version_id`, Local/OwnerKey=`id`)
  - `fileObjects()`: **BelongsToMany** -> `FileObject` (FK=`document_version_id`, Local/OwnerKey=`file_object_id`, pivot=`version_files`)
  - `textExtractions()`: **HasMany** -> `DocumentTextExtraction` (FK=`document_version_id`, Local/OwnerKey=`id`)
  - `workflowInstances()`: **HasMany** -> `WorkflowInstance` (FK=`document_version_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** methods: `[booted]`; events: `[creating]`; uses `GuardsVersionImmutability` (updating/deleting events throw RuntimeException unless depth > 0)
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `VersionFile`
- **(1) Table & PK:** table=`version_files`, PK=`id`, incrementing=`False`, keyType=`int` | Composite PK: `['document_version_id', 'file_object_id']`
- **(2) Fillable:** `[document_version_id, file_object_id, purpose, display_order]`
- **(3) Casts:** `display_order`: `integer`, `primary_slot`: `integer`
- **(4) Relationships (2):**
  - `documentVersion()`: **BelongsTo** -> `DocumentVersion` (FK=`document_version_id`, Local/OwnerKey=`id`)
  - `fileObject()`: **BelongsTo** -> `FileObject` (FK=`file_object_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** uses `GuardsVersionImmutability` (updating/deleting events throw RuntimeException unless depth > 0)
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `FileObject`
- **(1) Table & PK:** table=`file_objects`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[storage_disk, storage_key, original_filename, detected_mime, byte_size, sha256, uploaded_by, uploaded_at, scan_status, scanned_at, storage_status]`
- **(3) Casts:** `uploaded_at`: `datetime`, `scanned_at`: `datetime`
- **(4) Relationships (1):**
  - `uploadedBy()`: **BelongsTo** -> `User` (FK=`uploaded_by`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `DocumentRelation`
- **(1) Table & PK:** table=`document_relations`, PK=`id`, incrementing=`False`, keyType=`int`
- **(2) Fillable:** `[source_id, target_id, relation_type_id]`
- **(3) Casts:** none (except default PK id: int)
- **(4) Relationships (5):**
  - `source()`: **BelongsTo** -> `Document` (FK=`source_id`, Local/OwnerKey=`id`)
  - `target()`: **BelongsTo** -> `Document` (FK=`target_id`, Local/OwnerKey=`id`)
  - `sourceDocument()`: **BelongsTo** -> `Document` (FK=`source_id`, Local/OwnerKey=`id`)
  - `targetDocument()`: **BelongsTo** -> `Document` (FK=`target_id`, Local/OwnerKey=`id`)
  - `relationType()`: **BelongsTo** -> `DocumentRelationType` (FK=`relation_type_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `DocumentRelationType`
- **(1) Table & PK:** table=`document_relation_types`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[code, name, description, active]`
- **(3) Casts:** `active`: `boolean`
- **(4) Relationships:** None
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `DocumentTag`
- **(1) Table & PK:** table=`document_tags`, PK=`id`, incrementing=`False`, keyType=`int`
- **(2) Fillable:** `[document_id, tag_id]`
- **(3) Casts:** none (except default PK id: int)
- **(4) Relationships (2):**
  - `document()`: **BelongsTo** -> `Document` (FK=`document_id`, Local/OwnerKey=`id`)
  - `tag()`: **BelongsTo** -> `Tag` (FK=`tag_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `Tag`
- **(1) Table & PK:** table=`tags`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[normalized_name, display_name]`
- **(3) Casts:** none (except default PK id: int)
- **(4) Relationships:** None
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `DocumentComment`
- **(1) Table & PK:** table=`document_comments`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[document_id, parent_id, author_id, body, created_at, edited_at]`
- **(3) Casts:** `created_at`: `datetime`, `edited_at`: `datetime`
- **(4) Relationships (3):**
  - `document()`: **BelongsTo** -> `Document` (FK=`document_id`, Local/OwnerKey=`id`)
  - `parent()`: **BelongsTo** -> `DocumentComment` (FK=`parent_id`, Local/OwnerKey=`id`)
  - `author()`: **BelongsTo** -> `User` (FK=`author_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `DocumentGrant`
- **(1) Table & PK:** table=`document_grants`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[document_id, permission_id, user_id, unit_id, subject_key, granted_by, granted_at, expires_at]`
- **(3) Casts:** `granted_at`: `datetime`, `expires_at`: `datetime`
- **(4) Relationships (5):**
  - `document()`: **BelongsTo** -> `Document` (FK=`document_id`, Local/OwnerKey=`id`)
  - `permission()`: **BelongsTo** -> `Permission` (FK=`permission_id`, Local/OwnerKey=`id`)
  - `user()`: **BelongsTo** -> `User` (FK=`user_id`, Local/OwnerKey=`id`)
  - `unit()`: **BelongsTo** -> `OrganizationUnit` (FK=`unit_id`, Local/OwnerKey=`id`)
  - `grantedBy()`: **BelongsTo** -> `User` (FK=`granted_by`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `DocumentMovement`
- **(1) Table & PK:** table=`document_movements`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[document_id, from_unit_id, to_unit_id, sent_by, sent_at, instruction, due_at, idempotency_key]`
- **(3) Casts:** `sent_at`: `datetime`, `due_at`: `datetime`
- **(4) Relationships (5):**
  - `document()`: **BelongsTo** -> `Document` (FK=`document_id`, Local/OwnerKey=`id`)
  - `fromUnit()`: **BelongsTo** -> `OrganizationUnit` (FK=`from_unit_id`, Local/OwnerKey=`id`)
  - `toUnit()`: **BelongsTo** -> `OrganizationUnit` (FK=`to_unit_id`, Local/OwnerKey=`id`)
  - `sentBy()`: **BelongsTo** -> `User` (FK=`sent_by`, Local/OwnerKey=`id`)
  - `receipt()`: **HasOne** -> `MovementReceipt` (FK=`movement_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `MovementReceipt`
- **(1) Table & PK:** table=`movement_receipts`, PK=`movement_id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[movement_id, acknowledged_by, acknowledged_at]`
- **(3) Casts:** `movement_id`: `int`, `acknowledged_at`: `datetime`
- **(4) Relationships (2):**
  - `movement()`: **BelongsTo** -> `DocumentMovement` (FK=`movement_id`, Local/OwnerKey=`id`)
  - `acknowledgedBy()`: **BelongsTo** -> `User` (FK=`acknowledged_by`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `DocumentReleaseEvent`
- **(1) Table & PK:** table=`document_release_events`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[document_version_id, action, actor_id, reason, occurred_at]`
- **(3) Casts:** `occurred_at`: `datetime`
- **(4) Relationships (2):**
  - `documentVersion()`: **BelongsTo** -> `DocumentVersion` (FK=`document_version_id`, Local/OwnerKey=`id`)
  - `actor()`: **BelongsTo** -> `User` (FK=`actor_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `DocumentTextExtraction`
- **(1) Table & PK:** table=`document_text_extractions`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[document_version_id, file_object_id, raw_text, verified_text, searchable_text, language_detected, confidence_score, ocr_engine, extracted_at, verified_by, verified_at]`
- **(3) Casts:** `confidence_score`: `decimal:2`, `extracted_at`: `datetime`, `verified_at`: `datetime`
- **(4) Relationships (3):**
  - `documentVersion()`: **BelongsTo** -> `DocumentVersion` (FK=`document_version_id`, Local/OwnerKey=`id`)
  - `fileObject()`: **BelongsTo** -> `FileObject` (FK=`file_object_id`, Local/OwnerKey=`id`)
  - `verifiedBy()`: **BelongsTo** -> `User` (FK=`verified_by`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** methods: `[booted]`; events: `[creating, updating]`
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `DocumentTemplate`
- **(1) Table & PK:** table=`document_templates`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[code, name, description, category_id, owner_unit_id, visibility_scope, is_active, created_by]`
- **(3) Casts:** `is_active`: `boolean`, `created_at`: `datetime`, `updated_at`: `datetime`
- **(4) Relationships (6):**
  - `category()`: **BelongsTo** -> `DocumentCategory` (FK=`category_id`, Local/OwnerKey=`id`)
  - `ownerUnit()`: **BelongsTo** -> `OrganizationUnit` (FK=`owner_unit_id`, Local/OwnerKey=`id`)
  - `creator()`: **BelongsTo** -> `User` (FK=`created_by`, Local/OwnerKey=`id`)
  - `versions()`: **HasMany** -> `TemplateVersion` (FK=`template_id`, Local/OwnerKey=`id`)
  - `currentVersion()`: **HasOne** -> `TemplateVersion` (FK=`template_id`, Local/OwnerKey=`id`)
  - `publishedVersion()`: **HasOne** -> `TemplateVersion` (FK=`template_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `TemplateVersion`
- **(1) Table & PK:** table=`template_versions`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[template_id, version_number, schema_definition, body_template_html, header_asset_key, footer_asset_key, status, published_at, published_by, created_by, created_at]`
- **(3) Casts:** `version_number`: `integer`, `schema_definition`: `array`, `published_at`: `datetime`, `created_at`: `datetime`
- **(4) Relationships (4):**
  - `template()`: **BelongsTo** -> `DocumentTemplate` (FK=`template_id`, Local/OwnerKey=`id`)
  - `publisher()`: **BelongsTo** -> `User` (FK=`published_by`, Local/OwnerKey=`id`)
  - `creator()`: **BelongsTo** -> `User` (FK=`created_by`, Local/OwnerKey=`id`)
  - `documentVersions()`: **HasMany** -> `DocumentVersion` (FK=`template_version_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** methods: `[booted]`; events: `[creating]`
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `FileDerivative`
- **(1) Table & PK:** table=`file_derivatives`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[source_file_id, result_file_id, kind, generator_version, generated_at]`
- **(3) Casts:** `generated_at`: `datetime`
- **(4) Relationships (2):**
  - `sourceFile()`: **BelongsTo** -> `FileObject` (FK=`source_file_id`, Local/OwnerKey=`id`)
  - `resultFile()`: **BelongsTo** -> `FileObject` (FK=`result_file_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `FileIntegrityCheck`
- **(1) Table & PK:** table=`file_integrity_checks`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[file_object_id, checked_at, observed_sha256, outcome, error_code]`
- **(3) Casts:** `checked_at`: `datetime`
- **(4) Relationships (1):**
  - `fileObject()`: **BelongsTo** -> `FileObject` (FK=`file_object_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `DigitizationRecord`
- **(1) Table & PK:** table=`digitization_records`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[physical_original_id, document_version_id, digitized_by, digitized_at, verification_status, verified_by, verified_at, verification_note]`
- **(3) Casts:** `digitized_at`: `datetime`, `verified_at`: `datetime`
- **(4) Relationships (4):**
  - `physicalOriginal()`: **BelongsTo** -> `PhysicalOriginal` (FK=`physical_original_id`, Local/OwnerKey=`id`)
  - `documentVersion()`: **BelongsTo** -> `DocumentVersion` (FK=`document_version_id`, Local/OwnerKey=`id`)
  - `digitizedBy()`: **BelongsTo** -> `User` (FK=`digitized_by`, Local/OwnerKey=`id`)
  - `verifiedBy()`: **BelongsTo** -> `User` (FK=`verified_by`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified
