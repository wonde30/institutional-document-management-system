<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_objects', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->string('storage_disk', 32);
            $table->string('storage_key', 255);
            $table->string('original_filename', 255);
            $table->string('detected_mime', 120);
            $table->unsignedBigInteger('byte_size');
            $table->char('sha256', 64)->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('uploaded_by');
            $table->dateTime('uploaded_at', 6);
            $table->string('scan_status', 16)->default('pending');
            $table->dateTime('scanned_at', 6)->nullable();
            $table->string('storage_status', 16)->default('staged');

            $table->index('sha256', 'file_objects_ix_1');
            $table->index(['scan_status', 'uploaded_at'], 'file_objects_ix_2');
            $table->index('uploaded_by', 'file_objects_ix_3');
            $table->unique(['storage_disk', 'storage_key'], 'file_objects_uq_1');

            $table->foreign('uploaded_by', 'file_objects_fk_1')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `file_objects` ADD CONSTRAINT `file_objects_ck_1` CHECK ((`scan_status` in (\'pending\',\'clean\',\'infected\',\'error\',\'unavailable\')))');
        DB::statement('ALTER TABLE `file_objects` ADD CONSTRAINT `file_objects_ck_2` CHECK ((`storage_status` in (\'staged\',\'ready\',\'missing\',\'destroyed\')))');
        DB::statement('ALTER TABLE `file_objects` ADD CONSTRAINT `file_objects_ck_3` CHECK (regexp_like(`sha256`,\'^[0-9a-f]{64}$\'))');
    }

    public function down(): void
    {
        Schema::dropIfExists('file_objects');
    }
};
