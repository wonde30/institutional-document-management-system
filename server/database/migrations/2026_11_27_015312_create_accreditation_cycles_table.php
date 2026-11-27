<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accreditation_cycles', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('framework_version_id');
            $table->unsignedBigInteger('unit_id');
            $table->string('name', 200);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();

            $table->index('unit_id', 'accreditation_cycles_ix_1');
            $table->unique(['framework_version_id', 'unit_id', 'name'], 'accreditation_cycles_uq_1');

            $table->foreign('framework_version_id', 'accreditation_cycles_fk_1')->references('id')->on('accreditation_framework_versions');
            $table->foreign('unit_id', 'accreditation_cycles_fk_2')->references('id')->on('organization_units');
        });

        DB::statement('ALTER TABLE `accreditation_cycles` ADD CONSTRAINT `accreditation_cycles_ck_1` CHECK (((`ends_on` is null) or (`ends_on` >= `starts_on`)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('accreditation_cycles');
    }
};
