<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stamp_versions', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('stamp_id');
            $table->unsignedInteger('version_number');
            $table->unsignedBigInteger('image_file_id');
            $table->dateTime('effective_from', 6);
            $table->dateTime('retired_at', 6)->nullable();

            $table->index('image_file_id', 'stamp_versions_ix_1');
            $table->unique(['stamp_id', 'version_number'], 'stamp_versions_uq_1');

            $table->foreign('stamp_id', 'stamp_versions_fk_1')->references('id')->on('stamp_definitions');
            $table->foreign('image_file_id', 'stamp_versions_fk_2')->references('id')->on('file_objects');
        });

        DB::statement('ALTER TABLE `stamp_versions` ADD CONSTRAINT `stamp_versions_ck_1` CHECK ((`version_number` > 0))');
        DB::statement('ALTER TABLE `stamp_versions` ADD CONSTRAINT `stamp_versions_ck_2` CHECK (((`retired_at` is null) or (`retired_at` > `effective_from`)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('stamp_versions');
    }
};
