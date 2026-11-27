<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restore_drills', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('backup_run_id');
            $table->dateTime('started_at', 6);
            $table->dateTime('completed_at', 6)->nullable();
            $table->string('status', 16);
            $table->text('notes')->nullable();

            $table->index('backup_run_id', 'restore_drills_ix_1');
            $table->index('status', 'restore_drills_status_idx');

            $table->foreign('backup_run_id', 'restore_drills_fk_1')->references('id')->on('backup_runs');
        });

        DB::statement('ALTER TABLE `restore_drills` ADD CONSTRAINT `restore_drills_ck_1` CHECK ((`status` in (\'running\',\'completed\',\'failed\')))');
    }

    public function down(): void
    {
        Schema::dropIfExists('restore_drills');
    }
};
