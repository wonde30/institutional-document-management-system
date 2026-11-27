<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_movements', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('from_unit_id')->nullable();
            $table->unsignedBigInteger('to_unit_id');
            $table->unsignedBigInteger('sent_by');
            $table->dateTime('sent_at', 6);
            $table->text('instruction')->nullable();
            $table->dateTime('due_at', 6)->nullable();
            $table->string('idempotency_key', 100);

            $table->index(['document_id', 'sent_at', 'id'], 'document_movements_ix_1');
            $table->index(['to_unit_id', 'sent_at'], 'document_movements_ix_2');
            $table->index('from_unit_id', 'document_movements_ix_3');
            $table->index('sent_by', 'document_movements_ix_4');
            $table->index(['document_id', 'to_unit_id', 'sent_at'], 'document_movements_ix_5');
            $table->unique(['document_id', 'idempotency_key'], 'document_movements_uq_1');

            $table->foreign('document_id', 'document_movements_fk_1')->references('id')->on('documents');
            $table->foreign('from_unit_id', 'document_movements_fk_2')->references('id')->on('organization_units');
            $table->foreign('to_unit_id', 'document_movements_fk_3')->references('id')->on('organization_units');
            $table->foreign('sent_by', 'document_movements_fk_4')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `document_movements` ADD CONSTRAINT `document_movements_ck_1` CHECK (((`from_unit_id` is null) or (`from_unit_id` <> `to_unit_id`)))');
        DB::statement('ALTER TABLE `document_movements` ADD CONSTRAINT `document_movements_ck_2` CHECK (((`due_at` is null) or (`due_at` >= `sent_at`)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_movements');
    }
};
