<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_party_contacts', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('external_party_id');
            $table->string('contact_type', 16);
            $table->string('value', 500);
            $table->string('label', 100)->nullable();

            $table->index('external_party_id', 'external_party_contacts_ix_1');

            $table->foreign('external_party_id', 'external_party_contacts_fk_1')->references('id')->on('external_parties');
        });

        DB::statement('ALTER TABLE `external_party_contacts` ADD CONSTRAINT `external_party_contacts_ck_1` CHECK ((`contact_type` in (\'email\',\'phone\',\'postal\',\'other\')))');
    }

    public function down(): void
    {
        Schema::dropIfExists('external_party_contacts');
    }
};
