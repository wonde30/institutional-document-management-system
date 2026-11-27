<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physical_custody_events', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('physical_original_id');
            $table->unsignedBigInteger('location_id')->nullable();
            $table->unsignedBigInteger('custodian_id')->nullable();
            $table->string('action', 24);
            $table->unsignedBigInteger('actor_id');
            $table->dateTime('occurred_at', 6);
            $table->text('reason')->nullable();

            $table->index(['physical_original_id', 'occurred_at', 'id'], 'physical_custody_events_ix_1');
            $table->index('location_id', 'physical_custody_events_ix_2');
            $table->index('custodian_id', 'physical_custody_events_ix_3');
            $table->index('actor_id', 'physical_custody_events_ix_4');

            $table->foreign('physical_original_id', 'physical_custody_events_fk_1')->references('id')->on('physical_originals');
            $table->foreign('location_id', 'physical_custody_events_fk_2')->references('id')->on('physical_storage_locations');
            $table->foreign('custodian_id', 'physical_custody_events_fk_3')->references('id')->on('users');
            $table->foreign('actor_id', 'physical_custody_events_fk_4')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `physical_custody_events` ADD CONSTRAINT `physical_custody_events_ck_1` CHECK ((`action` in (\'register\',\'move\',\'checkout\',\'return\',\'missing\',\'transfer\',\'destroy\')))');
    }

    public function down(): void
    {
        Schema::dropIfExists('physical_custody_events');
    }
};
