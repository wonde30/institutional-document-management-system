<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_transitions', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('from_state_id');
            $table->unsignedBigInteger('to_state_id');
            $table->string('action_code', 64);
            $table->unsignedBigInteger('permission_id');
            $table->boolean('requires_reason')->default(false);
            $table->boolean('requires_signature')->default(false);
            $table->boolean('prohibit_self_approval')->default(true);
            $table->string('approval_policy', 20)->default('single');
            $table->string('join_policy', 32)->nullable();
            $table->json('fork_state_ids')->nullable();
            $table->unsignedInteger('quorum_count')->nullable();
            $table->decimal('quorum_percentage', 5, 2)->nullable();

            $table->index('to_state_id', 'workflow_transitions_ix_1');
            $table->index('permission_id', 'workflow_transitions_ix_2');
            $table->unique(['from_state_id', 'action_code'], 'workflow_transitions_uq_1');

            $table->foreign('from_state_id', 'workflow_transitions_fk_1')->references('id')->on('workflow_states');
            $table->foreign('to_state_id', 'workflow_transitions_fk_2')->references('id')->on('workflow_states');
            $table->foreign('permission_id', 'workflow_transitions_fk_3')->references('id')->on('permissions');
        });

        DB::statement('ALTER TABLE `workflow_transitions` ADD CONSTRAINT `workflow_transitions_ck_1` CHECK ((`from_state_id` <> `to_state_id`))');
        DB::statement('ALTER TABLE `workflow_transitions` ADD CONSTRAINT `workflow_transitions_ck_2` CHECK ((`requires_reason` in (0,1)))');
        DB::statement('ALTER TABLE `workflow_transitions` ADD CONSTRAINT `workflow_transitions_ck_3` CHECK ((`requires_signature` in (0,1)))');
        DB::statement('ALTER TABLE `workflow_transitions` ADD CONSTRAINT `workflow_transitions_ck_4` CHECK ((`prohibit_self_approval` in (0,1)))');
        DB::statement('ALTER TABLE `workflow_transitions` ADD CONSTRAINT `workflow_transitions_ck_5` CHECK ((`approval_policy` in (\'single\',\'all_required\',\'any_required\',\'majority\',\'quorum\')))');
        DB::statement('ALTER TABLE `workflow_transitions` ADD CONSTRAINT `workflow_transitions_ck_6` CHECK (((`join_policy` is null) or (`join_policy` in (\'all_branches\',\'any_branch\'))))');
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_transitions');
    }
};
