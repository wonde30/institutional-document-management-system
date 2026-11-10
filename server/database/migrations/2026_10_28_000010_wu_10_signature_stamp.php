<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // MySQL DDL is not a multi-statement transaction. See partial-failure recovery runbook.
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `signing_identities` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `provider_code` VARCHAR(64) NOT NULL,
  `provider_identity_reference` VARCHAR(191) NOT NULL,
  `public_certificate_file_id` BIGINT UNSIGNED NULL,
  `valid_from` DATETIME(6) NULL,
  `valid_until` DATETIME(6) NULL,
  `revoked_at` DATETIME(6) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `signing_identities_uq_1` (`provider_code`, `provider_identity_reference`),
  KEY `signing_identities_ix_1` (`user_id`),
  KEY `signing_identities_ix_2` (`public_certificate_file_id`),
  CONSTRAINT `signing_identities_fk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `signing_identities_fk_2` FOREIGN KEY (`public_certificate_file_id`) REFERENCES `file_objects` (`id`),
  CONSTRAINT `signing_identities_ck_1` CHECK (valid_until IS NULL OR valid_from IS NULL OR valid_until > valid_from)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `signature_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_version_id` BIGINT UNSIGNED NOT NULL,
  `requested_by` BIGINT UNSIGNED NOT NULL,
  `signer_id` BIGINT UNSIGNED NOT NULL,
  `requested_at` DATETIME(6) NOT NULL,
  `expires_at` DATETIME(6) NULL,
  `purpose` TEXT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `signature_requests_ix_1` (`signer_id`, `requested_at`),
  KEY `signature_requests_ix_2` (`document_version_id`),
  KEY `signature_requests_ix_3` (`requested_by`),
  CONSTRAINT `signature_requests_fk_1` FOREIGN KEY (`document_version_id`) REFERENCES `document_versions` (`id`),
  CONSTRAINT `signature_requests_fk_2` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`),
  CONSTRAINT `signature_requests_fk_3` FOREIGN KEY (`signer_id`) REFERENCES `users` (`id`),
  CONSTRAINT `signature_requests_ck_1` CHECK (expires_at IS NULL OR expires_at > requested_at)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `digital_signatures` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_id` BIGINT UNSIGNED NOT NULL,
  `signing_identity_id` BIGINT UNSIGNED NOT NULL,
  `source_file_id` BIGINT UNSIGNED NOT NULL,
  `signed_file_id` BIGINT UNSIGNED NOT NULL,
  `signature_format` VARCHAR(64) NOT NULL,
  `signed_at` DATETIME(6) NOT NULL,
  `provider_transaction_reference` VARCHAR(191) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `digital_signatures_uq_1` (`request_id`),
  UNIQUE KEY `digital_signatures_uq_2` (`signed_file_id`),
  KEY `digital_signatures_ix_1` (`signing_identity_id`),
  KEY `digital_signatures_ix_2` (`source_file_id`),
  CONSTRAINT `digital_signatures_fk_1` FOREIGN KEY (`request_id`) REFERENCES `signature_requests` (`id`),
  CONSTRAINT `digital_signatures_fk_2` FOREIGN KEY (`signing_identity_id`) REFERENCES `signing_identities` (`id`),
  CONSTRAINT `digital_signatures_fk_3` FOREIGN KEY (`source_file_id`) REFERENCES `file_objects` (`id`),
  CONSTRAINT `digital_signatures_fk_4` FOREIGN KEY (`signed_file_id`) REFERENCES `file_objects` (`id`),
  CONSTRAINT `digital_signatures_ck_1` CHECK (source_file_id <> signed_file_id)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `signature_verifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `signature_id` BIGINT UNSIGNED NOT NULL,
  `verified_at` DATETIME(6) NOT NULL,
  `outcome` VARCHAR(16) NOT NULL,
  `certificate_status` VARCHAR(64) NULL,
  `timestamp_status` VARCHAR(64) NULL,
  `details_json` JSON NULL,
  PRIMARY KEY (`id`),
  KEY `signature_verifications_ix_1` (`signature_id`, `verified_at`),
  CONSTRAINT `signature_verifications_fk_1` FOREIGN KEY (`signature_id`) REFERENCES `digital_signatures` (`id`),
  CONSTRAINT `signature_verifications_ck_1` CHECK (outcome IN ('valid','invalid','indeterminate','error'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `stamp_definitions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `active` BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stamp_definitions_uq_1` (`unit_id`, `name`),
  CONSTRAINT `stamp_definitions_fk_1` FOREIGN KEY (`unit_id`) REFERENCES `organization_units` (`id`),
  CONSTRAINT `stamp_definitions_ck_1` CHECK (active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `stamp_versions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `stamp_id` BIGINT UNSIGNED NOT NULL,
  `version_number` INT UNSIGNED NOT NULL,
  `image_file_id` BIGINT UNSIGNED NOT NULL,
  `effective_from` DATETIME(6) NOT NULL,
  `retired_at` DATETIME(6) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stamp_versions_uq_1` (`stamp_id`, `version_number`),
  KEY `stamp_versions_ix_1` (`image_file_id`),
  CONSTRAINT `stamp_versions_fk_1` FOREIGN KEY (`stamp_id`) REFERENCES `stamp_definitions` (`id`),
  CONSTRAINT `stamp_versions_fk_2` FOREIGN KEY (`image_file_id`) REFERENCES `file_objects` (`id`),
  CONSTRAINT `stamp_versions_ck_1` CHECK (version_number > 0),
  CONSTRAINT `stamp_versions_ck_2` CHECK (retired_at IS NULL OR retired_at > effective_from)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `stamp_authorizations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `stamp_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `granted_by` BIGINT UNSIGNED NOT NULL,
  `valid_from` DATETIME(6) NOT NULL,
  `valid_until` DATETIME(6) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stamp_authorizations_uq_1` (`stamp_id`, `user_id`, `valid_from`),
  KEY `stamp_authorizations_ix_1` (`user_id`),
  KEY `stamp_authorizations_ix_2` (`granted_by`),
  CONSTRAINT `stamp_authorizations_fk_1` FOREIGN KEY (`stamp_id`) REFERENCES `stamp_definitions` (`id`),
  CONSTRAINT `stamp_authorizations_fk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `stamp_authorizations_fk_3` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`),
  CONSTRAINT `stamp_authorizations_ck_1` CHECK (valid_until IS NULL OR valid_until > valid_from)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `stamp_applications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `stamp_version_id` BIGINT UNSIGNED NOT NULL,
  `document_version_id` BIGINT UNSIGNED NOT NULL,
  `source_file_id` BIGINT UNSIGNED NOT NULL,
  `result_file_id` BIGINT UNSIGNED NOT NULL,
  `applied_by` BIGINT UNSIGNED NOT NULL,
  `applied_at` DATETIME(6) NOT NULL,
  `reason` TEXT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stamp_applications_uq_1` (`result_file_id`),
  KEY `stamp_applications_ix_1` (`stamp_version_id`),
  KEY `stamp_applications_ix_2` (`document_version_id`),
  KEY `stamp_applications_ix_3` (`source_file_id`),
  KEY `stamp_applications_ix_4` (`applied_by`),
  CONSTRAINT `stamp_applications_fk_1` FOREIGN KEY (`stamp_version_id`) REFERENCES `stamp_versions` (`id`),
  CONSTRAINT `stamp_applications_fk_2` FOREIGN KEY (`document_version_id`) REFERENCES `document_versions` (`id`),
  CONSTRAINT `stamp_applications_fk_3` FOREIGN KEY (`source_file_id`) REFERENCES `file_objects` (`id`),
  CONSTRAINT `stamp_applications_fk_4` FOREIGN KEY (`result_file_id`) REFERENCES `file_objects` (`id`),
  CONSTRAINT `stamp_applications_fk_5` FOREIGN KEY (`applied_by`) REFERENCES `users` (`id`),
  CONSTRAINT `stamp_applications_ck_1` CHECK (source_file_id <> result_file_id)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );
    }
    public function down(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Destructive baseline rollback is restricted to local/testing.');
        }
        Schema::drop('stamp_applications');
        Schema::drop('stamp_authorizations');
        Schema::drop('stamp_versions');
        Schema::drop('stamp_definitions');
        Schema::drop('signature_verifications');
        Schema::drop('digital_signatures');
        Schema::drop('signature_requests');
        Schema::drop('signing_identities');
    }
};
