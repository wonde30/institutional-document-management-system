<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_progress_reports', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('document_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('submitted_by');
            $table->dateTime('submitted_at', 6);

            $table->index('submitted_by', 'project_progress_reports_ix_1');
            $table->unique(['project_id', 'period_start', 'period_end'], 'project_progress_reports_uq_1');
            $table->unique('document_id', 'project_progress_reports_uq_2');

            $table->foreign('project_id', 'project_progress_reports_fk_1')->references('id')->on('projects');
            $table->foreign('document_id', 'project_progress_reports_fk_2')->references('id')->on('documents');
            $table->foreign('submitted_by', 'project_progress_reports_fk_3')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `project_progress_reports` ADD CONSTRAINT `project_progress_reports_ck_1` CHECK ((`period_end` >= `period_start`))');
    }

    public function down(): void
    {
        Schema::dropIfExists('project_progress_reports');
    }
};
