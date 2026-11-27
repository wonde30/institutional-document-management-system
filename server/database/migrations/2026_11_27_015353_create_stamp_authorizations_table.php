<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stamp_authorizations', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('stamp_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('granted_by');
            $table->dateTime('valid_from', 6);
            $table->dateTime('valid_until', 6)->nullable();

            $table->index('user_id', 'stamp_authorizations_ix_1');
            $table->index('granted_by', 'stamp_authorizations_ix_2');
            $table->unique(['stamp_id', 'user_id', 'valid_from'], 'stamp_authorizations_uq_1');

            $table->foreign('stamp_id', 'stamp_authorizations_fk_1')->references('id')->on('stamp_definitions');
            $table->foreign('user_id', 'stamp_authorizations_fk_2')->references('id')->on('users');
            $table->foreign('granted_by', 'stamp_authorizations_fk_3')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `stamp_authorizations` ADD CONSTRAINT `stamp_authorizations_ck_1` CHECK (((`valid_until` is null) or (`valid_until` > `valid_from`)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('stamp_authorizations');
    }
};
