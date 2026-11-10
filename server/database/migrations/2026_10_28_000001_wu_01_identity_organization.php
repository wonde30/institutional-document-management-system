<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // MySQL DDL is not a multi-statement transaction. See partial-failure recovery runbook.
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `unit_types` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `description` TEXT NULL,
  `active` BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unit_types_uq_1` (`code`),
  CONSTRAINT `unit_types_ck_1` CHECK (active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(160) NOT NULL,
  `email` VARCHAR(254) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'inactive',
  `email_verified_at` DATETIME(6) NULL,
  `remember_token` VARCHAR(100) NULL,
  `authz_version` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME(6) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_uq_1` (`email`),
  KEY `users_ix_1` (`status`),
  CONSTRAINT `users_ck_1` CHECK (status IN ('active','inactive','locked'))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `organization_units` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` BIGINT UNSIGNED NULL,
  `unit_type_id` BIGINT UNSIGNED NOT NULL,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `active` BOOLEAN NOT NULL DEFAULT TRUE,
  `created_at` DATETIME(6) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `organization_units_uq_1` (`code`),
  KEY `organization_units_ix_1` (`parent_id`, `active`),
  KEY `organization_units_ix_2` (`unit_type_id`),
  CONSTRAINT `organization_units_fk_1` FOREIGN KEY (`parent_id`) REFERENCES `organization_units` (`id`),
  CONSTRAINT `organization_units_fk_2` FOREIGN KEY (`unit_type_id`) REFERENCES `unit_types` (`id`),
  CONSTRAINT `organization_units_ck_1` CHECK (active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `organization_memberships` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `unit_id` BIGINT UNSIGNED NOT NULL,
  `position_title` VARCHAR(160) NULL,
  `starts_at` DATETIME(6) NOT NULL,
  `ends_at` DATETIME(6) NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `organization_memberships_uq_1` (`user_id`, `unit_id`, `starts_at`),
  KEY `organization_memberships_ix_1` (`unit_id`, `ends_at`, `user_id`),
  KEY `organization_memberships_ix_2` (`created_by`),
  CONSTRAINT `organization_memberships_fk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `organization_memberships_fk_2` FOREIGN KEY (`unit_id`) REFERENCES `organization_units` (`id`),
  CONSTRAINT `organization_memberships_fk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `organization_memberships_ck_1` CHECK (ends_at IS NULL OR ends_at > starts_at)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `primary_memberships` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `membership_id` BIGINT UNSIGNED NOT NULL,
  `selected_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `primary_memberships_uq_1` (`membership_id`),
  CONSTRAINT `primary_memberships_fk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `primary_memberships_fk_2` FOREIGN KEY (`membership_id`) REFERENCES `organization_memberships` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );
    }
    public function down(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Destructive baseline rollback is restricted to local/testing.');
        }
        Schema::drop('primary_memberships');
        Schema::drop('organization_memberships');
        Schema::drop('organization_units');
        Schema::drop('users');
        Schema::drop('unit_types');
    }
};
