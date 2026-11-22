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
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove fork and join columns from workflow_transitions if they exist
        if (Schema::hasColumn('workflow_transitions', 'fork_state_ids')) {
            DB::statement("ALTER TABLE `workflow_transitions` DROP CONSTRAINT IF EXISTS `workflow_transitions_ck_6`");
            DB::statement("ALTER TABLE `workflow_transitions` DROP COLUMN `join_policy`, DROP COLUMN `fork_state_ids`");
        }

        // Remove state assignment columns from workflow_states if they exist
        if (Schema::hasColumn('workflow_states', 'assigned_unit_id')) {
            DB::statement("ALTER TABLE `workflow_states` DROP FOREIGN KEY IF EXISTS `workflow_states_fk_1`");
            DB::statement("ALTER TABLE `workflow_states` DROP FOREIGN KEY IF EXISTS `workflow_states_fk_2`");
            DB::statement("ALTER TABLE `workflow_states` DROP KEY IF EXISTS `workflow_states_ix_1`");
            DB::statement("ALTER TABLE `workflow_states` DROP KEY IF EXISTS `workflow_states_ix_2`");
            DB::statement("ALTER TABLE `workflow_states` DROP COLUMN `sla_hours`, DROP COLUMN `assigned_role_id`, DROP COLUMN `assigned_unit_id`");
        }

        // Remove branch_key from workflow_tasks if it exists
        if (Schema::hasColumn('workflow_tasks', 'branch_key')) {
            DB::statement("ALTER TABLE `workflow_tasks` DROP KEY IF EXISTS `workflow_tasks_ix_8`");
            DB::statement("ALTER TABLE `workflow_tasks` DROP COLUMN `branch_key`");
        }

        // Revert document_version_id to NOT NULL
        DB::statement(<<<'SQL'
ALTER TABLE `workflow_instances`
  MODIFY COLUMN `document_version_id` BIGINT UNSIGNED NOT NULL;
SQL
        );
    }
};
