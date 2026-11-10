<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // MySQL DDL is not a multi-statement transaction. See partial-failure recovery runbook.
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE TABLE `research_records` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `research_type` VARCHAR(64) NOT NULL,
  `completed_on` DATE NULL,
  `external_selection_reference` VARCHAR(160) NULL,
  `recorded_by` BIGINT UNSIGNED NOT NULL,
  `recorded_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `research_records_uq_1` (`document_id`),
  KEY `research_records_ix_1` (`recorded_by`),
  CONSTRAINT `research_records_fk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`),
  CONSTRAINT `research_records_fk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `research_authors` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `research_id` BIGINT UNSIGNED NOT NULL,
  `staff_id` BIGINT UNSIGNED NULL,
  `external_name` VARCHAR(200) NULL,
  `author_order` SMALLINT UNSIGNED NOT NULL,
  `contribution` VARCHAR(200) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `research_authors_uq_1` (`research_id`, `author_order`),
  UNIQUE KEY `research_authors_uq_2` (`research_id`, `staff_id`),
  KEY `research_authors_ix_1` (`staff_id`),
  CONSTRAINT `research_authors_fk_1` FOREIGN KEY (`research_id`) REFERENCES `research_records` (`id`),
  CONSTRAINT `research_authors_fk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff_profiles` (`id`),
  CONSTRAINT `research_authors_ck_1` CHECK ((staff_id IS NOT NULL AND external_name IS NULL) OR (staff_id IS NULL AND external_name IS NOT NULL)),
  CONSTRAINT `research_authors_ck_2` CHECK (author_order > 0)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `project_types` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `description` TEXT NULL,
  `active` BOOLEAN NOT NULL DEFAULT TRUE,
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_types_uq_1` (`code`),
  CONSTRAINT `project_types_ck_1` CHECK (active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `project_proposals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `project_type_id` BIGINT UNSIGNED NOT NULL,
  `recorded_by` BIGINT UNSIGNED NOT NULL,
  `recorded_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_proposals_uq_1` (`document_id`),
  KEY `project_proposals_ix_1` (`project_type_id`),
  KEY `project_proposals_ix_2` (`recorded_by`),
  CONSTRAINT `project_proposals_fk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`),
  CONSTRAINT `project_proposals_fk_2` FOREIGN KEY (`project_type_id`) REFERENCES `project_types` (`id`),
  CONSTRAINT `project_proposals_fk_3` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `project_proposal_members` (
  `proposal_id` BIGINT UNSIGNED NOT NULL,
  `staff_id` BIGINT UNSIGNED NOT NULL,
  `member_role` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`proposal_id`, `staff_id`),
  KEY `project_proposal_members_ix_1` (`staff_id`),
  CONSTRAINT `project_proposal_members_fk_1` FOREIGN KEY (`proposal_id`) REFERENCES `project_proposals` (`id`),
  CONSTRAINT `project_proposal_members_fk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff_profiles` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `project_proposal_workflows` (
  `proposal_id` BIGINT UNSIGNED NOT NULL,
  `workflow_instance_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`proposal_id`, `workflow_instance_id`),
  UNIQUE KEY `project_proposal_workflows_uq_1` (`workflow_instance_id`),
  CONSTRAINT `project_proposal_workflows_fk_1` FOREIGN KEY (`proposal_id`) REFERENCES `project_proposals` (`id`),
  CONSTRAINT `project_proposal_workflows_fk_2` FOREIGN KEY (`workflow_instance_id`) REFERENCES `workflow_instances` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `projects` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `proposal_id` BIGINT UNSIGNED NOT NULL,
  `code` VARCHAR(64) NOT NULL,
  `starts_on` DATE NULL,
  `ends_on` DATE NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `projects_uq_1` (`proposal_id`),
  UNIQUE KEY `projects_uq_2` (`code`),
  CONSTRAINT `projects_fk_1` FOREIGN KEY (`proposal_id`) REFERENCES `project_proposals` (`id`),
  CONSTRAINT `projects_ck_1` CHECK (ends_on IS NULL OR starts_on IS NULL OR ends_on >= starts_on)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `project_progress_reports` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `period_start` DATE NOT NULL,
  `period_end` DATE NOT NULL,
  `submitted_by` BIGINT UNSIGNED NOT NULL,
  `submitted_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_progress_reports_uq_1` (`project_id`, `period_start`, `period_end`),
  UNIQUE KEY `project_progress_reports_uq_2` (`document_id`),
  KEY `project_progress_reports_ix_1` (`submitted_by`),
  CONSTRAINT `project_progress_reports_fk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  CONSTRAINT `project_progress_reports_fk_2` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`),
  CONSTRAINT `project_progress_reports_fk_3` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`),
  CONSTRAINT `project_progress_reports_ck_1` CHECK (period_end >= period_start)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `publications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `doi` VARCHAR(255) NULL,
  `publication_type` VARCHAR(64) NOT NULL,
  `venue` VARCHAR(255) NULL,
  `published_on` DATE NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `publications_uq_1` (`document_id`),
  UNIQUE KEY `publications_uq_2` (`doi`),
  CONSTRAINT `publications_fk_1` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `publication_authors` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `publication_id` BIGINT UNSIGNED NOT NULL,
  `staff_id` BIGINT UNSIGNED NULL,
  `external_name` VARCHAR(200) NULL,
  `author_order` SMALLINT UNSIGNED NOT NULL,
  `is_corresponding` BOOLEAN NOT NULL DEFAULT FALSE,
  PRIMARY KEY (`id`),
  UNIQUE KEY `publication_authors_uq_1` (`publication_id`, `author_order`),
  UNIQUE KEY `publication_authors_uq_2` (`publication_id`, `staff_id`),
  KEY `publication_authors_ix_1` (`staff_id`),
  CONSTRAINT `publication_authors_fk_1` FOREIGN KEY (`publication_id`) REFERENCES `publications` (`id`),
  CONSTRAINT `publication_authors_fk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff_profiles` (`id`),
  CONSTRAINT `publication_authors_ck_1` CHECK ((staff_id IS NOT NULL AND external_name IS NULL) OR (staff_id IS NULL AND external_name IS NOT NULL)),
  CONSTRAINT `publication_authors_ck_2` CHECK (author_order > 0),
  CONSTRAINT `publication_authors_ck_3` CHECK (is_corresponding IN (0,1))
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `research_publications` (
  `research_id` BIGINT UNSIGNED NOT NULL,
  `publication_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`research_id`, `publication_id`),
  KEY `research_publications_ix_1` (`publication_id`),
  CONSTRAINT `research_publications_fk_1` FOREIGN KEY (`research_id`) REFERENCES `research_records` (`id`),
  CONSTRAINT `research_publications_fk_2` FOREIGN KEY (`publication_id`) REFERENCES `publications` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );

        DB::statement(<<<'SQL'
CREATE TABLE `project_publications` (
  `project_id` BIGINT UNSIGNED NOT NULL,
  `publication_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`project_id`, `publication_id`),
  KEY `project_publications_ix_1` (`publication_id`),
  CONSTRAINT `project_publications_fk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`),
  CONSTRAINT `project_publications_fk_2` FOREIGN KEY (`publication_id`) REFERENCES `publications` (`id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_as_cs;
SQL
        );
    }
    public function down(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Destructive baseline rollback is restricted to local/testing.');
        }
        Schema::drop('project_publications');
        Schema::drop('research_publications');
        Schema::drop('publication_authors');
        Schema::drop('publications');
        Schema::drop('project_progress_reports');
        Schema::drop('projects');
        Schema::drop('project_proposal_workflows');
        Schema::drop('project_proposal_members');
        Schema::drop('project_proposals');
        Schema::drop('project_types');
        Schema::drop('research_authors');
        Schema::drop('research_records');
    }
};
