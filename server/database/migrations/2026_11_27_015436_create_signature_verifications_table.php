<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signature_verifications', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('signature_id');
            $table->dateTime('verified_at', 6);
            $table->string('outcome', 16);
            $table->string('certificate_status', 64)->nullable();
            $table->string('timestamp_status', 64)->nullable();
            $table->json('details_json')->nullable();

            $table->index(['signature_id', 'verified_at'], 'signature_verifications_ix_1');

            $table->foreign('signature_id', 'signature_verifications_fk_1')->references('id')->on('digital_signatures');
        });

        DB::statement('ALTER TABLE `signature_verifications` ADD CONSTRAINT `signature_verifications_ck_1` CHECK ((`outcome` in (\'valid\',\'invalid\',\'indeterminate\',\'error\')))');
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_verifications');
    }
};
