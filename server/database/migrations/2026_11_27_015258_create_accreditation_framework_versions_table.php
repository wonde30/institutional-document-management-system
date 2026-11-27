<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accreditation_framework_versions', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('framework_id');
            $table->string('version_label', 100);
            $table->date('effective_from')->nullable();

            $table->unique(['framework_id', 'version_label'], 'accreditation_framework_versions_uq_1');

            $table->foreign('framework_id', 'accreditation_framework_versions_fk_1')->references('id')->on('accreditation_frameworks');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accreditation_framework_versions');
    }
};
