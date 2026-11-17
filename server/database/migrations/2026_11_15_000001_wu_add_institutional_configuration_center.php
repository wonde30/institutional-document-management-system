<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Numbering Policies
        if (! Schema::hasTable('numbering_policies')) {
            DB::statement(<<<'SQL'
CREATE TABLE `numbering_policies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `prefix` VARCHAR(32) NOT NULL DEFAULT 'WU',
  `include_unit_code` BOOLEAN NOT NULL DEFAULT FALSE,
  `include_category_code` BOOLEAN NOT NULL DEFAULT FALSE,
  `year_format` VARCHAR(20) NOT NULL DEFAULT 'calendar_year',
  `sequence_length` INT UNSIGNED NOT NULL DEFAULT 6,
  `reset_policy` VARCHAR(16) NOT NULL DEFAULT 'yearly',
  `active` BOOLEAN NOT NULL DEFAULT TRUE,
  `created_at` DATETIME(6) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numbering_policies_uq_1` (`code`),
  CONSTRAINT `numbering_policies_ck_1` CHECK (year_format IN ('calendar_year', 'ethiopian_year', 'none')),
  CONSTRAINT `numbering_policies_ck_2` CHECK (reset_policy IN ('yearly', 'monthly', 'never')),
  CONSTRAINT `numbering_policies_ck_3` CHECK (active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
SQL
            );
        }

        // 2. Routing Policies
        if (! Schema::hasTable('routing_policies')) {
            DB::statement(<<<'SQL'
CREATE TABLE `routing_policies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `from_unit_id` BIGINT UNSIGNED NULL,
  `to_unit_id` BIGINT UNSIGNED NOT NULL,
  `correspondence_type_id` BIGINT UNSIGNED NULL,
  `document_category_id` BIGINT UNSIGNED NULL,
  `priority_id` BIGINT UNSIGNED NULL,
  `default_assignee_role_id` BIGINT UNSIGNED NULL,
  `sla_hours` INT UNSIGNED NULL,
  `escalation_unit_id` BIGINT UNSIGNED NULL,
  `active` BOOLEAN NOT NULL DEFAULT TRUE,
  `description` TEXT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `routing_policies_uq_1` (`code`),
  CONSTRAINT `routing_policies_fk_1` FOREIGN KEY (`from_unit_id`) REFERENCES `organization_units` (`id`) ON DELETE CASCADE,
  CONSTRAINT `routing_policies_fk_2` FOREIGN KEY (`to_unit_id`) REFERENCES `organization_units` (`id`) ON DELETE CASCADE,
  CONSTRAINT `routing_policies_fk_3` FOREIGN KEY (`correspondence_type_id`) REFERENCES `correspondence_types` (`id`) ON DELETE SET NULL,
  CONSTRAINT `routing_policies_fk_4` FOREIGN KEY (`document_category_id`) REFERENCES `document_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `routing_policies_fk_5` FOREIGN KEY (`priority_id`) REFERENCES `priorities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `routing_policies_fk_6` FOREIGN KEY (`default_assignee_role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `routing_policies_fk_7` FOREIGN KEY (`escalation_unit_id`) REFERENCES `organization_units` (`id`) ON DELETE SET NULL,
  CONSTRAINT `routing_policies_ck_1` CHECK (active IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
SQL
            );
        }

        // 3. Workflow Definitions Extensions
        if (Schema::hasTable('workflow_definitions') && ! Schema::hasColumn('workflow_definitions', 'description')) {
            DB::statement(<<<'SQL'
ALTER TABLE `workflow_definitions`
  ADD COLUMN `description` TEXT NULL AFTER `name`,
  ADD COLUMN `document_category_id` BIGINT UNSIGNED NULL AFTER `description`,
  ADD CONSTRAINT `workflow_definitions_fk_1` FOREIGN KEY (`document_category_id`) REFERENCES `document_categories` (`id`) ON DELETE SET NULL;
SQL
            );
        }

        // 4. Workflow States Assignment Extensions
        if (Schema::hasTable('workflow_states') && ! Schema::hasColumn('workflow_states', 'assigned_role_id')) {
            DB::statement(<<<'SQL'
ALTER TABLE `workflow_states`
  ADD COLUMN `assigned_role_id` BIGINT UNSIGNED NULL AFTER `name`,
  ADD COLUMN `assigned_unit_id` BIGINT UNSIGNED NULL AFTER `assigned_role_id`,
  ADD COLUMN `sla_hours` INT UNSIGNED NULL AFTER `assigned_unit_id`,
  ADD CONSTRAINT `workflow_states_fk_2` FOREIGN KEY (`assigned_role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `workflow_states_fk_3` FOREIGN KEY (`assigned_unit_id`) REFERENCES `organization_units` (`id`) ON DELETE SET NULL;
SQL
            );
        }

        // 5. Retention Policies Category Extension
        if (Schema::hasTable('retention_policies') && ! Schema::hasColumn('retention_policies', 'document_category_id')) {
            DB::statement(<<<'SQL'
ALTER TABLE `retention_policies`
  ADD COLUMN `document_category_id` BIGINT UNSIGNED NULL AFTER `description`,
  ADD CONSTRAINT `retention_policies_fk_1` FOREIGN KEY (`document_category_id`) REFERENCES `document_categories` (`id`) ON DELETE SET NULL;
SQL
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('retention_policies') && Schema::hasColumn('retention_policies', 'document_category_id')) {
            DB::statement("ALTER TABLE `retention_policies` DROP FOREIGN KEY `retention_policies_fk_1`");
            DB::statement("ALTER TABLE `retention_policies` DROP COLUMN `document_category_id`");
        }

        if (Schema::hasTable('workflow_states') && Schema::hasColumn('workflow_states', 'assigned_role_id')) {
            DB::statement("ALTER TABLE `workflow_states` DROP FOREIGN KEY `workflow_states_fk_2`");
            DB::statement("ALTER TABLE `workflow_states` DROP FOREIGN KEY `workflow_states_fk_3`");
            DB::statement("ALTER TABLE `workflow_states` DROP COLUMN `sla_hours`, DROP COLUMN `assigned_unit_id`, DROP COLUMN `assigned_role_id`");
        }

        if (Schema::hasTable('workflow_definitions') && Schema::hasColumn('workflow_definitions', 'document_category_id')) {
            DB::statement("ALTER TABLE `workflow_definitions` DROP FOREIGN KEY `workflow_definitions_fk_1`");
            DB::statement("ALTER TABLE `workflow_definitions` DROP COLUMN `document_category_id`, DROP COLUMN `description`");
        }

        Schema::dropIfExists('routing_policies');
        Schema::dropIfExists('numbering_policies');
    }
};
