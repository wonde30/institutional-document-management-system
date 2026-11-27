<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digital_signatures', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('signing_identity_id');
            $table->unsignedBigInteger('source_file_id');
            $table->unsignedBigInteger('signed_file_id');
            $table->string('signature_format', 64);
            $table->string('verification_status', 32)->default('verified');
            $table->string('trust_tier', 32)->default('development_certificate');
            $table->json('certificate_metadata')->nullable();
            $table->dateTime('signed_at', 6);
            $table->string('provider_transaction_reference', 191)->nullable();

            $table->index('signing_identity_id', 'digital_signatures_ix_1');
            $table->index('source_file_id', 'digital_signatures_ix_2');
            $table->unique('request_id', 'digital_signatures_uq_1');
            $table->unique('signed_file_id', 'digital_signatures_uq_2');

            $table->foreign('request_id', 'digital_signatures_fk_1')->references('id')->on('signature_requests');
            $table->foreign('signing_identity_id', 'digital_signatures_fk_2')->references('id')->on('signing_identities');
            $table->foreign('source_file_id', 'digital_signatures_fk_3')->references('id')->on('file_objects');
            $table->foreign('signed_file_id', 'digital_signatures_fk_4')->references('id')->on('file_objects');
        });

        DB::statement('ALTER TABLE `digital_signatures` ADD CONSTRAINT `digital_signatures_ck_1` CHECK ((`source_file_id` <> `signed_file_id`))');
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_signatures');
    }
};
