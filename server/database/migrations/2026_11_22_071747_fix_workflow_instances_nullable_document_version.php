<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Make document_version_id nullable in workflow_instances to support
     * workflows that may be started without a specific version locked yet.
     * Only add columns that don't already exist.
     */
    public function up(): void
    {
        // Make document_version_id nullable
        DB::statement(<<<'SQL'
ALTER TABLE `workflow_instances`
  MODIFY COLUMN `document_version_id` BIGINT UNSIGNED NULL;
SQL
        );

        // Check and add branch_key to workflow_tasks if not exists
        if (!Schema::hasColumn('workflow_tasks', 'branch_key')) {
            DB::statement(<<<'SQL'
ALTER TABLE `workflow_tasks`
  ADD COLUMN `branch_key` VARCHAR(64) NULL AFTER `state_id`,
  ADD KEY `workflow_tasks_ix_8` (`instance_id`, `branch_key`, `closed_at`);
SQL
            );
        }

        // Check and add workflow_states columns if not exist
        if (!Schema::hasColumn('workflow_states', 'assigned_unit_id')) {
            DB::statement(<<<'SQL'
ALTER TABLE `workflow_states`
  ADD COLUMN `assigned_unit_id` BIGINT UNSIGNED NULL AFTER `is_terminal`,
  ADD COLUMN `assigned_role_id` BIGINT UNSIGNED NULL AFTER `assigned_unit_id`,
  ADD COLUMN `sla_hours` INT UNSIGNED NULL AFTER `assigned_role_id`,
  ADD KEY `workflow_states_ix_1` (`assigned_unit_id`),
  ADD KEY `workflow_states_ix_2` (`assigned_role_id`),
  ADD CONSTRAINT `workflow_states_fk_1` FOREIGN KEY (`assigned_unit_id`) REFERENCES `organization_units` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `workflow_states_fk_2` FOREIGN KEY (`assigned_role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL;
SQL
            );
        }

        // Check and add workflow_transitions columns if not exist
        if (!Schema::hasColumn('workflow_transitions', 'fork_state_ids')) {
            DB::statement(<<<'SQL'
ALTER TABLE `workflow_transitions`
  ADD COLUMN `fork_state_ids` JSON NULL AFTER `quorum_percentage`,
  ADD COLUMN `join_policy` VARCHAR(20) NULL AFTER `fork_state_ids`,
  ADD CONSTRAINT `workflow_transitions_ck_6` CHECK (`join_policy` IS NULL OR `join_policy` IN ('all_branches', 'any_branch'));
SQL
            );
        } else {
            $existingCheck = DB::select(
                "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS 
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'workflow_transitions' 
                 AND CONSTRAINT_TYPE = 'CHECK' AND CONSTRAINT_NAME = 'workflow_transitions_ck_6'"
            );
            if (empty($existingCheck)) {
                DB::statement("ALTER TABLE `workflow_transitions` ADD CONSTRAINT `workflow_transitions_ck_6` CHECK (`join_policy` IS NULL OR `join_policy` IN ('all_branches', 'any_branch'))");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop workflow_transitions_ck_6 if it exists
        $constraints = DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS 
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'workflow_transitions' 
             AND CONSTRAINT_TYPE = 'CHECK' AND (CONSTRAINT_NAME = 'workflow_transitions_ck_6' OR CONSTRAINT_NAME LIKE '%ck%6%')"
        );
        foreach ($constraints as $constraint) {
            try {
                DB::statement("ALTER TABLE `workflow_transitions` DROP CHECK `{$constraint->CONSTRAINT_NAME}`");
            } catch (\Throwable $e) {
                // Ignore if already dropped
            }
        }

        // Revert document_version_id to NOT NULL
        DB::statement(<<<'SQL'
ALTER TABLE `workflow_instances`
  MODIFY COLUMN `document_version_id` BIGINT UNSIGNED NOT NULL;
SQL
        );
    }
};
