<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_import_row_errors', function (Blueprint $table) {
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->unsignedBigInteger('operation_id');
            $table->unsignedInteger('row_number');
            $table->string('column_name', 64)->nullable();
            $table->string('code', 48);
            $table->string('message', 500);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['operation_id', 'row_number'], 'data_import_row_errors_operation_row_index');

            $table->foreign('operation_id', 'data_import_row_errors_operation_id_foreign')->references('id')->on('data_import_operations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_import_row_errors');
    }
};
