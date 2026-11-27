<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stamp_applications', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('stamp_version_id');
            $table->unsignedBigInteger('document_version_id');
            $table->unsignedBigInteger('source_file_id');
            $table->unsignedBigInteger('result_file_id');
            $table->unsignedBigInteger('applied_by');
            $table->dateTime('applied_at', 6);
            $table->text('reason');

            $table->index('stamp_version_id', 'stamp_applications_ix_1');
            $table->index('document_version_id', 'stamp_applications_ix_2');
            $table->index('source_file_id', 'stamp_applications_ix_3');
            $table->index('applied_by', 'stamp_applications_ix_4');
            $table->unique('result_file_id', 'stamp_applications_uq_1');

            $table->foreign('stamp_version_id', 'stamp_applications_fk_1')->references('id')->on('stamp_versions');
            $table->foreign('document_version_id', 'stamp_applications_fk_2')->references('id')->on('document_versions');
            $table->foreign('source_file_id', 'stamp_applications_fk_3')->references('id')->on('file_objects');
            $table->foreign('result_file_id', 'stamp_applications_fk_4')->references('id')->on('file_objects');
            $table->foreign('applied_by', 'stamp_applications_fk_5')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `stamp_applications` ADD CONSTRAINT `stamp_applications_ck_1` CHECK ((`source_file_id` <> `result_file_id`))');
    }

    public function down(): void
    {
        Schema::dropIfExists('stamp_applications');
    }
};
