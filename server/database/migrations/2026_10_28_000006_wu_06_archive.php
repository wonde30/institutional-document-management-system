<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // MySQL DDL is not a multi-statement transaction. See partial-failure recovery runbook.
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `archive_collections` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` BIGINT UNSIGNED NULL,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `archive_collections_uq_1` (`code`),
  KEY `archive_collections_ix_1` (`parent_id`),
  CONSTRAINT `archive_collections_fk_1` FOREIGN KEY (`parent_id`) REFERENCES `archive_collections` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `retention_policies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `description` TEXT NULL,
  `active` BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (`id`),
  UNIQUE KEY `retention_policies_uq_1` (`code`),
  CONSTRAINT `retention_policies_ck_1` CHECK (active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `retention_policy_versions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `policy_id` BIGINT UNSIGNED NOT NULL,
  `version_number` INT UNSIGNED NOT NULL,
  `trigger_code` VARCHAR(64) NOT NULL,
  `duration_months` INT UNSIGNED NULL,
  `disposition_action` VARCHAR(16) NOT NULL,
  `approved_by` BIGINT UNSIGNED NULL,
  `approved_at` DATETIME(6) NULL,
  `effective_from` DATE NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `retention_policy_versions_uq_1` (`policy_id`, `version_number`),
  KEY `retention_policy_versions_ix_1` (`approved_by`),
  CONSTRAINT `retention_policy_versions_fk_1` FOREIGN KEY (`policy_id`) REFERENCES `retention_policies` (`id`),
  CONSTRAINT `retention_policy_versions_fk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  CONSTRAINT `retention_policy_versions_ck_1` CHECK (version_number > 0),
  CONSTRAINT `retention_policy_versions_ck_2` CHECK (disposition_action IN ('review','transfer','retain','destroy')),
  CONSTRAINT `retention_policy_versions_ck_3` CHECK ((approved_by IS NULL AND approved_at IS NULL) OR (approved_by IS NOT NULL AND approved_at IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `archive_entries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `collection_id` BIGINT UNSIGNED NOT NULL,
  `retention_policy_version_id` BIGINT UNSIGNED NULL,
  `archived_by` BIGINT UNSIGNED NOT NULL,
  `archived_at` DATETIME(6) NOT NULL,
  `trigger_occurred_at` DATETIME(6) NULL,
  `next_review_at` DATETIME(6) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `archive_entries_uq_1` (`document_id`),
  KEY `archive_entries_ix_1` (`collection_id`, `archived_at`),
  KEY `archive_entries_ix_2` (`next_review_at`),
  KEY `archive_entries_ix_3` (`retention_policy_version_id`),
  KEY `archive_entries_ix_4` (`archived_by`),
  CONSTRAINT `archive_entries_fk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`),
  CONSTRAINT `archive_entries_fk_2` FOREIGN KEY (`collection_id`) REFERENCES `archive_collections` (`id`),
  CONSTRAINT `archive_entries_fk_3` FOREIGN KEY (`retention_policy_version_id`) REFERENCES `retention_policy_versions` (`id`),
  CONSTRAINT `archive_entries_fk_4` FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `archive_actions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `archive_entry_id` BIGINT UNSIGNED NOT NULL,
  `action` VARCHAR(32) NOT NULL,
  `actor_id` BIGINT UNSIGNED NOT NULL,
  `occurred_at` DATETIME(6) NOT NULL,
  `reason` TEXT NOT NULL,
  `evidence_document_id` BIGINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `archive_actions_ix_1` (`archive_entry_id`, `occurred_at`, `id`),
  KEY `archive_actions_ix_2` (`actor_id`),
  KEY `archive_actions_ix_3` (`evidence_document_id`),
  CONSTRAINT `archive_actions_fk_1` FOREIGN KEY (`archive_entry_id`) REFERENCES `archive_entries` (`id`),
  CONSTRAINT `archive_actions_fk_2` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`),
  CONSTRAINT `archive_actions_fk_3` FOREIGN KEY (`evidence_document_id`) REFERENCES `documents` (`id`),
  CONSTRAINT `archive_actions_ck_1` CHECK (action IN ('transfer_in','review','restore','transfer_out','disposition_executed'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `legal_holds` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `reason` TEXT NOT NULL,
  `placed_by` BIGINT UNSIGNED NOT NULL,
  `placed_at` DATETIME(6) NOT NULL,
  `released_by` BIGINT UNSIGNED NULL,
  `released_at` DATETIME(6) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `legal_holds_uq_1` (`code`),
  KEY `legal_holds_ix_1` (`placed_by`),
  KEY `legal_holds_ix_2` (`released_by`),
  CONSTRAINT `legal_holds_fk_1` FOREIGN KEY (`placed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `legal_holds_fk_2` FOREIGN KEY (`released_by`) REFERENCES `users` (`id`),
  CONSTRAINT `legal_holds_ck_1` CHECK ((released_by IS NULL AND released_at IS NULL) OR (released_by IS NOT NULL AND released_at IS NOT NULL AND released_at >= placed_at))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `legal_hold_documents` (
  `hold_id` BIGINT UNSIGNED NOT NULL,
  `document_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`hold_id`, `document_id`),
  KEY `legal_hold_documents_ix_1` (`document_id`),
  CONSTRAINT `legal_hold_documents_fk_1` FOREIGN KEY (`hold_id`) REFERENCES `legal_holds` (`id`),
  CONSTRAINT `legal_hold_documents_fk_2` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `disposition_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `archive_entry_id` BIGINT UNSIGNED NOT NULL,
  `requested_by` BIGINT UNSIGNED NOT NULL,
  `requested_action` VARCHAR(24) NOT NULL,
  `requested_at` DATETIME(6) NOT NULL,
  `justification` TEXT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `disposition_requests_ix_1` (`archive_entry_id`, `requested_at`),
  KEY `disposition_requests_ix_2` (`requested_by`),
  CONSTRAINT `disposition_requests_fk_1` FOREIGN KEY (`archive_entry_id`) REFERENCES `archive_entries` (`id`),
  CONSTRAINT `disposition_requests_fk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  CONSTRAINT `disposition_requests_ck_1` CHECK (requested_action IN ('transfer','destroy_digital','destroy_physical'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `disposition_decisions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` BIGINT UNSIGNED NOT NULL,
  `decided_by` BIGINT UNSIGNED NOT NULL,
  `decision` VARCHAR(16) NOT NULL,
  `reason` TEXT NOT NULL,
  `decided_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `disposition_decisions_uq_1` (`request_id`),
  KEY `disposition_decisions_ix_1` (`decided_by`),
  CONSTRAINT `disposition_decisions_fk_1` FOREIGN KEY (`request_id`) REFERENCES `disposition_requests` (`id`),
  CONSTRAINT `disposition_decisions_fk_2` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`),
  CONSTRAINT `disposition_decisions_ck_1` CHECK (decision IN ('approve','reject'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `disposition_executions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` BIGINT UNSIGNED NOT NULL,
  `executed_by` BIGINT UNSIGNED NOT NULL,
  `executed_at` DATETIME(6) NOT NULL,
  `evidence_document_id` BIGINT UNSIGNED NULL,
  `external_receipt` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `disposition_executions_uq_1` (`request_id`),
  KEY `disposition_executions_ix_1` (`executed_by`),
  KEY `disposition_executions_ix_2` (`evidence_document_id`),
  CONSTRAINT `disposition_executions_fk_1` FOREIGN KEY (`request_id`) REFERENCES `disposition_requests` (`id`),
  CONSTRAINT `disposition_executions_fk_2` FOREIGN KEY (`executed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `disposition_executions_fk_3` FOREIGN KEY (`evidence_document_id`) REFERENCES `documents` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `file_integrity_checks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `file_object_id` BIGINT UNSIGNED NOT NULL,
  `checked_at` DATETIME(6) NOT NULL,
  `observed_sha256` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  `outcome` VARCHAR(16) NOT NULL,
  `error_code` VARCHAR(100) NULL,
  PRIMARY KEY (`id`),
  KEY `file_integrity_checks_ix_1` (`file_object_id`, `checked_at`),
  CONSTRAINT `file_integrity_checks_fk_1` FOREIGN KEY (`file_object_id`) REFERENCES `file_objects` (`id`),
  CONSTRAINT `file_integrity_checks_ck_1` CHECK (outcome IN ('match','mismatch','missing','error'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );
    }
    public function down(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Destructive baseline rollback is restricted to local/testing.');
        }
        Schema::drop('file_integrity_checks');
        Schema::drop('disposition_executions');
        Schema::drop('disposition_decisions');
        Schema::drop('disposition_requests');
        Schema::drop('legal_hold_documents');
        Schema::drop('legal_holds');
        Schema::drop('archive_actions');
        Schema::drop('archive_entries');
        Schema::drop('retention_policy_versions');
        Schema::drop('retention_policies');
        Schema::drop('archive_collections');
    }
};
