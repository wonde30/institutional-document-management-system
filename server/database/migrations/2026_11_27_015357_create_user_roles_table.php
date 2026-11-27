<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_roles', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('scope_id');
            $table->unsignedBigInteger('granted_by');
            $table->dateTime('granted_at', 6);
            $table->dateTime('expires_at', 6)->nullable();

            $table->primary(['user_id', 'role_id', 'scope_id']);

            $table->index(['scope_id', 'user_id'], 'user_roles_ix_1');
            $table->index('role_id', 'user_roles_ix_2');
            $table->index('granted_by', 'user_roles_ix_3');

            $table->foreign('user_id', 'user_roles_fk_1')->references('id')->on('users');
            $table->foreign('role_id', 'user_roles_fk_2')->references('id')->on('roles');
            $table->foreign('scope_id', 'user_roles_fk_3')->references('id')->on('authorization_scopes');
            $table->foreign('granted_by', 'user_roles_fk_4')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `user_roles` ADD CONSTRAINT `user_roles_ck_1` CHECK (((`expires_at` is null) or (`expires_at` > `granted_at`)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
    }
};
