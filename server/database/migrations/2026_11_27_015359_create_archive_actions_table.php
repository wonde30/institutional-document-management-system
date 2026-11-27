<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archive_actions', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('archive_entry_id');
            $table->string('action', 32);
            $table->unsignedBigInteger('actor_id');
            $table->dateTime('occurred_at', 6);
            $table->text('reason');
            $table->unsignedBigInteger('evidence_document_id')->nullable();

            $table->index(['archive_entry_id', 'occurred_at', 'id'], 'archive_actions_ix_1');
            $table->index('actor_id', 'archive_actions_ix_2');
            $table->index('evidence_document_id', 'archive_actions_ix_3');

            $table->foreign('archive_entry_id', 'archive_actions_fk_1')->references('id')->on('archive_entries');
            $table->foreign('actor_id', 'archive_actions_fk_2')->references('id')->on('users');
            $table->foreign('evidence_document_id', 'archive_actions_fk_3')->references('id')->on('documents');
        });

        DB::statement('ALTER TABLE `archive_actions` ADD CONSTRAINT `archive_actions_ck_1` CHECK ((`action` in (\'transfer_in\',\'review\',\'restore\',\'transfer_out\',\'disposition_executed\')))');
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_actions');
    }
};
