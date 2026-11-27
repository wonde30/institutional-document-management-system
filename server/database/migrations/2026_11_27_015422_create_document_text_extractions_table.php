<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_text_extractions', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('document_version_id');
            $table->unsignedBigInteger('file_object_id');
            $table->longText('raw_text')->nullable();
            $table->longText('verified_text')->nullable();
            $table->longText('searchable_text')->nullable();
            $table->string('language_detected', 16)->default('amh+eng');
            $table->decimal('confidence_score', 5, 2)->nullable();
            $table->string('processing_status', 24)->default('completed');
            $table->text('error_details')->nullable();
            $table->string('ocr_engine', 64);
            $table->dateTime('extracted_at', 6);
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->dateTime('verified_at', 6)->nullable();

            $table->fullText('searchable_text', 'document_text_extractions_fx_1');
            $table->index('file_object_id', 'document_text_extractions_ix_1');
            $table->index('verified_by', 'document_text_extractions_ix_2');
            $table->unique(['document_version_id', 'file_object_id'], 'document_text_extractions_uq_1');

            $table->foreign('document_version_id', 'document_text_extractions_fk_1')->references('id')->on('document_versions')->cascadeOnDelete();
            $table->foreign('file_object_id', 'document_text_extractions_fk_2')->references('id')->on('file_objects')->cascadeOnDelete();
            $table->foreign('verified_by', 'document_text_extractions_fk_3')->references('id')->on('users')->nullOnDelete();
        });

        DB::statement('ALTER TABLE `document_text_extractions` ADD CONSTRAINT `document_text_extractions_ck_1` CHECK (((`confidence_score` is null) or ((`confidence_score` >= 0) and (`confidence_score` <= 100))))');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_text_extractions');
    }
};
