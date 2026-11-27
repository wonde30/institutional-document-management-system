<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_publications', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('publication_id');

            $table->primary(['project_id', 'publication_id']);

            $table->index('publication_id', 'project_publications_ix_1');

            $table->foreign('project_id', 'project_publications_fk_1')->references('id')->on('projects');
            $table->foreign('publication_id', 'project_publications_fk_2')->references('id')->on('publications');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_publications');
    }
};
