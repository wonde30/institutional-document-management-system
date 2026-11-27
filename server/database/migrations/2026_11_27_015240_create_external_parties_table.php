<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_parties', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->string('name', 255);
            $table->string('party_type', 16);
            $table->string('external_identifier', 120)->nullable();
            $table->dateTime('created_at', 6);
        });

        DB::statement('ALTER TABLE `external_parties` ADD CONSTRAINT `external_parties_ck_1` CHECK ((`party_type` in (\'organization\',\'person\')))');
    }

    public function down(): void
    {
        Schema::dropIfExists('external_parties');
    }
};
