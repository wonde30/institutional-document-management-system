<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physical_originals', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->string('physical_reference', 160);
            $table->string('retention_requirement', 16)->default('unknown');
            $table->unsignedBigInteger('retention_policy_version_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->dateTime('created_at', 6);

            $table->index('document_id', 'physical_originals_ix_1');
            $table->index('retention_policy_version_id', 'physical_originals_ix_2');
            $table->index('created_by', 'physical_originals_ix_3');
            $table->unique('physical_reference', 'physical_originals_uq_1');

            $table->foreign('document_id', 'physical_originals_fk_1')->references('id')->on('documents');
            $table->foreign('retention_policy_version_id', 'physical_originals_fk_2')->references('id')->on('retention_policy_versions');
            $table->foreign('created_by', 'physical_originals_fk_3')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `physical_originals` ADD CONSTRAINT `physical_originals_ck_1` CHECK ((`retention_requirement` in (\'unknown\',\'required\',\'not_required\')))');
    }

    public function down(): void
    {
        Schema::dropIfExists('physical_originals');
    }
};
