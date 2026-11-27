<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->string('reference_number', 160);
            $table->string('title', 500);
            $table->text('summary')->nullable();
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('confidentiality_id');
            $table->unsignedBigInteger('owner_unit_id');
            $table->unsignedBigInteger('custodian_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->date('document_date');
            $table->dateTime('registered_at', 6);
            $table->string('lifecycle', 16)->default('active');
            $table->unsignedBigInteger('lock_version')->default(0);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->fullText(['title', 'summary'], 'documents_fx_title_summary');
            $table->index(['owner_unit_id', 'lifecycle', 'registered_at'], 'documents_ix_1');
            $table->index(['category_id', 'registered_at'], 'documents_ix_2');
            $table->index(['confidentiality_id', 'registered_at'], 'documents_ix_3');
            $table->index('custodian_id', 'documents_ix_4');
            $table->index('created_by', 'documents_ix_5');
            $table->unique('reference_number', 'documents_uq_1');

            $table->foreign('category_id', 'documents_fk_1')->references('id')->on('document_categories');
            $table->foreign('confidentiality_id', 'documents_fk_2')->references('id')->on('confidentiality_levels');
            $table->foreign('owner_unit_id', 'documents_fk_3')->references('id')->on('organization_units');
            $table->foreign('custodian_id', 'documents_fk_4')->references('id')->on('users');
            $table->foreign('created_by', 'documents_fk_5')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `documents` ADD CONSTRAINT `documents_ck_1` CHECK ((`lifecycle` in (\'active\',\'retired\',\'tombstoned\')))');
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
