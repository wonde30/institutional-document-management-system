<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correspondence_procedures', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->unsignedBigInteger('correspondence_id');
            $table->unsignedBigInteger('procedure_version_id');
            $table->unsignedBigInteger('applied_by');
            $table->dateTime('applied_at', 6);

            $table->primary('correspondence_id');

            $table->index('procedure_version_id', 'correspondence_procedures_ix_1');
            $table->index('applied_by', 'correspondence_procedures_ix_2');

            $table->foreign('correspondence_id', 'correspondence_procedures_fk_1')->references('id')->on('correspondences');
            $table->foreign('procedure_version_id', 'correspondence_procedures_fk_2')->references('id')->on('office_procedure_versions');
            $table->foreign('applied_by', 'correspondence_procedures_fk_3')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondence_procedures');
    }
};
