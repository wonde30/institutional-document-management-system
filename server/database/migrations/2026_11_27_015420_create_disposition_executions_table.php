<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disposition_executions', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('executed_by');
            $table->dateTime('executed_at', 6);
            $table->unsignedBigInteger('evidence_document_id')->nullable();
            $table->string('external_receipt', 255)->nullable();

            $table->index('executed_by', 'disposition_executions_ix_1');
            $table->index('evidence_document_id', 'disposition_executions_ix_2');
            $table->unique('request_id', 'disposition_executions_uq_1');

            $table->foreign('request_id', 'disposition_executions_fk_1')->references('id')->on('disposition_requests');
            $table->foreign('executed_by', 'disposition_executions_fk_2')->references('id')->on('users');
            $table->foreign('evidence_document_id', 'disposition_executions_fk_3')->references('id')->on('documents');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disposition_executions');
    }
};
