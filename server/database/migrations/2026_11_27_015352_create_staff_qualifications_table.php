<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_qualifications', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('qualification_type_id');
            $table->unsignedBigInteger('institution_id');
            $table->string('field_of_study', 200)->nullable();
            $table->date('awarded_on')->nullable();
            $table->unsignedBigInteger('evidence_document_id')->nullable();

            $table->index(['staff_id', 'awarded_on'], 'staff_qualifications_ix_1');
            $table->index('qualification_type_id', 'staff_qualifications_ix_2');
            $table->index('institution_id', 'staff_qualifications_ix_3');
            $table->index('evidence_document_id', 'staff_qualifications_ix_4');

            $table->foreign('staff_id', 'staff_qualifications_fk_1')->references('id')->on('staff_profiles');
            $table->foreign('qualification_type_id', 'staff_qualifications_fk_2')->references('id')->on('qualification_types');
            $table->foreign('institution_id', 'staff_qualifications_fk_3')->references('id')->on('institutions');
            $table->foreign('evidence_document_id', 'staff_qualifications_fk_4')->references('id')->on('documents');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_qualifications');
    }
};
