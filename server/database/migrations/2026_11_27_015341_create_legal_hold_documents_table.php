<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_hold_documents', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->unsignedBigInteger('hold_id');
            $table->unsignedBigInteger('document_id');

            $table->primary(['hold_id', 'document_id']);

            $table->index('document_id', 'legal_hold_documents_ix_1');

            $table->foreign('hold_id', 'legal_hold_documents_fk_1')->references('id')->on('legal_holds');
            $table->foreign('document_id', 'legal_hold_documents_fk_2')->references('id')->on('documents');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_hold_documents');
    }
};
