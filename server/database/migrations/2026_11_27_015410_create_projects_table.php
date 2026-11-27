<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('proposal_id');
            $table->string('code', 64);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();

            $table->unique('proposal_id', 'projects_uq_1');
            $table->unique('code', 'projects_uq_2');

            $table->foreign('proposal_id', 'projects_fk_1')->references('id')->on('project_proposals');
        });

        DB::statement('ALTER TABLE `projects` ADD CONSTRAINT `projects_ck_1` CHECK (((`ends_on` is null) or (`starts_on` is null) or (`ends_on` >= `starts_on`)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
