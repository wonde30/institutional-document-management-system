<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // MySQL DDL is not a multi-statement transaction. See partial-failure recovery runbook.
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `permissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `description` TEXT NULL,
  `active` BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_uq_1` (`code`),
  CONSTRAINT `permissions_ck_1` CHECK (active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `roles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `description` TEXT NULL,
  `active` BOOLEAN NOT NULL DEFAULT TRUE,
  `is_system` BOOLEAN NOT NULL DEFAULT FALSE,
  `created_at` DATETIME(6) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_uq_1` (`code`),
  CONSTRAINT `roles_ck_1` CHECK (active IN (0,1)),
  CONSTRAINT `roles_ck_2` CHECK (is_system IN (0,1))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `role_permissions` (
  `role_id` BIGINT UNSIGNED NOT NULL,
  `permission_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  KEY `role_permissions_ix_1` (`permission_id`),
  CONSTRAINT `role_permissions_fk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `role_permissions_fk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `authorization_scopes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kind` VARCHAR(16) NOT NULL,
  `unit_id` BIGINT UNSIGNED NULL,
  `unit_key` BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(unit_id,0)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `authorization_scopes_uq_1` (`kind`, `unit_key`),
  KEY `authorization_scopes_ix_1` (`unit_id`),
  CONSTRAINT `authorization_scopes_fk_1` FOREIGN KEY (`unit_id`) REFERENCES `organization_units` (`id`),
  CONSTRAINT `authorization_scopes_ck_1` CHECK ((kind = 'global' AND unit_id IS NULL) OR (kind IN ('unit','subtree') AND unit_id IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `user_roles` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `role_id` BIGINT UNSIGNED NOT NULL,
  `scope_id` BIGINT UNSIGNED NOT NULL,
  `granted_by` BIGINT UNSIGNED NOT NULL,
  `granted_at` DATETIME(6) NOT NULL,
  `expires_at` DATETIME(6) NULL,
  PRIMARY KEY (`user_id`, `role_id`, `scope_id`),
  KEY `user_roles_ix_1` (`scope_id`, `user_id`),
  KEY `user_roles_ix_2` (`role_id`),
  KEY `user_roles_ix_3` (`granted_by`),
  CONSTRAINT `user_roles_fk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `user_roles_fk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `user_roles_fk_3` FOREIGN KEY (`scope_id`) REFERENCES `authorization_scopes` (`id`),
  CONSTRAINT `user_roles_fk_4` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`),
  CONSTRAINT `user_roles_ck_1` CHECK (expires_at IS NULL OR expires_at > granted_at)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `user_permissions` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `scope_id` BIGINT UNSIGNED NOT NULL,
  `granted_by` BIGINT UNSIGNED NOT NULL,
  `granted_at` DATETIME(6) NOT NULL,
  `expires_at` DATETIME(6) NULL,
  PRIMARY KEY (`user_id`, `permission_id`, `scope_id`),
  KEY `user_permissions_ix_1` (`scope_id`, `user_id`),
  KEY `user_permissions_ix_2` (`permission_id`),
  KEY `user_permissions_ix_3` (`granted_by`),
  CONSTRAINT `user_permissions_fk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `user_permissions_fk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`),
  CONSTRAINT `user_permissions_fk_3` FOREIGN KEY (`scope_id`) REFERENCES `authorization_scopes` (`id`),
  CONSTRAINT `user_permissions_fk_4` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`),
  CONSTRAINT `user_permissions_ck_1` CHECK (expires_at IS NULL OR expires_at > granted_at)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );
    }
    public function down(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Destructive baseline rollback is restricted to local/testing.');
        }
        Schema::drop('user_permissions');
        Schema::drop('user_roles');
        Schema::drop('authorization_scopes');
        Schema::drop('role_permissions');
        Schema::drop('roles');
        Schema::drop('permissions');
    }
};
