<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // MySQL DDL is not a multi-statement transaction. See partial-failure recovery runbook.
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `document_categories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `description` TEXT NULL,
  `active` BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_categories_uq_1` (`code`),
  CONSTRAINT `document_categories_ck_1` CHECK (active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `confidentiality_levels` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `description` TEXT NULL,
  `active` BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (`id`),
  UNIQUE KEY `confidentiality_levels_uq_1` (`code`),
  CONSTRAINT `confidentiality_levels_ck_1` CHECK (active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `reference_sequences` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sequence_key` VARCHAR(64) NOT NULL,
  `period_key` VARCHAR(32) NOT NULL,
  `next_value` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference_sequences_uq_1` (`sequence_key`, `period_key`),
  CONSTRAINT `reference_sequences_ck_1` CHECK (next_value > 0)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `documents` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reference_number` VARCHAR(160) NOT NULL,
  `title` VARCHAR(500) NOT NULL,
  `summary` TEXT NULL,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `confidentiality_id` BIGINT UNSIGNED NOT NULL,
  `owner_unit_id` BIGINT UNSIGNED NOT NULL,
  `custodian_id` BIGINT UNSIGNED NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `document_date` DATE NOT NULL,
  `registered_at` DATETIME(6) NOT NULL,
  `lifecycle` VARCHAR(16) NOT NULL DEFAULT 'active',
  `lock_version` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME(6) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `documents_uq_1` (`reference_number`),
  KEY `documents_ix_1` (`owner_unit_id`, `lifecycle`, `registered_at`),
  KEY `documents_ix_2` (`category_id`, `registered_at`),
  KEY `documents_ix_3` (`confidentiality_id`, `registered_at`),
  KEY `documents_ix_4` (`custodian_id`),
  KEY `documents_ix_5` (`created_by`),
  CONSTRAINT `documents_fk_1` FOREIGN KEY (`category_id`) REFERENCES `document_categories` (`id`),
  CONSTRAINT `documents_fk_2` FOREIGN KEY (`confidentiality_id`) REFERENCES `confidentiality_levels` (`id`),
  CONSTRAINT `documents_fk_3` FOREIGN KEY (`owner_unit_id`) REFERENCES `organization_units` (`id`),
  CONSTRAINT `documents_fk_4` FOREIGN KEY (`custodian_id`) REFERENCES `users` (`id`),
  CONSTRAINT `documents_fk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `documents_ck_1` CHECK (lifecycle IN ('active','retired','tombstoned'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `tags` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `normalized_name` VARCHAR(100) NOT NULL,
  `display_name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tags_uq_1` (`normalized_name`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `document_tags` (
  `document_id` BIGINT UNSIGNED NOT NULL,
  `tag_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`document_id`, `tag_id`),
  KEY `document_tags_ix_1` (`tag_id`),
  CONSTRAINT `document_tags_fk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`),
  CONSTRAINT `document_tags_fk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `file_objects` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `storage_disk` VARCHAR(32) NOT NULL,
  `storage_key` VARCHAR(255) NOT NULL,
  `original_filename` VARCHAR(255) NOT NULL,
  `detected_mime` VARCHAR(120) NOT NULL,
  `byte_size` BIGINT UNSIGNED NOT NULL,
  `sha256` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `uploaded_by` BIGINT UNSIGNED NOT NULL,
  `uploaded_at` DATETIME(6) NOT NULL,
  `scan_status` VARCHAR(16) NOT NULL DEFAULT 'pending',
  `scanned_at` DATETIME(6) NULL,
  `storage_status` VARCHAR(16) NOT NULL DEFAULT 'staged',
  PRIMARY KEY (`id`),
  UNIQUE KEY `file_objects_uq_1` (`storage_disk`, `storage_key`),
  KEY `file_objects_ix_1` (`sha256`),
  KEY `file_objects_ix_2` (`scan_status`, `uploaded_at`),
  KEY `file_objects_ix_3` (`uploaded_by`),
  CONSTRAINT `file_objects_fk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`),
  CONSTRAINT `file_objects_ck_1` CHECK (scan_status IN ('pending','clean','infected','error','unavailable')),
  CONSTRAINT `file_objects_ck_2` CHECK (storage_status IN ('staged','ready','missing','destroyed')),
  CONSTRAINT `file_objects_ck_3` CHECK (sha256 REGEXP '^[0-9a-f]{64}$')
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `document_versions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `version_number` INT UNSIGNED NOT NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `change_note` TEXT NULL,
  `created_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_versions_uq_1` (`document_id`, `version_number`),
  KEY `document_versions_ix_1` (`created_by`),
  CONSTRAINT `document_versions_fk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`),
  CONSTRAINT `document_versions_fk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `document_versions_ck_1` CHECK (version_number > 0)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `version_files` (
  `document_version_id` BIGINT UNSIGNED NOT NULL,
  `file_object_id` BIGINT UNSIGNED NOT NULL,
  `purpose` VARCHAR(16) NOT NULL,
  `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `primary_slot` TINYINT GENERATED ALWAYS AS (CASE WHEN purpose = 'primary' THEN 1 ELSE NULL END) STORED,
  PRIMARY KEY (`document_version_id`, `file_object_id`),
  UNIQUE KEY `version_files_uq_1` (`document_version_id`, `primary_slot`),
  UNIQUE KEY `version_files_uq_2` (`document_version_id`, `display_order`),
  KEY `version_files_ix_1` (`file_object_id`),
  CONSTRAINT `version_files_fk_1` FOREIGN KEY (`document_version_id`) REFERENCES `document_versions` (`id`),
  CONSTRAINT `version_files_fk_2` FOREIGN KEY (`file_object_id`) REFERENCES `file_objects` (`id`),
  CONSTRAINT `version_files_ck_1` CHECK (purpose IN ('primary','attachment'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `file_derivatives` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `source_file_id` BIGINT UNSIGNED NOT NULL,
  `result_file_id` BIGINT UNSIGNED NOT NULL,
  `kind` VARCHAR(32) NOT NULL,
  `generator_version` VARCHAR(64) NOT NULL,
  `generated_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `file_derivatives_uq_1` (`source_file_id`, `kind`, `generator_version`),
  UNIQUE KEY `file_derivatives_uq_2` (`result_file_id`),
  CONSTRAINT `file_derivatives_fk_1` FOREIGN KEY (`source_file_id`) REFERENCES `file_objects` (`id`),
  CONSTRAINT `file_derivatives_fk_2` FOREIGN KEY (`result_file_id`) REFERENCES `file_objects` (`id`),
  CONSTRAINT `file_derivatives_ck_1` CHECK (source_file_id <> result_file_id)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `document_release_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_version_id` BIGINT UNSIGNED NOT NULL,
  `action` VARCHAR(16) NOT NULL,
  `actor_id` BIGINT UNSIGNED NOT NULL,
  `reason` TEXT NULL,
  `occurred_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `document_release_events_ix_1` (`document_version_id`, `occurred_at`, `id`),
  KEY `document_release_events_ix_2` (`actor_id`),
  CONSTRAINT `document_release_events_fk_1` FOREIGN KEY (`document_version_id`) REFERENCES `document_versions` (`id`),
  CONSTRAINT `document_release_events_fk_2` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`),
  CONSTRAINT `document_release_events_ck_1` CHECK (action IN ('release','withdraw'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `document_relation_types` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `description` TEXT NULL,
  `active` BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_relation_types_uq_1` (`code`),
  CONSTRAINT `document_relation_types_ck_1` CHECK (active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `document_relations` (
  `source_id` BIGINT UNSIGNED NOT NULL,
  `target_id` BIGINT UNSIGNED NOT NULL,
  `relation_type_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`source_id`, `target_id`, `relation_type_id`),
  KEY `document_relations_ix_1` (`target_id`),
  KEY `document_relations_ix_2` (`relation_type_id`),
  CONSTRAINT `document_relations_fk_1` FOREIGN KEY (`source_id`) REFERENCES `documents` (`id`),
  CONSTRAINT `document_relations_fk_2` FOREIGN KEY (`target_id`) REFERENCES `documents` (`id`),
  CONSTRAINT `document_relations_fk_3` FOREIGN KEY (`relation_type_id`) REFERENCES `document_relation_types` (`id`),
  CONSTRAINT `document_relations_ck_1` CHECK (source_id <> target_id)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `document_grants` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `unit_id` BIGINT UNSIGNED NULL,
  `subject_key` VARCHAR(32) GENERATED ALWAYS AS (CASE WHEN user_id IS NOT NULL THEN CONCAT('u:',user_id) ELSE CONCAT('o:',unit_id) END) STORED,
  `granted_by` BIGINT UNSIGNED NOT NULL,
  `granted_at` DATETIME(6) NOT NULL,
  `expires_at` DATETIME(6) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_grants_uq_1` (`document_id`, `permission_id`, `subject_key`),
  KEY `document_grants_ix_1` (`user_id`, `document_id`),
  KEY `document_grants_ix_2` (`unit_id`, `document_id`),
  KEY `document_grants_ix_3` (`permission_id`),
  KEY `document_grants_ix_4` (`granted_by`),
  CONSTRAINT `document_grants_fk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`),
  CONSTRAINT `document_grants_fk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`),
  CONSTRAINT `document_grants_fk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `document_grants_fk_4` FOREIGN KEY (`unit_id`) REFERENCES `organization_units` (`id`),
  CONSTRAINT `document_grants_fk_5` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`),
  CONSTRAINT `document_grants_ck_1` CHECK ((user_id IS NOT NULL) + (unit_id IS NOT NULL) = 1),
  CONSTRAINT `document_grants_ck_2` CHECK (expires_at IS NULL OR expires_at > granted_at)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `document_comments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `parent_id` BIGINT UNSIGNED NULL,
  `author_id` BIGINT UNSIGNED NOT NULL,
  `body` TEXT NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `edited_at` DATETIME(6) NULL,
  PRIMARY KEY (`id`),
  KEY `document_comments_ix_1` (`document_id`, `created_at`, `id`),
  KEY `document_comments_ix_2` (`parent_id`),
  KEY `document_comments_ix_3` (`author_id`),
  CONSTRAINT `document_comments_fk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`),
  CONSTRAINT `document_comments_fk_2` FOREIGN KEY (`parent_id`) REFERENCES `document_comments` (`id`),
  CONSTRAINT `document_comments_fk_3` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );
    }
    public function down(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Destructive baseline rollback is restricted to local/testing.');
        }
        Schema::drop('document_comments');
        Schema::drop('document_grants');
        Schema::drop('document_relations');
        Schema::drop('document_relation_types');
        Schema::drop('document_release_events');
        Schema::drop('file_derivatives');
        Schema::drop('version_files');
        Schema::drop('document_versions');
        Schema::drop('file_objects');
        Schema::drop('document_tags');
        Schema::drop('tags');
        Schema::drop('documents');
        Schema::drop('reference_sequences');
        Schema::drop('confidentiality_levels');
        Schema::drop('document_categories');
    }
};
