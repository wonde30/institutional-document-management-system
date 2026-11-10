<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // MySQL DDL is not a multi-statement transaction. See partial-failure recovery runbook.
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `accreditation_frameworks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `description` TEXT NULL,
  `active` BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (`id`),
  UNIQUE KEY `accreditation_frameworks_uq_1` (`code`),
  CONSTRAINT `accreditation_frameworks_ck_1` CHECK (active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `accreditation_framework_versions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `framework_id` BIGINT UNSIGNED NOT NULL,
  `version_label` VARCHAR(100) NOT NULL,
  `effective_from` DATE NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `accreditation_framework_versions_uq_1` (`framework_id`, `version_label`),
  CONSTRAINT `accreditation_framework_versions_fk_1` FOREIGN KEY (`framework_id`) REFERENCES `accreditation_frameworks` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `accreditation_standards` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `framework_version_id` BIGINT UNSIGNED NOT NULL,
  `parent_id` BIGINT UNSIGNED NULL,
  `code` VARCHAR(64) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `accreditation_standards_uq_1` (`framework_version_id`, `code`),
  KEY `accreditation_standards_ix_1` (`parent_id`),
  CONSTRAINT `accreditation_standards_fk_1` FOREIGN KEY (`framework_version_id`) REFERENCES `accreditation_framework_versions` (`id`),
  CONSTRAINT `accreditation_standards_fk_2` FOREIGN KEY (`parent_id`) REFERENCES `accreditation_standards` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `accreditation_cycles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `framework_version_id` BIGINT UNSIGNED NOT NULL,
  `unit_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `starts_on` DATE NOT NULL,
  `ends_on` DATE NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `accreditation_cycles_uq_1` (`framework_version_id`, `unit_id`, `name`),
  KEY `accreditation_cycles_ix_1` (`unit_id`),
  CONSTRAINT `accreditation_cycles_fk_1` FOREIGN KEY (`framework_version_id`) REFERENCES `accreditation_framework_versions` (`id`),
  CONSTRAINT `accreditation_cycles_fk_2` FOREIGN KEY (`unit_id`) REFERENCES `organization_units` (`id`),
  CONSTRAINT `accreditation_cycles_ck_1` CHECK (ends_on IS NULL OR ends_on >= starts_on)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `accreditation_evidence` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cycle_id` BIGINT UNSIGNED NOT NULL,
  `standard_id` BIGINT UNSIGNED NOT NULL,
  `document_version_id` BIGINT UNSIGNED NOT NULL,
  `submitted_by` BIGINT UNSIGNED NOT NULL,
  `submitted_at` DATETIME(6) NOT NULL,
  `note` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `accreditation_evidence_uq_1` (`cycle_id`, `standard_id`, `document_version_id`),
  KEY `accreditation_evidence_ix_1` (`standard_id`),
  KEY `accreditation_evidence_ix_2` (`document_version_id`),
  KEY `accreditation_evidence_ix_3` (`submitted_by`),
  CONSTRAINT `accreditation_evidence_fk_1` FOREIGN KEY (`cycle_id`) REFERENCES `accreditation_cycles` (`id`),
  CONSTRAINT `accreditation_evidence_fk_2` FOREIGN KEY (`standard_id`) REFERENCES `accreditation_standards` (`id`),
  CONSTRAINT `accreditation_evidence_fk_3` FOREIGN KEY (`document_version_id`) REFERENCES `document_versions` (`id`),
  CONSTRAINT `accreditation_evidence_fk_4` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `accreditation_reviews` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `evidence_id` BIGINT UNSIGNED NOT NULL,
  `reviewer_id` BIGINT UNSIGNED NOT NULL,
  `decision` VARCHAR(16) NOT NULL,
  `comment` TEXT NULL,
  `reviewed_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `accreditation_reviews_ix_1` (`evidence_id`, `reviewed_at`, `id`),
  KEY `accreditation_reviews_ix_2` (`reviewer_id`),
  CONSTRAINT `accreditation_reviews_fk_1` FOREIGN KEY (`evidence_id`) REFERENCES `accreditation_evidence` (`id`),
  CONSTRAINT `accreditation_reviews_fk_2` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`),
  CONSTRAINT `accreditation_reviews_ck_1` CHECK (decision IN ('accepted','rejected','needs_revision'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );
    }
    public function down(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Destructive baseline rollback is restricted to local/testing.');
        }
        Schema::drop('accreditation_reviews');
        Schema::drop('accreditation_evidence');
        Schema::drop('accreditation_cycles');
        Schema::drop('accreditation_standards');
        Schema::drop('accreditation_framework_versions');
        Schema::drop('accreditation_frameworks');
    }
};
