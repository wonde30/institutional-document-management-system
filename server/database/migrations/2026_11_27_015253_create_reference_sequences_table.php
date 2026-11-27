<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_sequences', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->string('sequence_key', 64);
            $table->string('period_key', 32);
            $table->unsignedBigInteger('next_value')->default(1);

            $table->unique(['sequence_key', 'period_key'], 'reference_sequences_uq_1');
        });

        DB::statement('ALTER TABLE `reference_sequences` ADD CONSTRAINT `reference_sequences_ck_1` CHECK ((`next_value` > 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_sequences');
    }
};
