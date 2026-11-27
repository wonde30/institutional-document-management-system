<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accreditation_evidence', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('cycle_id');
            $table->unsignedBigInteger('standard_id');
            $table->unsignedBigInteger('document_version_id');
            $table->unsignedBigInteger('submitted_by');
            $table->dateTime('submitted_at', 6);
            $table->text('note')->nullable();

            $table->index('standard_id', 'accreditation_evidence_ix_1');
            $table->index('document_version_id', 'accreditation_evidence_ix_2');
            $table->index('submitted_by', 'accreditation_evidence_ix_3');
            $table->unique(['cycle_id', 'standard_id', 'document_version_id'], 'accreditation_evidence_uq_1');

            $table->foreign('cycle_id', 'accreditation_evidence_fk_1')->references('id')->on('accreditation_cycles');
            $table->foreign('standard_id', 'accreditation_evidence_fk_2')->references('id')->on('accreditation_standards');
            $table->foreign('document_version_id', 'accreditation_evidence_fk_3')->references('id')->on('document_versions');
            $table->foreign('submitted_by', 'accreditation_evidence_fk_4')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accreditation_evidence');
    }
};
