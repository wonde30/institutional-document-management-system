<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stamp_definitions', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('unit_id');
            $table->string('name', 160);
            $table->boolean('active')->default(true);

            $table->unique(['unit_id', 'name'], 'stamp_definitions_uq_1');

            $table->foreign('unit_id', 'stamp_definitions_fk_1')->references('id')->on('organization_units');
        });

        DB::statement('ALTER TABLE `stamp_definitions` ADD CONSTRAINT `stamp_definitions_ck_1` CHECK ((`active` in (0,1)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('stamp_definitions');
    }
};
