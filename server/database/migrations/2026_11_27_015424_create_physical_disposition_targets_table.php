<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physical_disposition_targets', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('physical_original_id');

            $table->primary(['request_id', 'physical_original_id']);

            $table->index('physical_original_id', 'physical_disposition_targets_ix_1');

            $table->foreign('request_id', 'physical_disposition_targets_fk_1')->references('id')->on('disposition_requests');
            $table->foreign('physical_original_id', 'physical_disposition_targets_fk_2')->references('id')->on('physical_originals');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('physical_disposition_targets');
    }
};
