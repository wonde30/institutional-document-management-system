<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_versions', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('template_id');
            $table->unsignedInteger('version_number');
            $table->json('schema_definition');
            $table->mediumText('body_template_html');
            $table->string('header_asset_key', 255)->nullable();
            $table->string('footer_asset_key', 255)->nullable();
            $table->string('status', 16)->default('draft');
            $table->dateTime('published_at', 6)->nullable();
            $table->unsignedBigInteger('published_by')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->dateTime('created_at', 6);

            $table->index(['status', 'published_at'], 'template_versions_ix_1');
            $table->index('created_by', 'template_versions_ix_2');
            $table->index('published_by', 'template_versions_ix_3');
            $table->unique(['template_id', 'version_number'], 'template_versions_uq_1');

            $table->foreign('template_id', 'template_versions_fk_1')->references('id')->on('document_templates')->restrictOnDelete();
            $table->foreign('published_by', 'template_versions_fk_2')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by', 'template_versions_fk_3')->references('id')->on('users')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE `template_versions` ADD CONSTRAINT `template_versions_ck_1` CHECK ((`version_number` > 0))');
        DB::statement('ALTER TABLE `template_versions` ADD CONSTRAINT `template_versions_ck_2` CHECK ((`status` in (\'draft\',\'published\',\'retired\')))');
        DB::statement('ALTER TABLE `template_versions` ADD CONSTRAINT `template_versions_ck_3` CHECK ((((`status` = \'published\') and (`published_at` is not null)) or (`status` <> \'published\')))');
    }

    public function down(): void
    {
        Schema::dropIfExists('template_versions');
    }
};
