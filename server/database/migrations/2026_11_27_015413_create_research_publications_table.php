<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_publications', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->unsignedBigInteger('research_id');
            $table->unsignedBigInteger('publication_id');

            $table->primary(['research_id', 'publication_id']);

            $table->index('publication_id', 'research_publications_ix_1');

            $table->foreign('research_id', 'research_publications_fk_1')->references('id')->on('research_records');
            $table->foreign('publication_id', 'research_publications_fk_2')->references('id')->on('publications');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_publications');
    }
};
