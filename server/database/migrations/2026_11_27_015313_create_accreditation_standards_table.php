<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accreditation_standards', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('framework_version_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 64);
            $table->string('title', 255);
            $table->text('description')->nullable();

            $table->index('parent_id', 'accreditation_standards_ix_1');
            $table->unique(['framework_version_id', 'code'], 'accreditation_standards_uq_1');

            $table->foreign('framework_version_id', 'accreditation_standards_fk_1')->references('id')->on('accreditation_framework_versions');
            $table->foreign('parent_id', 'accreditation_standards_fk_2')->references('id')->on('accreditation_standards');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accreditation_standards');
    }
};
