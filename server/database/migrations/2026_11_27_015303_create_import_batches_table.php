<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->string('source_label', 255);
            $table->unsignedBigInteger('initiated_by');
            $table->string('status', 16)->default('pending');
            $table->dateTime('started_at', 6)->nullable();
            $table->dateTime('completed_at', 6)->nullable();

            $table->index('initiated_by', 'import_batches_ix_1');
            $table->index('status', 'import_batches_status_idx');

            $table->foreign('initiated_by', 'import_batches_fk_1')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `import_batches` ADD CONSTRAINT `import_batches_ck_1` CHECK ((`status` in (\'pending\',\'running\',\'completed\',\'failed\',\'cancelled\')))');
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
