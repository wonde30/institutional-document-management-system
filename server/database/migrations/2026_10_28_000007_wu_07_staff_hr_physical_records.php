<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // MySQL DDL is not a multi-statement transaction. See partial-failure recovery runbook.
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `staff_profiles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `employee_number` VARCHAR(64) NOT NULL,
  `biography` TEXT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_profiles_uq_1` (`user_id`),
  UNIQUE KEY `staff_profiles_uq_2` (`employee_number`),
  CONSTRAINT `staff_profiles_fk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `institutions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `description` TEXT NULL,
  `active` BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (`id`),
  UNIQUE KEY `institutions_uq_1` (`code`),
  CONSTRAINT `institutions_ck_1` CHECK (active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `qualification_types` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `description` TEXT NULL,
  `active` BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (`id`),
  UNIQUE KEY `qualification_types_uq_1` (`code`),
  CONSTRAINT `qualification_types_ck_1` CHECK (active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `staff_qualifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` BIGINT UNSIGNED NOT NULL,
  `qualification_type_id` BIGINT UNSIGNED NOT NULL,
  `institution_id` BIGINT UNSIGNED NOT NULL,
  `field_of_study` VARCHAR(200) NULL,
  `awarded_on` DATE NULL,
  `evidence_document_id` BIGINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `staff_qualifications_ix_1` (`staff_id`, `awarded_on`),
  KEY `staff_qualifications_ix_2` (`qualification_type_id`),
  KEY `staff_qualifications_ix_3` (`institution_id`),
  KEY `staff_qualifications_ix_4` (`evidence_document_id`),
  CONSTRAINT `staff_qualifications_fk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff_profiles` (`id`),
  CONSTRAINT `staff_qualifications_fk_2` FOREIGN KEY (`qualification_type_id`) REFERENCES `qualification_types` (`id`),
  CONSTRAINT `staff_qualifications_fk_3` FOREIGN KEY (`institution_id`) REFERENCES `institutions` (`id`),
  CONSTRAINT `staff_qualifications_fk_4` FOREIGN KEY (`evidence_document_id`) REFERENCES `documents` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `staff_certificates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `issuer_id` BIGINT UNSIGNED NOT NULL,
  `issued_on` DATE NULL,
  `expires_on` DATE NULL,
  `evidence_document_id` BIGINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `staff_certificates_ix_1` (`staff_id`, `issued_on`),
  KEY `staff_certificates_ix_2` (`issuer_id`),
  KEY `staff_certificates_ix_3` (`evidence_document_id`),
  CONSTRAINT `staff_certificates_fk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff_profiles` (`id`),
  CONSTRAINT `staff_certificates_fk_2` FOREIGN KEY (`issuer_id`) REFERENCES `institutions` (`id`),
  CONSTRAINT `staff_certificates_fk_3` FOREIGN KEY (`evidence_document_id`) REFERENCES `documents` (`id`),
  CONSTRAINT `staff_certificates_ck_1` CHECK (expires_on IS NULL OR issued_on IS NULL OR expires_on >= issued_on)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `staff_employment_records` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` BIGINT UNSIGNED NOT NULL,
  `unit_id` BIGINT UNSIGNED NOT NULL,
  `position_title` VARCHAR(160) NOT NULL,
  `starts_on` DATE NOT NULL,
  `ends_on` DATE NULL,
  `evidence_document_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `staff_employment_records_ix_1` (`staff_id`, `starts_on`),
  KEY `staff_employment_records_ix_2` (`unit_id`),
  KEY `staff_employment_records_ix_3` (`evidence_document_id`),
  CONSTRAINT `staff_employment_records_fk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff_profiles` (`id`),
  CONSTRAINT `staff_employment_records_fk_2` FOREIGN KEY (`unit_id`) REFERENCES `organization_units` (`id`),
  CONSTRAINT `staff_employment_records_fk_3` FOREIGN KEY (`evidence_document_id`) REFERENCES `documents` (`id`),
  CONSTRAINT `staff_employment_records_ck_1` CHECK (ends_on IS NULL OR ends_on >= starts_on)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `hr_record_types` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `description` TEXT NULL,
  `active` BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (`id`),
  UNIQUE KEY `hr_record_types_uq_1` (`code`),
  CONSTRAINT `hr_record_types_ck_1` CHECK (active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `staff_documents` (
  `staff_id` BIGINT UNSIGNED NOT NULL,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `record_type_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`staff_id`, `document_id`, `record_type_id`),
  KEY `staff_documents_ix_1` (`document_id`),
  KEY `staff_documents_ix_2` (`record_type_id`),
  CONSTRAINT `staff_documents_fk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff_profiles` (`id`),
  CONSTRAINT `staff_documents_fk_2` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`),
  CONSTRAINT `staff_documents_fk_3` FOREIGN KEY (`record_type_id`) REFERENCES `hr_record_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `physical_storage_locations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` BIGINT UNSIGNED NULL,
  `unit_id` BIGINT UNSIGNED NOT NULL,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `active` BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (`id`),
  UNIQUE KEY `physical_storage_locations_uq_1` (`code`),
  KEY `physical_storage_locations_ix_1` (`parent_id`),
  KEY `physical_storage_locations_ix_2` (`unit_id`),
  CONSTRAINT `physical_storage_locations_fk_1` FOREIGN KEY (`parent_id`) REFERENCES `physical_storage_locations` (`id`),
  CONSTRAINT `physical_storage_locations_fk_2` FOREIGN KEY (`unit_id`) REFERENCES `organization_units` (`id`),
  CONSTRAINT `physical_storage_locations_ck_1` CHECK (active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `physical_originals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `physical_reference` VARCHAR(160) NOT NULL,
  `retention_requirement` VARCHAR(16) NOT NULL DEFAULT 'unknown',
  `retention_policy_version_id` BIGINT UNSIGNED NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `physical_originals_uq_1` (`physical_reference`),
  KEY `physical_originals_ix_1` (`document_id`),
  KEY `physical_originals_ix_2` (`retention_policy_version_id`),
  KEY `physical_originals_ix_3` (`created_by`),
  CONSTRAINT `physical_originals_fk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`),
  CONSTRAINT `physical_originals_fk_2` FOREIGN KEY (`retention_policy_version_id`) REFERENCES `retention_policy_versions` (`id`),
  CONSTRAINT `physical_originals_fk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `physical_originals_ck_1` CHECK (retention_requirement IN ('unknown','required','not_required'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `physical_custody_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `physical_original_id` BIGINT UNSIGNED NOT NULL,
  `location_id` BIGINT UNSIGNED NULL,
  `custodian_id` BIGINT UNSIGNED NULL,
  `action` VARCHAR(24) NOT NULL,
  `actor_id` BIGINT UNSIGNED NOT NULL,
  `occurred_at` DATETIME(6) NOT NULL,
  `reason` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `physical_custody_events_ix_1` (`physical_original_id`, `occurred_at`, `id`),
  KEY `physical_custody_events_ix_2` (`location_id`),
  KEY `physical_custody_events_ix_3` (`custodian_id`),
  KEY `physical_custody_events_ix_4` (`actor_id`),
  CONSTRAINT `physical_custody_events_fk_1` FOREIGN KEY (`physical_original_id`) REFERENCES `physical_originals` (`id`),
  CONSTRAINT `physical_custody_events_fk_2` FOREIGN KEY (`location_id`) REFERENCES `physical_storage_locations` (`id`),
  CONSTRAINT `physical_custody_events_fk_3` FOREIGN KEY (`custodian_id`) REFERENCES `users` (`id`),
  CONSTRAINT `physical_custody_events_fk_4` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`),
  CONSTRAINT `physical_custody_events_ck_1` CHECK (action IN ('register','move','checkout','return','missing','transfer','destroy'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `digitization_records` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `physical_original_id` BIGINT UNSIGNED NOT NULL,
  `document_version_id` BIGINT UNSIGNED NOT NULL,
  `digitized_by` BIGINT UNSIGNED NOT NULL,
  `digitized_at` DATETIME(6) NOT NULL,
  `verification_status` VARCHAR(16) NOT NULL DEFAULT 'pending',
  `verified_by` BIGINT UNSIGNED NULL,
  `verified_at` DATETIME(6) NULL,
  `verification_note` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `digitization_records_uq_1` (`physical_original_id`, `document_version_id`),
  KEY `digitization_records_ix_1` (`document_version_id`),
  KEY `digitization_records_ix_2` (`digitized_by`),
  KEY `digitization_records_ix_3` (`verified_by`),
  CONSTRAINT `digitization_records_fk_1` FOREIGN KEY (`physical_original_id`) REFERENCES `physical_originals` (`id`),
  CONSTRAINT `digitization_records_fk_2` FOREIGN KEY (`document_version_id`) REFERENCES `document_versions` (`id`),
  CONSTRAINT `digitization_records_fk_3` FOREIGN KEY (`digitized_by`) REFERENCES `users` (`id`),
  CONSTRAINT `digitization_records_fk_4` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`),
  CONSTRAINT `digitization_records_ck_1` CHECK ((verification_status = 'pending' AND verified_by IS NULL AND verified_at IS NULL) OR (verification_status IN ('accepted','rejected') AND verified_by IS NOT NULL AND verified_at IS NOT NULL)),
  CONSTRAINT `digitization_records_ck_2` CHECK (verified_at IS NULL OR verified_at >= digitized_at)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `physical_disposition_targets` (
  `request_id` BIGINT UNSIGNED NOT NULL,
  `physical_original_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`request_id`, `physical_original_id`),
  KEY `physical_disposition_targets_ix_1` (`physical_original_id`),
  CONSTRAINT `physical_disposition_targets_fk_1` FOREIGN KEY (`request_id`) REFERENCES `disposition_requests` (`id`),
  CONSTRAINT `physical_disposition_targets_fk_2` FOREIGN KEY (`physical_original_id`) REFERENCES `physical_originals` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );
    }
    public function down(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Destructive baseline rollback is restricted to local/testing.');
        }
        Schema::drop('physical_disposition_targets');
        Schema::drop('digitization_records');
        Schema::drop('physical_custody_events');
        Schema::drop('physical_originals');
        Schema::drop('physical_storage_locations');
        Schema::drop('staff_documents');
        Schema::drop('hr_record_types');
        Schema::drop('staff_employment_records');
        Schema::drop('staff_certificates');
        Schema::drop('staff_qualifications');
        Schema::drop('qualification_types');
        Schema::drop('institutions');
        Schema::drop('staff_profiles');
    }
};
