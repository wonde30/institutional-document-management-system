<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_integrity_checks', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('file_object_id');
            $table->dateTime('checked_at', 6);
            $table->char('observed_sha256', 64)->charset('ascii')->collation('ascii_bin')->nullable();
            $table->string('outcome', 16);
            $table->string('error_code', 100)->nullable();

            $table->index(['file_object_id', 'checked_at'], 'file_integrity_checks_ix_1');

            $table->foreign('file_object_id', 'file_integrity_checks_fk_1')->references('id')->on('file_objects');
        });

        DB::statement('ALTER TABLE `file_integrity_checks` ADD CONSTRAINT `file_integrity_checks_ck_1` CHECK ((`outcome` in (\'match\',\'mismatch\',\'missing\',\'error\')))');
    }

    public function down(): void
    {
        Schema::dropIfExists('file_integrity_checks');
    }
};
