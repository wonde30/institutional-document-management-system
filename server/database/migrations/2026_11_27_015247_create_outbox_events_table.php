<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->string('event_type', 100);
            $table->string('aggregate_type', 100);
            $table->string('aggregate_id', 100);
            $table->json('payload_json');
            $table->dateTime('created_at', 6);
            $table->dateTime('published_at', 6)->nullable();
            $table->unsignedInteger('attempt_count')->default(0);

            $table->index(['published_at', 'id'], 'outbox_events_ix_1');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
