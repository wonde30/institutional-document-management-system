<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signature_requests', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('document_version_id');
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('signer_id');
            $table->dateTime('requested_at', 6);
            $table->dateTime('expires_at', 6)->nullable();
            $table->text('purpose');

            $table->index(['signer_id', 'requested_at'], 'signature_requests_ix_1');
            $table->index('document_version_id', 'signature_requests_ix_2');
            $table->index('requested_by', 'signature_requests_ix_3');

            $table->foreign('document_version_id', 'signature_requests_fk_1')->references('id')->on('document_versions');
            $table->foreign('requested_by', 'signature_requests_fk_2')->references('id')->on('users');
            $table->foreign('signer_id', 'signature_requests_fk_3')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `signature_requests` ADD CONSTRAINT `signature_requests_ck_1` CHECK (((`expires_at` is null) or (`expires_at` > `requested_at`)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('signature_requests');
    }
};
