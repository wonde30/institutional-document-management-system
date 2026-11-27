<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_certificates', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->string('title', 255);
            $table->unsignedBigInteger('issuer_id');
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->unsignedBigInteger('evidence_document_id')->nullable();

            $table->index(['staff_id', 'issued_on'], 'staff_certificates_ix_1');
            $table->index('issuer_id', 'staff_certificates_ix_2');
            $table->index('evidence_document_id', 'staff_certificates_ix_3');

            $table->foreign('staff_id', 'staff_certificates_fk_1')->references('id')->on('staff_profiles');
            $table->foreign('issuer_id', 'staff_certificates_fk_2')->references('id')->on('institutions');
            $table->foreign('evidence_document_id', 'staff_certificates_fk_3')->references('id')->on('documents');
        });

        DB::statement('ALTER TABLE `staff_certificates` ADD CONSTRAINT `staff_certificates_ck_1` CHECK (((`expires_on` is null) or (`issued_on` is null) or (`expires_on` >= `issued_on`)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_certificates');
    }
};
