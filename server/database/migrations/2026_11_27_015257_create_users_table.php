<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->string('name', 160);
            $table->string('email', 254);
            $table->string('password', 255);
            $table->string('status', 16)->default('inactive');
            $table->dateTime('email_verified_at', 6)->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->unsignedBigInteger('authz_version')->default(1);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->index('status', 'users_ix_1');
            $table->unique('email', 'users_uq_1');
        });

        DB::statement('ALTER TABLE `users` ADD CONSTRAINT `users_ck_1` CHECK ((`status` in (\'active\',\'inactive\',\'locked\')))');
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
