<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_parties', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('external_party_id')->nullable();

            $table->unique('unit_id', 'communication_parties_uq_1');
            $table->unique('user_id', 'communication_parties_uq_2');
            $table->unique('external_party_id', 'communication_parties_uq_3');

            $table->foreign('unit_id', 'communication_parties_fk_1')->references('id')->on('organization_units');
            $table->foreign('user_id', 'communication_parties_fk_2')->references('id')->on('users');
            $table->foreign('external_party_id', 'communication_parties_fk_3')->references('id')->on('external_parties');
        });

        DB::statement('ALTER TABLE `communication_parties` ADD CONSTRAINT `communication_parties_ck_1` CHECK (((((`unit_id` is not null) + (`user_id` is not null)) + (`external_party_id` is not null)) = 1))');
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_parties');
    }
};
