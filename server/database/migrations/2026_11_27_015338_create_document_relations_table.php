<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_relations', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->unsignedBigInteger('source_id');
            $table->unsignedBigInteger('target_id');
            $table->unsignedBigInteger('relation_type_id');

            $table->primary(['source_id', 'target_id', 'relation_type_id']);

            $table->index('target_id', 'document_relations_ix_1');
            $table->index('relation_type_id', 'document_relations_ix_2');
            $table->index(['source_id', 'relation_type_id', 'target_id'], 'document_relations_ix_3');

            $table->foreign('source_id', 'document_relations_fk_1')->references('id')->on('documents');
            $table->foreign('target_id', 'document_relations_fk_2')->references('id')->on('documents');
            $table->foreign('relation_type_id', 'document_relations_fk_3')->references('id')->on('document_relation_types');
        });

        DB::statement('ALTER TABLE `document_relations` ADD CONSTRAINT `document_relations_ck_1` CHECK ((`source_id` <> `target_id`))');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_relations');
    }
};
