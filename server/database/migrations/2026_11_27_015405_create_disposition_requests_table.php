<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disposition_requests', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('archive_entry_id');
            $table->unsignedBigInteger('requested_by');
            $table->string('requested_action', 24);
            $table->dateTime('requested_at', 6);
            $table->text('justification');

            $table->index(['archive_entry_id', 'requested_at'], 'disposition_requests_ix_1');
            $table->index('requested_by', 'disposition_requests_ix_2');

            $table->foreign('archive_entry_id', 'disposition_requests_fk_1')->references('id')->on('archive_entries');
            $table->foreign('requested_by', 'disposition_requests_fk_2')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `disposition_requests` ADD CONSTRAINT `disposition_requests_ck_1` CHECK ((`requested_action` in (\'transfer\',\'destroy_digital\',\'destroy_physical\')))');
    }

    public function down(): void
    {
        Schema::dropIfExists('disposition_requests');
    }
};
