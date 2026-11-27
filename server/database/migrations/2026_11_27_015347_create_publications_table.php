<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publications', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->string('doi', 255)->nullable();
            $table->string('publication_type', 64);
            $table->string('venue', 255)->nullable();
            $table->date('published_on')->nullable();

            $table->unique('document_id', 'publications_uq_1');
            $table->unique('doi', 'publications_uq_2');

            $table->foreign('document_id', 'publications_fk_1')->references('id')->on('documents');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publications');
    }
};
