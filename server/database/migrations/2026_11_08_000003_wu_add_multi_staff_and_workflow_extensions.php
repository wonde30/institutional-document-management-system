<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Assignment Participants (M:N operational collaboration)
        DB::statement(<<<'SQL'
CREATE TABLE `assignment_participants` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `assignment_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `role_code` VARCHAR(24) NOT NULL DEFAULT 'contributor',
  `status` VARCHAR(16) NOT NULL DEFAULT 'pending',
  `assigned_at` DATETIME(6) NOT NULL,
  `responded_at` DATETIME(6) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `assignment_participants_uq_1` (`assignment_id`, `user_id`),
  KEY `assignment_participants_ix_1` (`user_id`, `status`),
  CONSTRAINT `assignment_participants_fk_1` FOREIGN KEY (`assignment_id`) REFERENCES `document_assignments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `assignment_participants_fk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `assignment_participants_ck_1` CHECK (`role_code` IN ('lead', 'writer', 'contributor', 'secretary')),
  CONSTRAINT `assignment_participants_ck_2` CHECK (`status` IN ('pending', 'accepted', 'completed', 'reassigned', 'declined'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
SQL
        );

        // 2. Assignment Responses (M:N directive fulfillment)
        DB::statement(<<<'SQL'
CREATE TABLE `assignment_responses` (
  `assignment_id` BIGINT UNSIGNED NOT NULL,
  `response_document_id` BIGINT UNSIGNED NOT NULL,
  `relationship_role` VARCHAR(24) NOT NULL DEFAULT 'full_reply',
  `is_fulfilling` TINYINT(1) NOT NULL DEFAULT 1,
  `linked_by` BIGINT UNSIGNED NOT NULL,
  `linked_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`assignment_id`, `response_document_id`),
  KEY `assignment_responses_ix_1` (`response_document_id`),
  KEY `assignment_responses_ix_2` (`linked_by`),
  CONSTRAINT `assignment_responses_fk_1` FOREIGN KEY (`assignment_id`) REFERENCES `document_assignments` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `assignment_responses_fk_2` FOREIGN KEY (`response_document_id`) REFERENCES `documents` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `assignment_responses_fk_3` FOREIGN KEY (`linked_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `assignment_responses_ck_1` CHECK (`relationship_role` IN ('full_reply', 'interim_reply', 'supporting_report', 'partial_reply')),
  CONSTRAINT `assignment_responses_ck_2` CHECK (`is_fulfilling` IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
SQL
        );

        // 3. Workflow Task Participants (Parallel committee reviews)
        DB::statement(<<<'SQL'
CREATE TABLE `workflow_task_participants` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `participation_role` VARCHAR(24) NOT NULL DEFAULT 'reviewer',
  `status` VARCHAR(16) NOT NULL DEFAULT 'pending',
  `assigned_at` DATETIME(6) NOT NULL,
  `responded_at` DATETIME(6) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `workflow_task_participants_uq_1` (`task_id`, `user_id`),
  KEY `workflow_task_participants_ix_1` (`user_id`, `status`),
  CONSTRAINT `workflow_task_participants_fk_1` FOREIGN KEY (`task_id`) REFERENCES `workflow_tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `workflow_task_participants_fk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `workflow_task_participants_ck_1` CHECK (`participation_role` IN ('lead_reviewer', 'reviewer', 'contributor', 'observer')),
  CONSTRAINT `workflow_task_participants_ck_2` CHECK (`status` IN ('pending', 'completed', 'excused'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
SQL
        );

        // 4. Alter workflow_tasks (Delegation attributes)
        DB::statement(<<<'SQL'
ALTER TABLE `workflow_tasks`
  ADD COLUMN `delegated_to_user_id` BIGINT UNSIGNED NULL AFTER `required_role_id`,
  ADD COLUMN `delegated_by` BIGINT UNSIGNED NULL AFTER `delegated_to_user_id`,
  ADD COLUMN `delegation_reason` TEXT NULL AFTER `delegated_by`,
  ADD KEY `workflow_tasks_ix_6` (`delegated_to_user_id`, `closed_at`),
  ADD KEY `workflow_tasks_ix_7` (`delegated_by`),
  ADD CONSTRAINT `workflow_tasks_fk_6` FOREIGN KEY (`delegated_to_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `workflow_tasks_fk_7` FOREIGN KEY (`delegated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
SQL
        );

        // 5. Alter workflow_instances (Parent revision instance link)
        DB::statement(<<<'SQL'
ALTER TABLE `workflow_instances`
  ADD COLUMN `parent_instance_id` BIGINT UNSIGNED NULL AFTER `workflow_version_id`,
  ADD KEY `workflow_instances_ix_4` (`parent_instance_id`),
  ADD CONSTRAINT `workflow_instances_fk_4` FOREIGN KEY (`parent_instance_id`) REFERENCES `workflow_instances` (`id`) ON DELETE SET NULL;
SQL
        );

        // 6. Alter workflow_transitions (Consensus policies)
        DB::statement(<<<'SQL'
ALTER TABLE `workflow_transitions`
  ADD COLUMN `approval_policy` VARCHAR(20) NOT NULL DEFAULT 'single' AFTER `prohibit_self_approval`,
  ADD COLUMN `quorum_count` INT UNSIGNED NULL AFTER `approval_policy`,
  ADD COLUMN `quorum_percentage` DECIMAL(5,2) NULL AFTER `quorum_count`,
  ADD CONSTRAINT `workflow_transitions_ck_5` CHECK (`approval_policy` IN ('single', 'all_required', 'any_required', 'majority', 'quorum'));
SQL
        );
    }

    public function down(): void
    {
        // Revert workflow_transitions
        DB::statement("ALTER TABLE `workflow_transitions` DROP CONSTRAINT `workflow_transitions_ck_5`");
        DB::statement("ALTER TABLE `workflow_transitions` DROP COLUMN `quorum_percentage`, DROP COLUMN `quorum_count`, DROP COLUMN `approval_policy`");

        // Revert workflow_instances
        DB::statement("ALTER TABLE `workflow_instances` DROP FOREIGN KEY `workflow_instances_fk_4`");
        DB::statement("ALTER TABLE `workflow_instances` DROP KEY `workflow_instances_ix_4`");
        DB::statement("ALTER TABLE `workflow_instances` DROP COLUMN `parent_instance_id`");

        // Revert workflow_tasks
        DB::statement("ALTER TABLE `workflow_tasks` DROP FOREIGN KEY `workflow_tasks_fk_6`, DROP FOREIGN KEY `workflow_tasks_fk_7`");
        DB::statement("ALTER TABLE `workflow_tasks` DROP KEY `workflow_tasks_ix_6`, DROP KEY `workflow_tasks_ix_7`");
        DB::statement("ALTER TABLE `workflow_tasks` DROP COLUMN `delegation_reason`, DROP COLUMN `delegated_by`, DROP COLUMN `delegated_to_user_id`");

        // Drop tables
        Schema::dropIfExists('workflow_task_participants');
        Schema::dropIfExists('assignment_responses');
        Schema::dropIfExists('assignment_participants');
    }
};
