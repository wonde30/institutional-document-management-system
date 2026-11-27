<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_proposal_members', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->unsignedBigInteger('proposal_id');
            $table->unsignedBigInteger('staff_id');
            $table->string('member_role', 100);

            $table->primary(['proposal_id', 'staff_id']);

            $table->index('staff_id', 'project_proposal_members_ix_1');

            $table->foreign('proposal_id', 'project_proposal_members_fk_1')->references('id')->on('project_proposals');
            $table->foreign('staff_id', 'project_proposal_members_fk_2')->references('id')->on('staff_profiles');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_proposal_members');
    }
};
