<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // MySQL DDL is not a multi-statement transaction. See partial-failure recovery runbook.
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `external_parties` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `party_type` VARCHAR(16) NOT NULL,
  `external_identifier` VARCHAR(120) NULL,
  `created_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `external_parties_ck_1` CHECK (party_type IN ('organization','person'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `external_party_contacts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `external_party_id` BIGINT UNSIGNED NOT NULL,
  `contact_type` VARCHAR(16) NOT NULL,
  `value` VARCHAR(500) NOT NULL,
  `label` VARCHAR(100) NULL,
  PRIMARY KEY (`id`),
  KEY `external_party_contacts_ix_1` (`external_party_id`),
  CONSTRAINT `external_party_contacts_fk_1` FOREIGN KEY (`external_party_id`) REFERENCES `external_parties` (`id`),
  CONSTRAINT `external_party_contacts_ck_1` CHECK (contact_type IN ('email','phone','postal','other'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `communication_parties` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id` BIGINT UNSIGNED NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `external_party_id` BIGINT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `communication_parties_uq_1` (`unit_id`),
  UNIQUE KEY `communication_parties_uq_2` (`user_id`),
  UNIQUE KEY `communication_parties_uq_3` (`external_party_id`),
  CONSTRAINT `communication_parties_fk_1` FOREIGN KEY (`unit_id`) REFERENCES `organization_units` (`id`),
  CONSTRAINT `communication_parties_fk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `communication_parties_fk_3` FOREIGN KEY (`external_party_id`) REFERENCES `external_parties` (`id`),
  CONSTRAINT `communication_parties_ck_1` CHECK ((unit_id IS NOT NULL) + (user_id IS NOT NULL) + (external_party_id IS NOT NULL) = 1)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `correspondences` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `type_id` BIGINT UNSIGNED NOT NULL,
  `priority_id` BIGINT UNSIGNED NOT NULL,
  `purpose_code` VARCHAR(64) NOT NULL,
  `external_reference` VARCHAR(160) NULL,
  `correspondence_date` DATE NOT NULL,
  `first_receiving_unit_id` BIGINT UNSIGNED NULL,
  `received_at` DATETIME(6) NULL,
  `requested_due_at` DATETIME(6) NULL,
  `registered_by` BIGINT UNSIGNED NOT NULL,
  `registered_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `correspondences_uq_1` (`document_id`),
  KEY `correspondences_ix_1` (`type_id`, `registered_at`),
  KEY `correspondences_ix_2` (`first_receiving_unit_id`, `received_at`),
  KEY `correspondences_ix_3` (`external_reference`),
  KEY `correspondences_ix_4` (`priority_id`),
  KEY `correspondences_ix_5` (`registered_by`),
  CONSTRAINT `correspondences_fk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`),
  CONSTRAINT `correspondences_fk_2` FOREIGN KEY (`type_id`) REFERENCES `correspondence_types` (`id`),
  CONSTRAINT `correspondences_fk_3` FOREIGN KEY (`priority_id`) REFERENCES `priorities` (`id`),
  CONSTRAINT `correspondences_fk_4` FOREIGN KEY (`first_receiving_unit_id`) REFERENCES `organization_units` (`id`),
  CONSTRAINT `correspondences_fk_5` FOREIGN KEY (`registered_by`) REFERENCES `users` (`id`),
  CONSTRAINT `correspondences_ck_1` CHECK ((first_receiving_unit_id IS NULL AND received_at IS NULL) OR (first_receiving_unit_id IS NOT NULL AND received_at IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `correspondence_procedures` (
  `correspondence_id` BIGINT UNSIGNED NOT NULL,
  `procedure_version_id` BIGINT UNSIGNED NOT NULL,
  `applied_by` BIGINT UNSIGNED NOT NULL,
  `applied_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`correspondence_id`),
  KEY `correspondence_procedures_ix_1` (`procedure_version_id`),
  KEY `correspondence_procedures_ix_2` (`applied_by`),
  CONSTRAINT `correspondence_procedures_fk_1` FOREIGN KEY (`correspondence_id`) REFERENCES `correspondences` (`id`),
  CONSTRAINT `correspondence_procedures_fk_2` FOREIGN KEY (`procedure_version_id`) REFERENCES `office_procedure_versions` (`id`),
  CONSTRAINT `correspondence_procedures_fk_3` FOREIGN KEY (`applied_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `correspondence_participants` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `correspondence_id` BIGINT UNSIGNED NOT NULL,
  `party_id` BIGINT UNSIGNED NOT NULL,
  `participation_type` VARCHAR(16) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `correspondence_participants_uq_1` (`correspondence_id`, `party_id`, `participation_type`),
  KEY `correspondence_participants_ix_1` (`party_id`),
  CONSTRAINT `correspondence_participants_fk_1` FOREIGN KEY (`correspondence_id`) REFERENCES `correspondences` (`id`),
  CONSTRAINT `correspondence_participants_fk_2` FOREIGN KEY (`party_id`) REFERENCES `communication_parties` (`id`),
  CONSTRAINT `correspondence_participants_ck_1` CHECK (participation_type IN ('sender','to','cc'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `dispatch_methods` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `description` TEXT NULL,
  `active` BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (`id`),
  UNIQUE KEY `dispatch_methods_uq_1` (`code`),
  CONSTRAINT `dispatch_methods_ck_1` CHECK (active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `correspondence_dispatches` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `participant_id` BIGINT UNSIGNED NOT NULL,
  `document_version_id` BIGINT UNSIGNED NOT NULL,
  `method_id` BIGINT UNSIGNED NOT NULL,
  `dispatched_by` BIGINT UNSIGNED NOT NULL,
  `dispatched_at` DATETIME(6) NOT NULL,
  `tracking_reference` VARCHAR(160) NULL,
  `evidence_file_id` BIGINT UNSIGNED NULL,
  `idempotency_key` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `correspondence_dispatches_uq_1` (`participant_id`, `idempotency_key`),
  KEY `correspondence_dispatches_ix_1` (`document_version_id`, `dispatched_at`),
  KEY `correspondence_dispatches_ix_2` (`method_id`),
  KEY `correspondence_dispatches_ix_3` (`dispatched_by`),
  KEY `correspondence_dispatches_ix_4` (`evidence_file_id`),
  CONSTRAINT `correspondence_dispatches_fk_1` FOREIGN KEY (`participant_id`) REFERENCES `correspondence_participants` (`id`),
  CONSTRAINT `correspondence_dispatches_fk_2` FOREIGN KEY (`document_version_id`) REFERENCES `document_versions` (`id`),
  CONSTRAINT `correspondence_dispatches_fk_3` FOREIGN KEY (`method_id`) REFERENCES `dispatch_methods` (`id`),
  CONSTRAINT `correspondence_dispatches_fk_4` FOREIGN KEY (`dispatched_by`) REFERENCES `users` (`id`),
  CONSTRAINT `correspondence_dispatches_fk_5` FOREIGN KEY (`evidence_file_id`) REFERENCES `file_objects` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `document_movements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `from_unit_id` BIGINT UNSIGNED NULL,
  `to_unit_id` BIGINT UNSIGNED NOT NULL,
  `sent_by` BIGINT UNSIGNED NOT NULL,
  `sent_at` DATETIME(6) NOT NULL,
  `instruction` TEXT NULL,
  `due_at` DATETIME(6) NULL,
  `idempotency_key` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_movements_uq_1` (`document_id`, `idempotency_key`),
  KEY `document_movements_ix_1` (`document_id`, `sent_at`, `id`),
  KEY `document_movements_ix_2` (`to_unit_id`, `sent_at`),
  KEY `document_movements_ix_3` (`from_unit_id`),
  KEY `document_movements_ix_4` (`sent_by`),
  CONSTRAINT `document_movements_fk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`),
  CONSTRAINT `document_movements_fk_2` FOREIGN KEY (`from_unit_id`) REFERENCES `organization_units` (`id`),
  CONSTRAINT `document_movements_fk_3` FOREIGN KEY (`to_unit_id`) REFERENCES `organization_units` (`id`),
  CONSTRAINT `document_movements_fk_4` FOREIGN KEY (`sent_by`) REFERENCES `users` (`id`),
  CONSTRAINT `document_movements_ck_1` CHECK (from_unit_id IS NULL OR from_unit_id <> to_unit_id),
  CONSTRAINT `document_movements_ck_2` CHECK (due_at IS NULL OR due_at >= sent_at)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `movement_receipts` (
  `movement_id` BIGINT UNSIGNED NOT NULL,
  `acknowledged_by` BIGINT UNSIGNED NOT NULL,
  `acknowledged_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`movement_id`),
  KEY `movement_receipts_ix_1` (`acknowledged_by`),
  CONSTRAINT `movement_receipts_fk_1` FOREIGN KEY (`movement_id`) REFERENCES `document_movements` (`id`),
  CONSTRAINT `movement_receipts_fk_2` FOREIGN KEY (`acknowledged_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `document_assignments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `assigned_user_id` BIGINT UNSIGNED NULL,
  `assigned_unit_id` BIGINT UNSIGNED NULL,
  `assigned_by` BIGINT UNSIGNED NOT NULL,
  `assigned_at` DATETIME(6) NOT NULL,
  `due_at` DATETIME(6) NULL,
  `instruction` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `document_assignments_ix_1` (`assigned_user_id`, `due_at`),
  KEY `document_assignments_ix_2` (`assigned_unit_id`, `due_at`),
  KEY `document_assignments_ix_3` (`document_id`, `assigned_at`),
  KEY `document_assignments_ix_4` (`assigned_by`),
  CONSTRAINT `document_assignments_fk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`),
  CONSTRAINT `document_assignments_fk_2` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `document_assignments_fk_3` FOREIGN KEY (`assigned_unit_id`) REFERENCES `organization_units` (`id`),
  CONSTRAINT `document_assignments_fk_4` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`),
  CONSTRAINT `document_assignments_ck_1` CHECK (assigned_user_id IS NOT NULL OR assigned_unit_id IS NOT NULL),
  CONSTRAINT `document_assignments_ck_2` CHECK (due_at IS NULL OR due_at >= assigned_at)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `assignment_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` BIGINT UNSIGNED NOT NULL,
  `sequence_number` INT UNSIGNED NOT NULL,
  `action` VARCHAR(24) NOT NULL,
  `actor_id` BIGINT UNSIGNED NOT NULL,
  `occurred_at` DATETIME(6) NOT NULL,
  `comment` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assignment_events_uq_1` (`assignment_id`, `sequence_number`),
  KEY `assignment_events_ix_1` (`actor_id`),
  CONSTRAINT `assignment_events_fk_1` FOREIGN KEY (`assignment_id`) REFERENCES `document_assignments` (`id`),
  CONSTRAINT `assignment_events_fk_2` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`),
  CONSTRAINT `assignment_events_ck_1` CHECK (action IN ('assigned','accepted','completed','returned','cancelled')),
  CONSTRAINT `assignment_events_ck_2` CHECK (sequence_number > 0)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );
    }
    public function down(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Destructive baseline rollback is restricted to local/testing.');
        }
        Schema::drop('assignment_events');
        Schema::drop('document_assignments');
        Schema::drop('movement_receipts');
        Schema::drop('document_movements');
        Schema::drop('correspondence_dispatches');
        Schema::drop('dispatch_methods');
        Schema::drop('correspondence_participants');
        Schema::drop('correspondence_procedures');
        Schema::drop('correspondences');
        Schema::drop('communication_parties');
        Schema::drop('external_party_contacts');
        Schema::drop('external_parties');
    }
};
