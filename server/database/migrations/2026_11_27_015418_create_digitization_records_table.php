<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('digitization_records', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('physical_original_id');
            $table->unsignedBigInteger('document_version_id');
            $table->unsignedBigInteger('digitized_by');
            $table->dateTime('digitized_at', 6);
            $table->string('verification_status', 16)->default('pending');
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->dateTime('verified_at', 6)->nullable();
            $table->text('verification_note')->nullable();

            $table->index('document_version_id', 'digitization_records_ix_1');
            $table->index('digitized_by', 'digitization_records_ix_2');
            $table->index('verified_by', 'digitization_records_ix_3');
            $table->unique(['physical_original_id', 'document_version_id'], 'digitization_records_uq_1');

            $table->foreign('physical_original_id', 'digitization_records_fk_1')->references('id')->on('physical_originals');
            $table->foreign('document_version_id', 'digitization_records_fk_2')->references('id')->on('document_versions');
            $table->foreign('digitized_by', 'digitization_records_fk_3')->references('id')->on('users');
            $table->foreign('verified_by', 'digitization_records_fk_4')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `digitization_records` ADD CONSTRAINT `digitization_records_ck_1` CHECK ((((`verification_status` = \'pending\') and (`verified_by` is null) and (`verified_at` is null)) or ((`verification_status` in (\'accepted\',\'rejected\')) and (`verified_by` is not null) and (`verified_at` is not null))))');
        DB::statement('ALTER TABLE `digitization_records` ADD CONSTRAINT `digitization_records_ck_2` CHECK (((`verified_at` is null) or (`verified_at` >= `digitized_at`)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('digitization_records');
    }
};
