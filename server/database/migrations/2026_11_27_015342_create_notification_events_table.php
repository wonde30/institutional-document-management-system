<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_events', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->string('type_code', 100);
            $table->unsignedBigInteger('document_id')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->dateTime('occurred_at', 6);

            $table->index('document_id', 'notification_events_ix_1');
            $table->index('actor_id', 'notification_events_ix_2');

            $table->foreign('document_id', 'notification_events_fk_1')->references('id')->on('documents');
            $table->foreign('actor_id', 'notification_events_fk_2')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_events');
    }
};
