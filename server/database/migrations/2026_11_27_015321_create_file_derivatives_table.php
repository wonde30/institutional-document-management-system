<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_derivatives', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('source_file_id');
            $table->unsignedBigInteger('result_file_id');
            $table->string('kind', 32);
            $table->string('generator_version', 64);
            $table->dateTime('generated_at', 6);

            $table->unique(['source_file_id', 'kind', 'generator_version'], 'file_derivatives_uq_1');
            $table->unique('result_file_id', 'file_derivatives_uq_2');

            $table->foreign('source_file_id', 'file_derivatives_fk_1')->references('id')->on('file_objects');
            $table->foreign('result_file_id', 'file_derivatives_fk_2')->references('id')->on('file_objects');
        });

        DB::statement('ALTER TABLE `file_derivatives` ADD CONSTRAINT `file_derivatives_ck_1` CHECK ((`source_file_id` <> `result_file_id`))');
    }

    public function down(): void
    {
        Schema::dropIfExists('file_derivatives');
    }
};
