<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // MySQL DDL is not a multi-statement transaction. See partial-failure recovery runbook.
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `correspondence_types` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `description` TEXT NULL,
  `active` BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (`id`),
  UNIQUE KEY `correspondence_types_uq_1` (`code`),
  CONSTRAINT `correspondence_types_ck_1` CHECK (active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `priorities` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `description` TEXT NULL,
  `active` BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (`id`),
  UNIQUE KEY `priorities_uq_1` (`code`),
  CONSTRAINT `priorities_ck_1` CHECK (active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `workflow_definitions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `active` BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (`id`),
  UNIQUE KEY `workflow_definitions_uq_1` (`code`),
  CONSTRAINT `workflow_definitions_ck_1` CHECK (active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `workflow_versions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `definition_id` BIGINT UNSIGNED NOT NULL,
  `version_number` INT UNSIGNED NOT NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `published_at` DATETIME(6) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `workflow_versions_uq_1` (`definition_id`, `version_number`),
  KEY `workflow_versions_ix_1` (`created_by`),
  CONSTRAINT `workflow_versions_fk_1` FOREIGN KEY (`definition_id`) REFERENCES `workflow_definitions` (`id`),
  CONSTRAINT `workflow_versions_fk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `workflow_versions_ck_1` CHECK (version_number > 0)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `workflow_states` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `workflow_version_id` BIGINT UNSIGNED NOT NULL,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `is_initial` BOOLEAN NOT NULL DEFAULT FALSE,
  `is_terminal` BOOLEAN NOT NULL DEFAULT FALSE,
  `initial_slot` TINYINT GENERATED ALWAYS AS (CASE WHEN is_initial THEN 1 ELSE NULL END) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `workflow_states_uq_1` (`workflow_version_id`, `code`),
  UNIQUE KEY `workflow_states_uq_2` (`workflow_version_id`, `initial_slot`),
  CONSTRAINT `workflow_states_fk_1` FOREIGN KEY (`workflow_version_id`) REFERENCES `workflow_versions` (`id`),
  CONSTRAINT `workflow_states_ck_1` CHECK (is_initial IN (0,1)),
  CONSTRAINT `workflow_states_ck_2` CHECK (is_terminal IN (0,1))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `workflow_transitions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `from_state_id` BIGINT UNSIGNED NOT NULL,
  `to_state_id` BIGINT UNSIGNED NOT NULL,
  `action_code` VARCHAR(64) NOT NULL,
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `requires_reason` BOOLEAN NOT NULL DEFAULT FALSE,
  `requires_signature` BOOLEAN NOT NULL DEFAULT FALSE,
  `prohibit_self_approval` BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (`id`),
  UNIQUE KEY `workflow_transitions_uq_1` (`from_state_id`, `action_code`),
  KEY `workflow_transitions_ix_1` (`to_state_id`),
  KEY `workflow_transitions_ix_2` (`permission_id`),
  CONSTRAINT `workflow_transitions_fk_1` FOREIGN KEY (`from_state_id`) REFERENCES `workflow_states` (`id`),
  CONSTRAINT `workflow_transitions_fk_2` FOREIGN KEY (`to_state_id`) REFERENCES `workflow_states` (`id`),
  CONSTRAINT `workflow_transitions_fk_3` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`),
  CONSTRAINT `workflow_transitions_ck_1` CHECK (from_state_id <> to_state_id),
  CONSTRAINT `workflow_transitions_ck_2` CHECK (requires_reason IN (0,1)),
  CONSTRAINT `workflow_transitions_ck_3` CHECK (requires_signature IN (0,1)),
  CONSTRAINT `workflow_transitions_ck_4` CHECK (prohibit_self_approval IN (0,1))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `workflow_instances` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_version_id` BIGINT UNSIGNED NOT NULL,
  `workflow_version_id` BIGINT UNSIGNED NOT NULL,
  `started_by` BIGINT UNSIGNED NOT NULL,
  `started_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `workflow_instances_ix_1` (`document_version_id`, `started_at`),
  KEY `workflow_instances_ix_2` (`workflow_version_id`),
  KEY `workflow_instances_ix_3` (`started_by`),
  CONSTRAINT `workflow_instances_fk_1` FOREIGN KEY (`document_version_id`) REFERENCES `document_versions` (`id`),
  CONSTRAINT `workflow_instances_fk_2` FOREIGN KEY (`workflow_version_id`) REFERENCES `workflow_versions` (`id`),
  CONSTRAINT `workflow_instances_fk_3` FOREIGN KEY (`started_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `workflow_tasks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `instance_id` BIGINT UNSIGNED NOT NULL,
  `state_id` BIGINT UNSIGNED NOT NULL,
  `assigned_user_id` BIGINT UNSIGNED NULL,
  `assigned_unit_id` BIGINT UNSIGNED NULL,
  `required_role_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME(6) NOT NULL,
  `due_at` DATETIME(6) NULL,
  `closed_at` DATETIME(6) NULL,
  PRIMARY KEY (`id`),
  KEY `workflow_tasks_ix_1` (`assigned_user_id`, `closed_at`, `due_at`),
  KEY `workflow_tasks_ix_2` (`assigned_unit_id`, `closed_at`, `due_at`),
  KEY `workflow_tasks_ix_3` (`instance_id`, `closed_at`),
  KEY `workflow_tasks_ix_4` (`state_id`),
  KEY `workflow_tasks_ix_5` (`required_role_id`),
  CONSTRAINT `workflow_tasks_fk_1` FOREIGN KEY (`instance_id`) REFERENCES `workflow_instances` (`id`),
  CONSTRAINT `workflow_tasks_fk_2` FOREIGN KEY (`state_id`) REFERENCES `workflow_states` (`id`),
  CONSTRAINT `workflow_tasks_fk_3` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `workflow_tasks_fk_4` FOREIGN KEY (`assigned_unit_id`) REFERENCES `organization_units` (`id`),
  CONSTRAINT `workflow_tasks_fk_5` FOREIGN KEY (`required_role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `workflow_tasks_ck_1` CHECK (assigned_user_id IS NOT NULL OR assigned_unit_id IS NOT NULL),
  CONSTRAINT `workflow_tasks_ck_2` CHECK (due_at IS NULL OR due_at >= created_at),
  CONSTRAINT `workflow_tasks_ck_3` CHECK (closed_at IS NULL OR closed_at >= created_at)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `workflow_task_decisions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id` BIGINT UNSIGNED NOT NULL,
  `decided_by` BIGINT UNSIGNED NOT NULL,
  `decision` VARCHAR(16) NOT NULL,
  `reason` TEXT NULL,
  `decided_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `workflow_task_decisions_uq_1` (`task_id`),
  KEY `workflow_task_decisions_ix_1` (`decided_by`),
  CONSTRAINT `workflow_task_decisions_fk_1` FOREIGN KEY (`task_id`) REFERENCES `workflow_tasks` (`id`),
  CONSTRAINT `workflow_task_decisions_fk_2` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`),
  CONSTRAINT `workflow_task_decisions_ck_1` CHECK (decision IN ('approve','reject','return','cancel'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `workflow_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `instance_id` BIGINT UNSIGNED NOT NULL,
  `sequence_number` INT UNSIGNED NOT NULL,
  `transition_id` BIGINT UNSIGNED NULL,
  `actor_id` BIGINT UNSIGNED NOT NULL,
  `event_kind` VARCHAR(16) NOT NULL,
  `reason` TEXT NULL,
  `occurred_at` DATETIME(6) NOT NULL,
  `idempotency_key` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `workflow_events_uq_1` (`instance_id`, `sequence_number`),
  UNIQUE KEY `workflow_events_uq_2` (`instance_id`, `idempotency_key`),
  KEY `workflow_events_ix_1` (`instance_id`, `occurred_at`),
  KEY `workflow_events_ix_2` (`transition_id`),
  KEY `workflow_events_ix_3` (`actor_id`),
  CONSTRAINT `workflow_events_fk_1` FOREIGN KEY (`instance_id`) REFERENCES `workflow_instances` (`id`),
  CONSTRAINT `workflow_events_fk_2` FOREIGN KEY (`transition_id`) REFERENCES `workflow_transitions` (`id`),
  CONSTRAINT `workflow_events_fk_3` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`),
  CONSTRAINT `workflow_events_ck_1` CHECK (sequence_number > 0),
  CONSTRAINT `workflow_events_ck_2` CHECK ((event_kind = 'transition' AND transition_id IS NOT NULL) OR (event_kind IN ('start','cancel') AND transition_id IS NULL))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `workflow_decision_events` (
  `decision_id` BIGINT UNSIGNED NOT NULL,
  `event_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`decision_id`),
  UNIQUE KEY `workflow_decision_events_uq_1` (`event_id`),
  CONSTRAINT `workflow_decision_events_fk_1` FOREIGN KEY (`decision_id`) REFERENCES `workflow_task_decisions` (`id`),
  CONSTRAINT `workflow_decision_events_fk_2` FOREIGN KEY (`event_id`) REFERENCES `workflow_events` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `office_procedure_versions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_id` BIGINT UNSIGNED NOT NULL,
  `correspondence_type_id` BIGINT UNSIGNED NOT NULL,
  `purpose_code` VARCHAR(64) NOT NULL,
  `version_number` INT UNSIGNED NOT NULL,
  `receipt_mode` VARCHAR(24) NOT NULL,
  `registry_unit_id` BIGINT UNSIGNED NULL,
  `issue_mode` VARCHAR(24) NOT NULL,
  `workflow_version_id` BIGINT UNSIGNED NULL,
  `confirmed_by` BIGINT UNSIGNED NULL,
  `confirmation_source` TEXT NULL,
  `confirmed_at` DATETIME(6) NULL,
  `effective_from` DATETIME(6) NOT NULL,
  `effective_until` DATETIME(6) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `office_procedure_versions_uq_1` (`unit_id`, `correspondence_type_id`, `purpose_code`, `version_number`),
  KEY `office_procedure_versions_ix_1` (`unit_id`, `correspondence_type_id`, `purpose_code`, `effective_from`),
  KEY `office_procedure_versions_ix_2` (`correspondence_type_id`),
  KEY `office_procedure_versions_ix_3` (`registry_unit_id`),
  KEY `office_procedure_versions_ix_4` (`workflow_version_id`),
  KEY `office_procedure_versions_ix_5` (`confirmed_by`),
  CONSTRAINT `office_procedure_versions_fk_1` FOREIGN KEY (`unit_id`) REFERENCES `organization_units` (`id`),
  CONSTRAINT `office_procedure_versions_fk_2` FOREIGN KEY (`correspondence_type_id`) REFERENCES `correspondence_types` (`id`),
  CONSTRAINT `office_procedure_versions_fk_3` FOREIGN KEY (`registry_unit_id`) REFERENCES `organization_units` (`id`),
  CONSTRAINT `office_procedure_versions_fk_4` FOREIGN KEY (`workflow_version_id`) REFERENCES `workflow_versions` (`id`),
  CONSTRAINT `office_procedure_versions_fk_5` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `office_procedure_versions_ck_1` CHECK (receipt_mode IN ('direct','via_registry','either')),
  CONSTRAINT `office_procedure_versions_ck_2` CHECK ((receipt_mode = 'direct' AND registry_unit_id IS NULL) OR (receipt_mode IN ('via_registry','either') AND registry_unit_id IS NOT NULL)),
  CONSTRAINT `office_procedure_versions_ck_3` CHECK ((issue_mode = 'workflow' AND workflow_version_id IS NOT NULL) OR (issue_mode IN ('direct','blocked') AND workflow_version_id IS NULL)),
  CONSTRAINT `office_procedure_versions_ck_4` CHECK (effective_until IS NULL OR effective_until > effective_from),
  CONSTRAINT `office_procedure_versions_ck_5` CHECK ((confirmed_by IS NULL AND confirmed_at IS NULL) OR (confirmed_by IS NOT NULL AND confirmed_at IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );
    }
    public function down(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Destructive baseline rollback is restricted to local/testing.');
        }
        Schema::drop('office_procedure_versions');
        Schema::drop('workflow_decision_events');
        Schema::drop('workflow_events');
        Schema::drop('workflow_task_decisions');
        Schema::drop('workflow_tasks');
        Schema::drop('workflow_instances');
        Schema::drop('workflow_transitions');
        Schema::drop('workflow_states');
        Schema::drop('workflow_versions');
        Schema::drop('workflow_definitions');
        Schema::drop('priorities');
        Schema::drop('correspondence_types');
    }
};
