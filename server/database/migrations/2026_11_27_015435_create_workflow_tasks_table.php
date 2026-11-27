<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_tasks', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('instance_id');
            $table->unsignedBigInteger('state_id');
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->unsignedBigInteger('assigned_unit_id')->nullable();
            $table->unsignedBigInteger('required_role_id')->nullable();
            $table->string('branch_key', 64)->nullable();
            $table->unsignedBigInteger('delegated_to_user_id')->nullable();
            $table->unsignedBigInteger('delegated_by')->nullable();
            $table->text('delegation_reason')->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('due_at', 6)->nullable();
            $table->dateTime('closed_at', 6)->nullable();

            $table->index(['assigned_user_id', 'closed_at', 'due_at'], 'workflow_tasks_ix_1');
            $table->index(['assigned_unit_id', 'closed_at', 'due_at'], 'workflow_tasks_ix_2');
            $table->index(['instance_id', 'closed_at'], 'workflow_tasks_ix_3');
            $table->index('state_id', 'workflow_tasks_ix_4');
            $table->index('required_role_id', 'workflow_tasks_ix_5');
            $table->index(['delegated_to_user_id', 'closed_at'], 'workflow_tasks_ix_6');
            $table->index('delegated_by', 'workflow_tasks_ix_7');

            $table->foreign('instance_id', 'workflow_tasks_fk_1')->references('id')->on('workflow_instances');
            $table->foreign('state_id', 'workflow_tasks_fk_2')->references('id')->on('workflow_states');
            $table->foreign('assigned_user_id', 'workflow_tasks_fk_3')->references('id')->on('users');
            $table->foreign('assigned_unit_id', 'workflow_tasks_fk_4')->references('id')->on('organization_units');
            $table->foreign('required_role_id', 'workflow_tasks_fk_5')->references('id')->on('roles');
            $table->foreign('delegated_to_user_id', 'workflow_tasks_fk_6')->references('id')->on('users')->nullOnDelete();
            $table->foreign('delegated_by', 'workflow_tasks_fk_7')->references('id')->on('users')->nullOnDelete();
        });

        DB::statement('ALTER TABLE `workflow_tasks` ADD CONSTRAINT `workflow_tasks_ck_1` CHECK (((`assigned_user_id` is not null) or (`assigned_unit_id` is not null)))');
        DB::statement('ALTER TABLE `workflow_tasks` ADD CONSTRAINT `workflow_tasks_ck_2` CHECK (((`due_at` is null) or (`due_at` >= `created_at`)))');
        DB::statement('ALTER TABLE `workflow_tasks` ADD CONSTRAINT `workflow_tasks_ck_3` CHECK (((`closed_at` is null) or (`closed_at` >= `created_at`)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_tasks');
    }
};
