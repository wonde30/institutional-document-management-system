<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_tags', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('tag_id');

            $table->primary(['document_id', 'tag_id']);

            $table->index('tag_id', 'document_tags_ix_1');

            $table->foreign('document_id', 'document_tags_fk_1')->references('id')->on('documents');
            $table->foreign('tag_id', 'document_tags_fk_2')->references('id')->on('tags');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_tags');
    }
};
