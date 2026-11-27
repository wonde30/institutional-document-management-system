<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archive_collections', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 64);
            $table->string('name', 200);
            $table->text('description')->nullable();

            $table->index('parent_id', 'archive_collections_ix_1');
            $table->unique('code', 'archive_collections_uq_1');

            $table->foreign('parent_id', 'archive_collections_fk_1')->references('id')->on('archive_collections');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_collections');
    }
};
