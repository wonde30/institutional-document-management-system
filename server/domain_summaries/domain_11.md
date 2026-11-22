## Domain 11: Audit, Notifications, System Operations & Transfer (17 Models)

### Model: `AuditEvent`
- **(1) Table & PK:** table=`audit_events`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[actor_id, action, target_type, target_id, request_id, occurred_at, ip_address, user_agent, details_json]`
- **(3) Casts:** `occurred_at`: `datetime`, `details_json`: `array`
- **(4) Relationships (1):**
  - `actor()`: **BelongsTo** -> `User` (FK=`actor_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** methods: `[booted]`; events: `[updating, deleting]`
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `NotificationEvent`
- **(1) Table & PK:** table=`notification_events`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[type_code, document_id, actor_id, occurred_at]`
- **(3) Casts:** `occurred_at`: `datetime`
- **(4) Relationships (2):**
  - `document()`: **BelongsTo** -> `Document` (FK=`document_id`, Local/OwnerKey=`id`)
  - `actor()`: **BelongsTo** -> `User` (FK=`actor_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `NotificationDelivery`
- **(1) Table & PK:** table=`notification_deliveries`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[notification_id, channel, status, attempt_count, delivered_at, last_error_code]`
- **(3) Casts:** `attempt_count`: `integer`, `delivered_at`: `datetime`
- **(4) Relationships (1):**
  - `notification()`: **BelongsTo** -> `UserNotification` (FK=`notification_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `UserNotification`
- **(1) Table & PK:** table=`user_notifications`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[event_id, user_id, read_at, created_at]`
- **(3) Casts:** `read_at`: `datetime`, `created_at`: `datetime`
- **(4) Relationships (2):**
  - `event()`: **BelongsTo** -> `NotificationEvent` (FK=`event_id`, Local/OwnerKey=`id`)
  - `user()`: **BelongsTo** -> `User` (FK=`user_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `ExportJob`
- **(1) Table & PK:** table=`export_jobs`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[requested_by, report_type, filter_snapshot_json, status, output_file_id, requested_at, completed_at, expires_at, error_code]`
- **(3) Casts:** `filter_snapshot_json`: `array`, `requested_at`: `datetime`, `completed_at`: `datetime`, `expires_at`: `datetime`
- **(4) Relationships (2):**
  - `requestedBy()`: **BelongsTo** -> `User` (FK=`requested_by`, Local/OwnerKey=`id`)
  - `outputFile()`: **BelongsTo** -> `FileObject` (FK=`output_file_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `ImportBatch`
- **(1) Table & PK:** table=`import_batches`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[source_label, initiated_by, status, started_at, completed_at]`
- **(3) Casts:** `started_at`: `datetime`, `completed_at`: `datetime`
- **(4) Relationships (1):**
  - `initiatedBy()`: **BelongsTo** -> `User` (FK=`initiated_by`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `ImportRow`
- **(1) Table & PK:** table=`import_rows`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[batch_id, source_row_key, target_document_id, status, validation_errors_json]`
- **(3) Casts:** `validation_errors_json`: `array`
- **(4) Relationships (2):**
  - `batch()`: **BelongsTo** -> `ImportBatch` (FK=`batch_id`, Local/OwnerKey=`id`)
  - `targetDocument()`: **BelongsTo** -> `Document` (FK=`target_document_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `BackupRun`
- **(1) Table & PK:** table=`backup_runs`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[provider_job_reference, started_at, completed_at, status, manifest_reference, verification_status]`
- **(3) Casts:** `started_at`: `datetime`, `completed_at`: `datetime`
- **(4) Relationships:** None
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `RestoreDrill`
- **(1) Table & PK:** table=`restore_drills`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[backup_run_id, started_at, completed_at, status, notes]`
- **(3) Casts:** `started_at`: `datetime`, `completed_at`: `datetime`
- **(4) Relationships (1):**
  - `backupRun()`: **BelongsTo** -> `BackupRun` (FK=`backup_run_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `IdempotencyRecord`
- **(1) Table & PK:** table=`idempotency_records`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[user_id, operation_key, idempotency_key, request_hash, result_reference, expires_at]`
- **(3) Casts:** `created_at`: `datetime`, `expires_at`: `datetime`
- **(4) Relationships (1):**
  - `user()`: **BelongsTo** -> `User` (FK=`user_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `DataExportOperation`
- **(1) Table & PK:** table=`data_export_operations`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[dataset, status, format, storage_path, file_size, record_count, filters, scope_unit_id, created_by, failure_reason, started_at, completed_at, expires_at]`
- **(3) Casts:** `filters`: `array`, `file_size`: `integer`, `record_count`: `integer`, `scope_unit_id`: `integer`, `created_by`: `integer`, `started_at`: `datetime`, `completed_at`: `datetime`, `expires_at`: `datetime`
- **(4) Relationships (1):**
  - `creator()`: **BelongsTo** -> `User` (FK=`created_by`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `DataImportOperation`
- **(1) Table & PK:** table=`data_import_operations`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[dataset, status, format, original_filename, storage_path, scope_unit_id, created_by, total_rows, valid_rows, invalid_rows, duplicate_rows, imported_rows, failed_rows, failure_reason, started_at, completed_at]`
- **(3) Casts:** `scope_unit_id`: `integer`, `created_by`: `integer`, `total_rows`: `integer`, `valid_rows`: `integer`, `invalid_rows`: `integer`, `duplicate_rows`: `integer`, `imported_rows`: `integer`, `failed_rows`: `integer`, `started_at`: `datetime`, `completed_at`: `datetime`
- **(4) Relationships (2):**
  - `creator()`: **BelongsTo** -> `User` (FK=`created_by`, Local/OwnerKey=`id`)
  - `rowErrors()`: **HasMany** -> `DataImportRowError` (FK=`operation_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `DataImportRowError`
- **(1) Table & PK:** table=`data_import_row_errors`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[operation_id, row_number, column_name, code, message]`
- **(3) Casts:** `operation_id`: `integer`, `row_number`: `integer`
- **(4) Relationships (1):**
  - `operation()`: **BelongsTo** -> `DataImportOperation` (FK=`operation_id`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `SystemSetting`
- **(1) Table & PK:** table=`system_settings`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[setting_key, scope_unit_id, scope_key, value_type, string_value, integer_value, boolean_value, updated_by]`
- **(3) Casts:** `scope_unit_id`: `integer`, `scope_key`: `integer`, `integer_value`: `integer`, `boolean_value`: `boolean`, `updated_by`: `integer`
- **(4) Relationships (1):**
  - `updatedBy()`: **BelongsTo** -> `User` (FK=`updated_by`, Local/OwnerKey=`id`)
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `ReferenceSequence`
- **(1) Table & PK:** table=`reference_sequences`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[sequence_key, period_key, next_value]`
- **(3) Casts:** none (except default PK id: int)
- **(4) Relationships:** None
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `Institution`
- **(1) Table & PK:** table=`institutions`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[code, name, description, active]`
- **(3) Casts:** `active`: `boolean`
- **(4) Relationships:** None
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified

### Model: `OutboxEvent`
- **(1) Table & PK:** table=`outbox_events`, PK=`id`, incrementing=`True`, keyType=`int`
- **(2) Fillable:** `[event_type, aggregate_type, aggregate_id, payload_json, published_at, attempt_count]`
- **(3) Casts:** `payload_json`: `array`, `created_at`: `datetime`, `published_at`: `datetime`, `attempt_count`: `integer`
- **(4) Relationships:** None
- **(5) SoftDeletes:** No
- **(6) Eager Loading ($with):** None
- **(7) Scopes:** None
- **(8) Boot / Events:** None
- **(9) Optimistic Locking (lock_version):** None
- **(10) N+1 Risks / Custom Query Methods:** None identified
