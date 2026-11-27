<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_records', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->string('research_type', 64);
            $table->date('completed_on')->nullable();
            $table->string('external_selection_reference', 160)->nullable();
            $table->string('workflow_status', 32)->default('draft');
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->dateTime('submitted_at', 6)->nullable();
            $table->text('decision_remarks')->nullable();
            $table->unsignedBigInteger('recorded_by');
            $table->dateTime('recorded_at', 6);

            $table->index('approved_by', 'research_records_approved_by_fk');
            $table->index('recorded_by', 'research_records_ix_1');
            $table->index('rejected_by', 'research_records_rejected_by_fk');
            $table->index('reviewed_by', 'research_records_reviewed_by_fk');
            $table->index('submitted_by', 'research_records_submitted_by_fk');
            $table->unique('document_id', 'research_records_uq_1');
            $table->index('workflow_status', 'research_records_workflow_status_idx');

            $table->foreign('approved_by', 'research_records_approved_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('document_id', 'research_records_fk_1')->references('id')->on('documents');
            $table->foreign('recorded_by', 'research_records_fk_2')->references('id')->on('users');
            $table->foreign('rejected_by', 'research_records_rejected_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('reviewed_by', 'research_records_reviewed_by_fk')->references('id')->on('users')->nullOnDelete();
            $table->foreign('submitted_by', 'research_records_submitted_by_fk')->references('id')->on('users')->nullOnDelete();
        });

        DB::statement('ALTER TABLE `research_records` ADD CONSTRAINT `research_records_ck_workflow_status` CHECK ((`workflow_status` in (\'draft\',\'submitted\',\'under_review\',\'approved\',\'rejected\',\'returned\')))');
    }

    public function down(): void
    {
        Schema::dropIfExists('research_records');
    }
};
