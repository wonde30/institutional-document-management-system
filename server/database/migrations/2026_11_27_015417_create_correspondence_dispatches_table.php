<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correspondence_dispatches', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('participant_id');
            $table->unsignedBigInteger('document_version_id');
            $table->unsignedBigInteger('method_id');
            $table->unsignedBigInteger('dispatched_by');
            $table->dateTime('dispatched_at', 6);
            $table->string('tracking_reference', 160)->nullable();
            $table->unsignedBigInteger('evidence_file_id')->nullable();
            $table->string('idempotency_key', 100);

            $table->index(['document_version_id', 'dispatched_at'], 'correspondence_dispatches_ix_1');
            $table->index('method_id', 'correspondence_dispatches_ix_2');
            $table->index('dispatched_by', 'correspondence_dispatches_ix_3');
            $table->index('evidence_file_id', 'correspondence_dispatches_ix_4');
            $table->unique(['participant_id', 'idempotency_key'], 'correspondence_dispatches_uq_1');

            $table->foreign('participant_id', 'correspondence_dispatches_fk_1')->references('id')->on('correspondence_participants');
            $table->foreign('document_version_id', 'correspondence_dispatches_fk_2')->references('id')->on('document_versions');
            $table->foreign('method_id', 'correspondence_dispatches_fk_3')->references('id')->on('dispatch_methods');
            $table->foreign('dispatched_by', 'correspondence_dispatches_fk_4')->references('id')->on('users');
            $table->foreign('evidence_file_id', 'correspondence_dispatches_fk_5')->references('id')->on('file_objects');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondence_dispatches');
    }
};
