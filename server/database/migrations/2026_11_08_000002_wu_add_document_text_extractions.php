<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `document_text_extractions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_version_id` BIGINT UNSIGNED NOT NULL,
  `file_object_id` BIGINT UNSIGNED NOT NULL,
  `raw_text` LONGTEXT NOT NULL,
  `verified_text` LONGTEXT NULL,
  `searchable_text` LONGTEXT NOT NULL,
  `language_detected` VARCHAR(16) NOT NULL DEFAULT 'amh+eng',
  `confidence_score` DECIMAL(5,2) NULL,
  `ocr_engine` VARCHAR(64) NOT NULL,
  `extracted_at` DATETIME(6) NOT NULL,
  `verified_by` BIGINT UNSIGNED NULL,
  `verified_at` DATETIME(6) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_text_extractions_uq_1` (`document_version_id`, `file_object_id`),
  KEY `document_text_extractions_ix_1` (`file_object_id`),
  KEY `document_text_extractions_ix_2` (`verified_by`),
  FULLTEXT KEY `document_text_extractions_fx_1` (`searchable_text`),
  CONSTRAINT `document_text_extractions_fk_1` FOREIGN KEY (`document_version_id`) REFERENCES `document_versions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `document_text_extractions_fk_2` FOREIGN KEY (`file_object_id`) REFERENCES `file_objects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `document_text_extractions_fk_3` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `document_text_extractions_ck_1` CHECK (`confidence_score` IS NULL OR (`confidence_score` >= 0 AND `confidence_score` <= 100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_as_cs;
SQL
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('document_text_extractions');
    }
};
