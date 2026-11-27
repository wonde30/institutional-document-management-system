<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movement_receipts', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->unsignedBigInteger('movement_id');
            $table->unsignedBigInteger('acknowledged_by');
            $table->dateTime('acknowledged_at', 6);

            $table->primary('movement_id');

            $table->index('acknowledged_by', 'movement_receipts_ix_1');

            $table->foreign('movement_id', 'movement_receipts_fk_1')->references('id')->on('document_movements');
            $table->foreign('acknowledged_by', 'movement_receipts_fk_2')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movement_receipts');
    }
};
