<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->string('code', 64);
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('owner_unit_id')->nullable();
            $table->string('visibility_scope', 20)->default('global');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by');
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);

            $table->index(['owner_unit_id', 'is_active'], 'document_templates_ix_1');
            $table->index('category_id', 'document_templates_ix_2');
            $table->index('created_by', 'document_templates_ix_3');
            $table->unique('code', 'document_templates_uq_1');

            $table->foreign('category_id', 'document_templates_fk_1')->references('id')->on('document_categories')->restrictOnDelete();
            $table->foreign('owner_unit_id', 'document_templates_fk_2')->references('id')->on('organization_units')->nullOnDelete();
            $table->foreign('created_by', 'document_templates_fk_3')->references('id')->on('users')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE `document_templates` ADD CONSTRAINT `document_templates_ck_1` CHECK ((`is_active` in (0,1)))');
        DB::statement('ALTER TABLE `document_templates` ADD CONSTRAINT `document_templates_ck_2` CHECK ((`visibility_scope` in (\'global\',\'unit_restricted\')))');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_templates');
    }
};
