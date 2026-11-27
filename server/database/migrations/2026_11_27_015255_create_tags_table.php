<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->string('normalized_name', 100);
            $table->string('display_name', 100);

            $table->unique('normalized_name', 'tags_uq_1');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tags');
    }
};
