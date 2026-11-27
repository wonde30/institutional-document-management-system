<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_comments', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('author_id');
            $table->text('body');
            $table->dateTime('created_at', 6);
            $table->dateTime('edited_at', 6)->nullable();

            $table->index(['document_id', 'created_at', 'id'], 'document_comments_ix_1');
            $table->index('parent_id', 'document_comments_ix_2');
            $table->index('author_id', 'document_comments_ix_3');

            $table->foreign('document_id', 'document_comments_fk_1')->references('id')->on('documents');
            $table->foreign('parent_id', 'document_comments_fk_2')->references('id')->on('document_comments');
            $table->foreign('author_id', 'document_comments_fk_3')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_comments');
    }
};
