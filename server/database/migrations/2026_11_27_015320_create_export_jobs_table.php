<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('export_jobs', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('requested_by');
            $table->string('report_type', 100);
            $table->json('filter_snapshot_json');
            $table->string('status', 16)->default('pending');
            $table->unsignedBigInteger('output_file_id')->nullable();
            $table->dateTime('requested_at', 6);
            $table->dateTime('completed_at', 6)->nullable();
            $table->dateTime('expires_at', 6)->nullable();
            $table->string('error_code', 100)->nullable();

            $table->index(['requested_by', 'requested_at'], 'export_jobs_ix_1');
            $table->index(['status', 'requested_at'], 'export_jobs_ix_2');
            $table->index('output_file_id', 'export_jobs_ix_3');

            $table->foreign('requested_by', 'export_jobs_fk_1')->references('id')->on('users');
            $table->foreign('output_file_id', 'export_jobs_fk_2')->references('id')->on('file_objects');
        });

        DB::statement('ALTER TABLE `export_jobs` ADD CONSTRAINT `export_jobs_ck_1` CHECK ((`status` in (\'pending\',\'running\',\'completed\',\'failed\',\'expired\')))');
    }

    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
    }
};
