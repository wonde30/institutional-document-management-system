<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_rows', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('batch_id');
            $table->string('source_row_key', 191);
            $table->unsignedBigInteger('target_document_id')->nullable();
            $table->string('status', 16)->default('pending');
            $table->json('validation_errors_json')->nullable();

            $table->index('target_document_id', 'import_rows_ix_1');
            $table->index('status', 'import_rows_status_idx');
            $table->unique(['batch_id', 'source_row_key'], 'import_rows_uq_1');

            $table->foreign('batch_id', 'import_rows_fk_1')->references('id')->on('import_batches');
            $table->foreign('target_document_id', 'import_rows_fk_2')->references('id')->on('documents');
        });

        DB::statement('ALTER TABLE `import_rows` ADD CONSTRAINT `import_rows_ck_1` CHECK ((`status` in (\'pending\',\'imported\',\'skipped\',\'rejected\')))');
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
    }
};
