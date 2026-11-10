<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `document_templates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT NULL,
  `category_id` BIGINT UNSIGNED NOT NULL,
  `owner_unit_id` BIGINT UNSIGNED NULL,
  `visibility_scope` VARCHAR(20) NOT NULL DEFAULT 'global',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_templates_uq_1` (`code`),
  KEY `document_templates_ix_1` (`owner_unit_id`, `is_active`),
  KEY `document_templates_ix_2` (`category_id`),
  KEY `document_templates_ix_3` (`created_by`),
  CONSTRAINT `document_templates_fk_1` FOREIGN KEY (`category_id`) REFERENCES `document_categories` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `document_templates_fk_2` FOREIGN KEY (`owner_unit_id`) REFERENCES `organization_units` (`id`) ON DELETE SET NULL,
  CONSTRAINT `document_templates_fk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `document_templates_ck_1` CHECK (`is_active` IN (0, 1)),
  CONSTRAINT `document_templates_ck_2` CHECK (`visibility_scope` IN ('global', 'unit_restricted'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `template_versions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `template_id` BIGINT UNSIGNED NOT NULL,
  `version_number` INT UNSIGNED NOT NULL,
  `schema_definition` JSON NOT NULL,
  `body_template_html` MEDIUMTEXT NOT NULL,
  `header_asset_key` VARCHAR(255) NULL,
  `footer_asset_key` VARCHAR(255) NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'draft',
  `published_at` DATETIME(6) NULL,
  `published_by` BIGINT UNSIGNED NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `template_versions_uq_1` (`template_id`, `version_number`),
  KEY `template_versions_ix_1` (`status`, `published_at`),
  KEY `template_versions_ix_2` (`created_by`),
  KEY `template_versions_ix_3` (`published_by`),
  CONSTRAINT `template_versions_fk_1` FOREIGN KEY (`template_id`) REFERENCES `document_templates` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `template_versions_fk_2` FOREIGN KEY (`published_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `template_versions_fk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `template_versions_ck_1` CHECK (`version_number` > 0),
  CONSTRAINT `template_versions_ck_2` CHECK (`status` IN ('draft', 'published', 'retired')),
  CONSTRAINT `template_versions_ck_3` CHECK (((`status` = 'published' AND `published_at` IS NOT NULL) OR (`status` != 'published')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
SQL
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('template_versions');
        Schema::dropIfExists('document_templates');
    }
};
