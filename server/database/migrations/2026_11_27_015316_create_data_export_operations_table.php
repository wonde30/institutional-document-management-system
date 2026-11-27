<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_export_operations', function (Blueprint $table) {
            $table->collation('utf8mb4_unicode_ci');

            $table->id();
            $table->string('dataset', 64);
            $table->string('status', 32);
            $table->string('format', 16)->default('csv');
            $table->string('storage_path', 500)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->unsignedInteger('record_count')->default(0);
            $table->json('filters')->nullable();
            $table->unsignedBigInteger('scope_unit_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index(['created_by', 'created_at'], 'data_export_operations_actor_index');
            $table->index('expires_at', 'data_export_operations_expiry_index');
            $table->index('scope_unit_id', 'data_export_operations_scope_unit_id_foreign');
            $table->index('status', 'data_export_operations_status_index');

            $table->foreign('created_by', 'data_export_operations_created_by_foreign')->references('id')->on('users')->nullOnDelete();
            $table->foreign('scope_unit_id', 'data_export_operations_scope_unit_id_foreign')->references('id')->on('organization_units')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_export_operations');
    }
};
