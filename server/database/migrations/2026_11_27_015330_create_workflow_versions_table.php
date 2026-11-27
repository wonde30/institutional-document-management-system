<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_versions', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('definition_id');
            $table->unsignedInteger('version_number');
            $table->unsignedBigInteger('created_by');
            $table->dateTime('created_at', 6);
            $table->dateTime('published_at', 6)->nullable();

            $table->index('created_by', 'workflow_versions_ix_1');
            $table->unique(['definition_id', 'version_number'], 'workflow_versions_uq_1');

            $table->foreign('definition_id', 'workflow_versions_fk_1')->references('id')->on('workflow_definitions');
            $table->foreign('created_by', 'workflow_versions_fk_2')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `workflow_versions` ADD CONSTRAINT `workflow_versions_ck_1` CHECK ((`version_number` > 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_versions');
    }
};
