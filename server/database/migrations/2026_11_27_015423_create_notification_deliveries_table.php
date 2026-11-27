<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('notification_id');
            $table->string('channel', 32);
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->dateTime('delivered_at', 6)->nullable();
            $table->string('last_error_code', 100)->nullable();

            $table->index('status', 'notification_deliveries_ix_1');
            $table->unique(['notification_id', 'channel'], 'notification_deliveries_uq_1');

            $table->foreign('notification_id', 'notification_deliveries_fk_1')->references('id')->on('user_notifications');
        });

        DB::statement('ALTER TABLE `notification_deliveries` ADD CONSTRAINT `notification_deliveries_ck_1` CHECK ((`status` in (\'pending\',\'delivering\',\'delivered\',\'failed\')))');
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
