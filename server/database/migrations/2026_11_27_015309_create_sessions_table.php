<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->string('id', 255);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity');

            $table->primary('id');

            $table->index('last_activity', 'sessions_ix_1');
            $table->index('user_id', 'sessions_ix_2');

            $table->foreign('user_id', 'sessions_fk_1')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
