<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signing_identities', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('provider_code', 64);
            $table->string('provider_identity_reference', 191);
            $table->unsignedBigInteger('public_certificate_file_id')->nullable();
            $table->dateTime('valid_from', 6)->nullable();
            $table->dateTime('valid_until', 6)->nullable();
            $table->dateTime('revoked_at', 6)->nullable();

            $table->index('user_id', 'signing_identities_ix_1');
            $table->index('public_certificate_file_id', 'signing_identities_ix_2');
            $table->unique(['provider_code', 'provider_identity_reference'], 'signing_identities_uq_1');

            $table->foreign('user_id', 'signing_identities_fk_1')->references('id')->on('users');
            $table->foreign('public_certificate_file_id', 'signing_identities_fk_2')->references('id')->on('file_objects');
        });

        DB::statement('ALTER TABLE `signing_identities` ADD CONSTRAINT `signing_identities_ck_1` CHECK (((`valid_until` is null) or (`valid_from` is null) or (`valid_until` > `valid_from`)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('signing_identities');
    }
};
