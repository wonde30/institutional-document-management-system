<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_task_participants', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('user_id');
            $table->string('participation_role', 24)->default('reviewer');
            $table->string('status', 16)->default('pending');
            $table->dateTime('assigned_at', 6);
            $table->dateTime('responded_at', 6)->nullable();

            $table->index(['user_id', 'status'], 'workflow_task_participants_ix_1');
            $table->unique(['task_id', 'user_id'], 'workflow_task_participants_uq_1');

            $table->foreign('task_id', 'workflow_task_participants_fk_1')->references('id')->on('workflow_tasks')->cascadeOnDelete();
            $table->foreign('user_id', 'workflow_task_participants_fk_2')->references('id')->on('users')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE `workflow_task_participants` ADD CONSTRAINT `workflow_task_participants_ck_1` CHECK ((`participation_role` in (\'lead_reviewer\',\'reviewer\',\'contributor\',\'observer\')))');
        DB::statement('ALTER TABLE `workflow_task_participants` ADD CONSTRAINT `workflow_task_participants_ck_2` CHECK ((`status` in (\'pending\',\'completed\',\'excused\')))');
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_task_participants');
    }
};
