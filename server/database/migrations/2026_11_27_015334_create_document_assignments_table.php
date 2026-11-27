<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_assignments', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('assigned_user_id')->nullable();
            $table->unsignedBigInteger('assigned_unit_id')->nullable();
            $table->unsignedBigInteger('assigned_by');
            $table->dateTime('assigned_at', 6);
            $table->dateTime('due_at', 6)->nullable();
            $table->text('instruction')->nullable();

            $table->index(['assigned_user_id', 'due_at'], 'document_assignments_ix_1');
            $table->index(['assigned_unit_id', 'due_at'], 'document_assignments_ix_2');
            $table->index(['document_id', 'assigned_at'], 'document_assignments_ix_3');
            $table->index('assigned_by', 'document_assignments_ix_4');

            $table->foreign('document_id', 'document_assignments_fk_1')->references('id')->on('documents');
            $table->foreign('assigned_user_id', 'document_assignments_fk_2')->references('id')->on('users');
            $table->foreign('assigned_unit_id', 'document_assignments_fk_3')->references('id')->on('organization_units');
            $table->foreign('assigned_by', 'document_assignments_fk_4')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `document_assignments` ADD CONSTRAINT `document_assignments_ck_1` CHECK (((`assigned_user_id` is not null) or (`assigned_unit_id` is not null)))');
        DB::statement('ALTER TABLE `document_assignments` ADD CONSTRAINT `document_assignments_ck_2` CHECK (((`due_at` is null) or (`due_at` >= `assigned_at`)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_assignments');
    }
};
