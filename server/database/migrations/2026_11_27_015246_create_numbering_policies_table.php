<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('numbering_policies', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->string('code', 64);
            $table->string('name', 160);
            $table->string('prefix', 32)->default('WU');
            $table->boolean('include_unit_code')->default(false);
            $table->boolean('include_category_code')->default(false);
            $table->string('year_format', 20)->default('calendar_year');
            $table->unsignedInteger('sequence_length')->default(6);
            $table->string('reset_policy', 16)->default('yearly');
            $table->boolean('active')->default(true);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->unique('code', 'numbering_policies_uq_1');
        });

        DB::statement('ALTER TABLE `numbering_policies` ADD CONSTRAINT `numbering_policies_ck_1` CHECK ((`year_format` in (\'calendar_year\',\'ethiopian_year\',\'none\')))');
        DB::statement('ALTER TABLE `numbering_policies` ADD CONSTRAINT `numbering_policies_ck_2` CHECK ((`reset_policy` in (\'yearly\',\'monthly\',\'never\')))');
        DB::statement('ALTER TABLE `numbering_policies` ADD CONSTRAINT `numbering_policies_ck_3` CHECK ((`active` in (0,1)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('numbering_policies');
    }
};
