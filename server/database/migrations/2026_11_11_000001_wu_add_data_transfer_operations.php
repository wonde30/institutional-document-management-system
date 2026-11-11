<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * History of dataset import and export operations.
     *
     * The tables record what was requested, what was actually done and where the
     * generated artefact is stored. Row level validation failures are kept in
     * their own table so that the error report is a real, queryable record
     * rather than a free form payload.
     */
    public function up(): void
    {
        Schema::create('data_import_operations', function (Blueprint $table) {
            $table->id();

            // The dataset key from the supported operation catalogue.
            $table->string('dataset', 64);

            // uploaded | validated | importing | completed | completed_with_errors | failed | discarded
            $table->string('status', 32);

            $table->string('format', 16)->default('csv');

            // The name the file carried when it was received. It is recorded for
            // the operator's benefit and never used to build a path.
            $table->string('original_filename', 255);

            // Server controlled path of the uploaded file on the private disk.
            $table->string('storage_path', 500)->nullable();

            $table->unsignedBigInteger('scope_unit_id')->nullable();
            $table->foreign('scope_unit_id')->references('id')->on('organization_units')->nullOnDelete();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);

            $table->text('failure_reason')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['created_by', 'created_at'], 'data_import_operations_actor_index');
            $table->index('status', 'data_import_operations_status_index');
            $table->index('dataset', 'data_import_operations_dataset_index');
        });

        Schema::create('data_import_row_errors', function (Blueprint $table) {
            $table->id();

            $table->foreignId('operation_id')
                ->constrained('data_import_operations')
                ->cascadeOnDelete();

            // The one based row number in the uploaded file.
            $table->unsignedInteger('row_number');

            // The offending column, when the failure is attributable to one.
            $table->string('column_name', 64)->nullable();

            $table->string('code', 48);

            $table->string('message', 500);

            $table->timestamps();

            $table->index(['operation_id', 'row_number'], 'data_import_row_errors_operation_row_index');
        });

        Schema::create('data_export_operations', function (Blueprint $table) {
            $table->id();

            $table->string('dataset', 64);

            // requested | generating | completed | failed
            $table->string('status', 32);

            $table->string('format', 16)->default('csv');

            $table->string('storage_path', 500)->nullable();

            $table->unsignedBigInteger('file_size')->default(0);

            $table->unsignedInteger('record_count')->default(0);

            // The filters that were applied. This is a document shaped value:
            // the set of filters the client requested and the server honoured.
            $table->json('filters')->nullable();

            $table->unsignedBigInteger('scope_unit_id')->nullable();
            $table->foreign('scope_unit_id')->references('id')->on('organization_units')->nullOnDelete();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->text('failure_reason')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // The generated file is only retrievable until this moment.
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(['created_by', 'created_at'], 'data_export_operations_actor_index');
            $table->index('status', 'data_export_operations_status_index');
            $table->index('expires_at', 'data_export_operations_expiry_index');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_export_operations');
        Schema::dropIfExists('data_import_row_errors');
        Schema::dropIfExists('data_import_operations');
    }
};
