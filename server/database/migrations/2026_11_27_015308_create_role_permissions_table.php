<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');

            $table->primary(['role_id', 'permission_id']);

            $table->index('permission_id', 'role_permissions_ix_1');

            $table->foreign('role_id', 'role_permissions_fk_1')->references('id')->on('roles');
            $table->foreign('permission_id', 'role_permissions_fk_2')->references('id')->on('permissions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
