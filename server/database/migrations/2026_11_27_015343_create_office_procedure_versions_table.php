<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_procedure_versions', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('unit_id');
            $table->unsignedBigInteger('correspondence_type_id');
            $table->string('purpose_code', 64);
            $table->unsignedInteger('version_number');
            $table->string('receipt_mode', 24);
            $table->unsignedBigInteger('registry_unit_id')->nullable();
            $table->string('issue_mode', 24);
            $table->unsignedBigInteger('workflow_version_id')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->text('confirmation_source')->nullable();
            $table->dateTime('confirmed_at', 6)->nullable();
            $table->dateTime('effective_from', 6);
            $table->dateTime('effective_until', 6)->nullable();

            $table->index(['unit_id', 'correspondence_type_id', 'purpose_code', 'effective_from'], 'office_procedure_versions_ix_1');
            $table->index('correspondence_type_id', 'office_procedure_versions_ix_2');
            $table->index('registry_unit_id', 'office_procedure_versions_ix_3');
            $table->index('workflow_version_id', 'office_procedure_versions_ix_4');
            $table->index('confirmed_by', 'office_procedure_versions_ix_5');
            $table->unique(['unit_id', 'correspondence_type_id', 'purpose_code', 'version_number'], 'office_procedure_versions_uq_1');

            $table->foreign('unit_id', 'office_procedure_versions_fk_1')->references('id')->on('organization_units');
            $table->foreign('correspondence_type_id', 'office_procedure_versions_fk_2')->references('id')->on('correspondence_types');
            $table->foreign('registry_unit_id', 'office_procedure_versions_fk_3')->references('id')->on('organization_units');
            $table->foreign('workflow_version_id', 'office_procedure_versions_fk_4')->references('id')->on('workflow_versions');
            $table->foreign('confirmed_by', 'office_procedure_versions_fk_5')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `office_procedure_versions` ADD CONSTRAINT `office_procedure_versions_ck_1` CHECK ((`receipt_mode` in (\'direct\',\'via_registry\',\'either\')))');
        DB::statement('ALTER TABLE `office_procedure_versions` ADD CONSTRAINT `office_procedure_versions_ck_2` CHECK ((((`receipt_mode` = \'direct\') and (`registry_unit_id` is null)) or ((`receipt_mode` in (\'via_registry\',\'either\')) and (`registry_unit_id` is not null))))');
        DB::statement('ALTER TABLE `office_procedure_versions` ADD CONSTRAINT `office_procedure_versions_ck_3` CHECK ((((`issue_mode` = \'workflow\') and (`workflow_version_id` is not null)) or ((`issue_mode` in (\'direct\',\'blocked\')) and (`workflow_version_id` is null))))');
        DB::statement('ALTER TABLE `office_procedure_versions` ADD CONSTRAINT `office_procedure_versions_ck_4` CHECK (((`effective_until` is null) or (`effective_until` > `effective_from`)))');
        DB::statement('ALTER TABLE `office_procedure_versions` ADD CONSTRAINT `office_procedure_versions_ck_5` CHECK ((((`confirmed_by` is null) and (`confirmed_at` is null)) or ((`confirmed_by` is not null) and (`confirmed_at` is not null))))');
    }

    public function down(): void
    {
        Schema::dropIfExists('office_procedure_versions');
    }
};
