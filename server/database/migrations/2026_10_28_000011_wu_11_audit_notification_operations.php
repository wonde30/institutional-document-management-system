<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // MySQL DDL is not a multi-statement transaction. See partial-failure recovery runbook.
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `audit_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `actor_id` BIGINT UNSIGNED NULL,
  `action` VARCHAR(100) NOT NULL,
  `target_type` VARCHAR(100) NOT NULL,
  `target_id` VARCHAR(100) NOT NULL,
  `request_id` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `occurred_at` DATETIME(6) NOT NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(500) NULL,
  `details_json` JSON NULL,
  PRIMARY KEY (`id`),
  KEY `audit_events_ix_1` (`target_type`, `target_id`, `occurred_at`),
  KEY `audit_events_ix_2` (`actor_id`, `occurred_at`),
  KEY `audit_events_ix_3` (`request_id`),
  KEY `audit_events_ix_4` (`occurred_at`),
  CONSTRAINT `audit_events_fk_1` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `notification_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type_code` VARCHAR(100) NOT NULL,
  `document_id` BIGINT UNSIGNED NULL,
  `actor_id` BIGINT UNSIGNED NULL,
  `occurred_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `notification_events_ix_1` (`document_id`),
  KEY `notification_events_ix_2` (`actor_id`),
  CONSTRAINT `notification_events_fk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`),
  CONSTRAINT `notification_events_fk_2` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `user_notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `read_at` DATETIME(6) NULL,
  `created_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_notifications_uq_1` (`event_id`, `user_id`),
  KEY `user_notifications_ix_1` (`user_id`, `read_at`, `created_at`),
  CONSTRAINT `user_notifications_fk_1` FOREIGN KEY (`event_id`) REFERENCES `notification_events` (`id`),
  CONSTRAINT `user_notifications_fk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `notification_deliveries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `notification_id` BIGINT UNSIGNED NOT NULL,
  `channel` VARCHAR(32) NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'pending',
  `attempt_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `delivered_at` DATETIME(6) NULL,
  `last_error_code` VARCHAR(100) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notification_deliveries_uq_1` (`notification_id`, `channel`),
  KEY `notification_deliveries_ix_1` (`status`),
  CONSTRAINT `notification_deliveries_fk_1` FOREIGN KEY (`notification_id`) REFERENCES `user_notifications` (`id`),
  CONSTRAINT `notification_deliveries_ck_1` CHECK (status IN ('pending','delivering','delivered','failed'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `outbox_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_type` VARCHAR(100) NOT NULL,
  `aggregate_type` VARCHAR(100) NOT NULL,
  `aggregate_id` VARCHAR(100) NOT NULL,
  `payload_json` JSON NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `published_at` DATETIME(6) NULL,
  `attempt_count` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `outbox_events_ix_1` (`published_at`, `id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `idempotency_records` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `operation_key` VARCHAR(100) NOT NULL,
  `idempotency_key` VARCHAR(100) NOT NULL,
  `request_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `result_reference` VARCHAR(255) NULL,
  `created_at` DATETIME(6) NOT NULL,
  `expires_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idempotency_records_uq_1` (`user_id`, `operation_key`, `idempotency_key`),
  KEY `idempotency_records_ix_1` (`expires_at`),
  CONSTRAINT `idempotency_records_fk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `idempotency_records_ck_1` CHECK (expires_at > created_at)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `export_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `requested_by` BIGINT UNSIGNED NOT NULL,
  `report_type` VARCHAR(100) NOT NULL,
  `filter_snapshot_json` JSON NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'pending',
  `output_file_id` BIGINT UNSIGNED NULL,
  `requested_at` DATETIME(6) NOT NULL,
  `completed_at` DATETIME(6) NULL,
  `expires_at` DATETIME(6) NULL,
  `error_code` VARCHAR(100) NULL,
  PRIMARY KEY (`id`),
  KEY `export_jobs_ix_1` (`requested_by`, `requested_at`),
  KEY `export_jobs_ix_2` (`status`, `requested_at`),
  KEY `export_jobs_ix_3` (`output_file_id`),
  CONSTRAINT `export_jobs_fk_1` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  CONSTRAINT `export_jobs_fk_2` FOREIGN KEY (`output_file_id`) REFERENCES `file_objects` (`id`),
  CONSTRAINT `export_jobs_ck_1` CHECK (status IN ('pending','running','completed','failed','expired'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `import_batches` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source_label` VARCHAR(255) NOT NULL,
  `initiated_by` BIGINT UNSIGNED NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'pending',
  `started_at` DATETIME(6) NULL,
  `completed_at` DATETIME(6) NULL,
  PRIMARY KEY (`id`),
  KEY `import_batches_ix_1` (`initiated_by`),
  CONSTRAINT `import_batches_fk_1` FOREIGN KEY (`initiated_by`) REFERENCES `users` (`id`),
  CONSTRAINT `import_batches_ck_1` CHECK (status IN ('pending','running','completed','failed','cancelled'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `import_rows` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `batch_id` BIGINT UNSIGNED NOT NULL,
  `source_row_key` VARCHAR(191) NOT NULL,
  `target_document_id` BIGINT UNSIGNED NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'pending',
  `validation_errors_json` JSON NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `import_rows_uq_1` (`batch_id`, `source_row_key`),
  KEY `import_rows_ix_1` (`target_document_id`),
  CONSTRAINT `import_rows_fk_1` FOREIGN KEY (`batch_id`) REFERENCES `import_batches` (`id`),
  CONSTRAINT `import_rows_fk_2` FOREIGN KEY (`target_document_id`) REFERENCES `documents` (`id`),
  CONSTRAINT `import_rows_ck_1` CHECK (status IN ('pending','imported','skipped','rejected'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `backup_runs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider_job_reference` VARCHAR(191) NULL,
  `started_at` DATETIME(6) NOT NULL,
  `completed_at` DATETIME(6) NULL,
  `status` VARCHAR(16) NOT NULL,
  `manifest_reference` VARCHAR(255) NULL,
  `verification_status` VARCHAR(16) NOT NULL DEFAULT 'unverified',
  PRIMARY KEY (`id`),
  KEY `backup_runs_ix_1` (`started_at`),
  CONSTRAINT `backup_runs_ck_1` CHECK (status IN ('running','completed','failed')),
  CONSTRAINT `backup_runs_ck_2` CHECK (verification_status IN ('unverified','verified','failed'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `restore_drills` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `backup_run_id` BIGINT UNSIGNED NOT NULL,
  `started_at` DATETIME(6) NOT NULL,
  `completed_at` DATETIME(6) NULL,
  `status` VARCHAR(16) NOT NULL,
  `notes` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `restore_drills_ix_1` (`backup_run_id`),
  CONSTRAINT `restore_drills_fk_1` FOREIGN KEY (`backup_run_id`) REFERENCES `backup_runs` (`id`),
  CONSTRAINT `restore_drills_ck_1` CHECK (status IN ('running','completed','failed'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );
    }
    public function down(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Destructive baseline rollback is restricted to local/testing.');
        }
        Schema::drop('restore_drills');
        Schema::drop('backup_runs');
        Schema::drop('import_rows');
        Schema::drop('import_batches');
        Schema::drop('export_jobs');
        Schema::drop('idempotency_records');
        Schema::drop('outbox_events');
        Schema::drop('notification_deliveries');
        Schema::drop('user_notifications');
        Schema::drop('notification_events');
        Schema::drop('audit_events');
    }
};
