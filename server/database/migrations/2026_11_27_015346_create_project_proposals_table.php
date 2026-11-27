<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_proposals', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('project_type_id');
            $table->string('workflow_status', 32)->default('draft');
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->dateTime('submitted_at', 6)->nullable();
            $table->text('decision_remarks')->nullable();
            $table->unsignedBigInteger('recorded_by');
            $table->dateTime('recorded_at', 6);

            $table->index('approved_by', 'project_proposals_approved_by_fk');
            $table->index('project_type_id', 'project_proposals_ix_1');
            $table->index('recorded_by', 'project_proposals_ix_2');
            $table->index('rejected_by', 'project_proposals_rejected_by_fk');
            $table->index('reviewed_by', 'project_proposals_reviewed_by_fk');
            $table->index('submitted_by', 'project_proposals_submitted_by_fk');
            $table->unique('document_id', 'project_proposals_uq_1');
            $table->index('workflow_status', 'project_proposals_workflow_status_idx');

            $table->foreign('approved_by', 'project_proposals_approved_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('document_id', 'project_proposals_fk_1')->references('id')->on('documents');
            $table->foreign('project_type_id', 'project_proposals_fk_2')->references('id')->on('project_types');
            $table->foreign('recorded_by', 'project_proposals_fk_3')->references('id')->on('users');
            $table->foreign('rejected_by', 'project_proposals_rejected_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reviewed_by', 'project_proposals_reviewed_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('submitted_by', 'project_proposals_submitted_by_fk')->references('id')->on('users')->nullOnDelete();
        });

        DB::statement('ALTER TABLE `project_proposals` ADD CONSTRAINT `project_proposals_ck_workflow_status` CHECK ((`workflow_status` in (\'draft\',\'submitted\',\'under_review\',\'approved\',\'rejected\',\'returned\')))');
    }

    public function down(): void
    {
        Schema::dropIfExists('project_proposals');
    }
};
