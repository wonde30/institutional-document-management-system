<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('user_id');
            $table->dateTime('read_at', 6)->nullable();
            $table->dateTime('created_at', 6);

            $table->index(['user_id', 'read_at', 'created_at'], 'user_notifications_ix_1');
            $table->index(['user_id', 'created_at'], 'user_notifications_ix_2');
            $table->unique(['event_id', 'user_id'], 'user_notifications_uq_1');

            $table->foreign('event_id', 'user_notifications_fk_1')->references('id')->on('notification_events');
            $table->foreign('user_id', 'user_notifications_fk_2')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
