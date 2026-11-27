<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->string('code', 64);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->unique('code', 'roles_uq_1');
        });

        DB::statement('ALTER TABLE `roles` ADD CONSTRAINT `roles_ck_1` CHECK ((`active` in (0,1)))');
        DB::statement('ALTER TABLE `roles` ADD CONSTRAINT `roles_ck_2` CHECK ((`is_system` in (0,1)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
