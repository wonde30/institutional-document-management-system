<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correspondences', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('type_id');
            $table->unsignedBigInteger('priority_id');
            $table->string('purpose_code', 64);
            $table->string('external_reference', 160)->nullable();
            $table->date('correspondence_date');
            $table->unsignedBigInteger('first_receiving_unit_id')->nullable();
            $table->dateTime('received_at', 6)->nullable();
            $table->dateTime('requested_due_at', 6)->nullable();
            $table->unsignedBigInteger('registered_by');
            $table->dateTime('registered_at', 6);

            $table->index(['type_id', 'registered_at'], 'correspondences_ix_1');
            $table->index(['first_receiving_unit_id', 'received_at'], 'correspondences_ix_2');
            $table->index('external_reference', 'correspondences_ix_3');
            $table->index('priority_id', 'correspondences_ix_4');
            $table->index('registered_by', 'correspondences_ix_5');
            $table->unique('document_id', 'correspondences_uq_1');

            $table->foreign('document_id', 'correspondences_fk_1')->references('id')->on('documents');
            $table->foreign('type_id', 'correspondences_fk_2')->references('id')->on('correspondence_types');
            $table->foreign('priority_id', 'correspondences_fk_3')->references('id')->on('priorities');
            $table->foreign('first_receiving_unit_id', 'correspondences_fk_4')->references('id')->on('organization_units');
            $table->foreign('registered_by', 'correspondences_fk_5')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `correspondences` ADD CONSTRAINT `correspondences_ck_1` CHECK ((((`first_receiving_unit_id` is null) and (`received_at` is null)) or ((`first_receiving_unit_id` is not null) and (`received_at` is not null))))');
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondences');
    }
};
