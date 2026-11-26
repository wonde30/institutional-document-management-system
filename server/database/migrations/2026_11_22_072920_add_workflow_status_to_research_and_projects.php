<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add workflow_status to research_records
        DB::statement(<<<'SQL'
ALTER TABLE `research_records`
ADD COLUMN `workflow_status` VARCHAR(32) NOT NULL DEFAULT 'draft'
    AFTER `external_selection_reference`,
ADD CONSTRAINT `research_records_ck_workflow_status` 
    CHECK (`workflow_status` IN ('draft', 'submitted', 'under_review', 'approved', 'rejected', 'returned'))
SQL
        );

        // Add workflow_status to project_proposals
        DB::statement(<<<'SQL'
ALTER TABLE `project_proposals`
ADD COLUMN `workflow_status` VARCHAR(32) NOT NULL DEFAULT 'draft'
    AFTER `project_type_id`,
ADD CONSTRAINT `project_proposals_ck_workflow_status` 
    CHECK (`workflow_status` IN ('draft', 'submitted', 'under_review', 'approved', 'rejected', 'returned'))
SQL
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `research_records` DROP CONSTRAINT `research_records_ck_workflow_status`');
        DB::statement('ALTER TABLE `research_records` DROP COLUMN `workflow_status`');
        
        DB::statement('ALTER TABLE `project_proposals` DROP CONSTRAINT `project_proposals_ck_workflow_status`');
        DB::statement('ALTER TABLE `project_proposals` DROP COLUMN `workflow_status`');
    }
};
