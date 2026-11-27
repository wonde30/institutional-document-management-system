<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->string('provider_job_reference', 191)->nullable();
            $table->dateTime('started_at', 6);
            $table->dateTime('completed_at', 6)->nullable();
            $table->string('status', 16);
            $table->string('manifest_reference', 255)->nullable();
            $table->string('verification_status', 16)->default('unverified');

            $table->index('started_at', 'backup_runs_ix_1');
            $table->index('status', 'backup_runs_status_idx');
        });

        DB::statement('ALTER TABLE `backup_runs` ADD CONSTRAINT `backup_runs_ck_1` CHECK ((`status` in (\'running\',\'completed\',\'failed\')))');
        DB::statement('ALTER TABLE `backup_runs` ADD CONSTRAINT `backup_runs_ck_2` CHECK ((`verification_status` in (\'unverified\',\'verified\',\'failed\')))');
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
