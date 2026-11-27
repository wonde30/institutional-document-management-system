<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_units', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('unit_type_id');
            $table->string('code', 64);
            $table->string('name', 200);
            $table->boolean('active')->default(true);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->index(['parent_id', 'active'], 'organization_units_ix_1');
            $table->index('unit_type_id', 'organization_units_ix_2');
            $table->unique('code', 'organization_units_uq_1');

            $table->foreign('parent_id', 'organization_units_fk_1')->references('id')->on('organization_units');
            $table->foreign('unit_type_id', 'organization_units_fk_2')->references('id')->on('unit_types');
        });

        DB::statement('ALTER TABLE `organization_units` ADD CONSTRAINT `organization_units_ck_1` CHECK ((`active` in (0,1)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_units');
    }
};
