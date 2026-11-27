<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('priorities', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->string('code', 64);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);

            $table->unique('code', 'priorities_uq_1');
        });

        DB::statement('ALTER TABLE `priorities` ADD CONSTRAINT `priorities_ck_1` CHECK ((`active` in (0,1)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('priorities');
    }
};
