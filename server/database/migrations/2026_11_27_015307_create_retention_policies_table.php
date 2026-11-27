<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retention_policies', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->string('code', 64);
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('document_category_id')->nullable();
            $table->boolean('active')->default(true);

            $table->index('document_category_id', 'retention_policies_fk_1');
            $table->unique('code', 'retention_policies_uq_1');

            $table->foreign('document_category_id', 'retention_policies_fk_1')->references('id')->on('document_categories')->nullOnDelete();
        });

        DB::statement('ALTER TABLE `retention_policies` ADD CONSTRAINT `retention_policies_ck_1` CHECK ((`active` in (0,1)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_policies');
    }
};
