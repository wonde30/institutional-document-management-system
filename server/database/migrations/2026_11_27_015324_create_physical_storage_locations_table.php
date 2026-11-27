<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physical_storage_locations', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('unit_id');
            $table->string('code', 64);
            $table->string('name', 200);
            $table->boolean('active')->default(true);

            $table->index('parent_id', 'physical_storage_locations_ix_1');
            $table->index('unit_id', 'physical_storage_locations_ix_2');
            $table->unique('code', 'physical_storage_locations_uq_1');

            $table->foreign('parent_id', 'physical_storage_locations_fk_1')->references('id')->on('physical_storage_locations');
            $table->foreign('unit_id', 'physical_storage_locations_fk_2')->references('id')->on('organization_units');
        });

        DB::statement('ALTER TABLE `physical_storage_locations` ADD CONSTRAINT `physical_storage_locations_ck_1` CHECK ((`active` in (0,1)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('physical_storage_locations');
    }
};
