<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_documents', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('record_type_id');

            $table->primary(['staff_id', 'document_id', 'record_type_id']);

            $table->index('document_id', 'staff_documents_ix_1');
            $table->index('record_type_id', 'staff_documents_ix_2');

            $table->foreign('staff_id', 'staff_documents_fk_1')->references('id')->on('staff_profiles');
            $table->foreign('document_id', 'staff_documents_fk_2')->references('id')->on('documents');
            $table->foreign('record_type_id', 'staff_documents_fk_3')->references('id')->on('hr_record_types');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_documents');
    }
};
