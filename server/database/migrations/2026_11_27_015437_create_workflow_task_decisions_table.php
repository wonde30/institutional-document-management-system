<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_task_decisions', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('decided_by');
            $table->string('decision', 16);
            $table->text('reason')->nullable();
            $table->dateTime('decided_at', 6);

            $table->index('decided_by', 'workflow_task_decisions_ix_1');
            $table->index(['decision', 'task_id'], 'workflow_task_decisions_ix_2');
            $table->index(['task_id', 'decision'], 'workflow_task_decisions_ix_3');
            $table->unique('task_id', 'workflow_task_decisions_uq_1');

            $table->foreign('task_id', 'workflow_task_decisions_fk_1')->references('id')->on('workflow_tasks');
            $table->foreign('decided_by', 'workflow_task_decisions_fk_2')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `workflow_task_decisions` ADD CONSTRAINT `workflow_task_decisions_ck_1` CHECK ((`decision` in (\'approve\',\'reject\',\'return\',\'cancel\')))');
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_task_decisions');
    }
};
