<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_employment_records', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('unit_id');
            $table->string('position_title', 160);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedBigInteger('evidence_document_id');

            $table->index(['staff_id', 'starts_on'], 'staff_employment_records_ix_1');
            $table->index('unit_id', 'staff_employment_records_ix_2');
            $table->index('evidence_document_id', 'staff_employment_records_ix_3');

            $table->foreign('staff_id', 'staff_employment_records_fk_1')->references('id')->on('staff_profiles');
            $table->foreign('unit_id', 'staff_employment_records_fk_2')->references('id')->on('organization_units');
            $table->foreign('evidence_document_id', 'staff_employment_records_fk_3')->references('id')->on('documents');
        });

        DB::statement('ALTER TABLE `staff_employment_records` ADD CONSTRAINT `staff_employment_records_ck_1` CHECK (((`ends_on` is null) or (`ends_on` >= `starts_on`)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_employment_records');
    }
};
