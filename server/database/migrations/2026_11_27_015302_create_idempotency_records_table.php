<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_records', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('operation_key', 100);
            $table->string('idempotency_key', 100);
            $table->char('request_hash', 64)->charset('ascii')->collation('ascii_bin');
            $table->string('result_reference', 255)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('expires_at', 6);

            $table->index('expires_at', 'idempotency_records_ix_1');
            $table->unique(['user_id', 'operation_key', 'idempotency_key'], 'idempotency_records_uq_1');

            $table->foreign('user_id', 'idempotency_records_fk_1')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `idempotency_records` ADD CONSTRAINT `idempotency_records_ck_1` CHECK ((`expires_at` > `created_at`))');
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_records');
    }
};
