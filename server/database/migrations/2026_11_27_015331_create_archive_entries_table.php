<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archive_entries', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('collection_id');
            $table->unsignedBigInteger('retention_policy_version_id')->nullable();
            $table->unsignedBigInteger('archived_by');
            $table->dateTime('archived_at', 6);
            $table->dateTime('trigger_occurred_at', 6)->nullable();
            $table->dateTime('next_review_at', 6)->nullable();

            $table->index(['collection_id', 'archived_at'], 'archive_entries_ix_1');
            $table->index('next_review_at', 'archive_entries_ix_2');
            $table->index('retention_policy_version_id', 'archive_entries_ix_3');
            $table->index('archived_by', 'archive_entries_ix_4');
            $table->unique('document_id', 'archive_entries_uq_1');

            $table->foreign('document_id', 'archive_entries_fk_1')->references('id')->on('documents');
            $table->foreign('collection_id', 'archive_entries_fk_2')->references('id')->on('archive_collections');
            $table->foreign('retention_policy_version_id', 'archive_entries_fk_3')->references('id')->on('retention_policy_versions');
            $table->foreign('archived_by', 'archive_entries_fk_4')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archive_entries');
    }
};
