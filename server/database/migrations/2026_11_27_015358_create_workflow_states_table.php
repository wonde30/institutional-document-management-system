<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_states', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('workflow_version_id');
            $table->string('code', 64);
            $table->string('name', 160);
            $table->unsignedBigInteger('assigned_role_id')->nullable();
            $table->unsignedBigInteger('assigned_unit_id')->nullable();
            $table->unsignedInteger('sla_hours')->nullable();
            $table->boolean('is_initial')->default(false);
            $table->boolean('is_terminal')->default(false);
            $table->tinyInteger('initial_slot')->storedAs('(case when `is_initial` then 1 else NULL end)')->nullable();

            $table->index('assigned_role_id', 'workflow_states_fk_2');
            $table->index('assigned_unit_id', 'workflow_states_fk_3');
            $table->unique(['workflow_version_id', 'code'], 'workflow_states_uq_1');
            $table->unique(['workflow_version_id', 'initial_slot'], 'workflow_states_uq_2');

            $table->foreign('workflow_version_id', 'workflow_states_fk_1')->references('id')->on('workflow_versions');
            $table->foreign('assigned_role_id', 'workflow_states_fk_2')->references('id')->on('roles')->nullOnDelete();
            $table->foreign('assigned_unit_id', 'workflow_states_fk_3')->references('id')->on('organization_units')->nullOnDelete();
        });

        DB::statement('ALTER TABLE `workflow_states` ADD CONSTRAINT `workflow_states_ck_1` CHECK ((`is_initial` in (0,1)))');
        DB::statement('ALTER TABLE `workflow_states` ADD CONSTRAINT `workflow_states_ck_2` CHECK ((`is_terminal` in (0,1)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_states');
    }
};
