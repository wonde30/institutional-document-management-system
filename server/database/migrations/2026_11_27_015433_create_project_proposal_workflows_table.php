<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_proposal_workflows', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->unsignedBigInteger('proposal_id');
            $table->unsignedBigInteger('workflow_instance_id');

            $table->primary(['proposal_id', 'workflow_instance_id']);

            $table->unique('workflow_instance_id', 'project_proposal_workflows_uq_1');

            $table->foreign('proposal_id', 'project_proposal_workflows_fk_1')->references('id')->on('project_proposals');
            $table->foreign('workflow_instance_id', 'project_proposal_workflows_fk_2')->references('id')->on('workflow_instances');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_proposal_workflows');
    }
};
