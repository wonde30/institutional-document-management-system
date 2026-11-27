<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_grants', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('document_id');
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->string('subject_key', 32)->storedAs('(case when (`user_id` is not null) then concat(\'u:\',`user_id`) else concat(\'o:\',`unit_id`) end)')->nullable();
            $table->unsignedBigInteger('granted_by');
            $table->dateTime('granted_at', 6);
            $table->dateTime('expires_at', 6)->nullable();

            $table->index(['user_id', 'document_id'], 'document_grants_ix_1');
            $table->index(['unit_id', 'document_id'], 'document_grants_ix_2');
            $table->index('permission_id', 'document_grants_ix_3');
            $table->index('granted_by', 'document_grants_ix_4');
            $table->unique(['document_id', 'permission_id', 'subject_key'], 'document_grants_uq_1');

            $table->foreign('document_id', 'document_grants_fk_1')->references('id')->on('documents');
            $table->foreign('permission_id', 'document_grants_fk_2')->references('id')->on('permissions');
            $table->foreign('user_id', 'document_grants_fk_3')->references('id')->on('users');
            $table->foreign('unit_id', 'document_grants_fk_4')->references('id')->on('organization_units');
            $table->foreign('granted_by', 'document_grants_fk_5')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `document_grants` ADD CONSTRAINT `document_grants_ck_1` CHECK ((((`user_id` is not null) + (`unit_id` is not null)) = 1))');
        DB::statement('ALTER TABLE `document_grants` ADD CONSTRAINT `document_grants_ck_2` CHECK (((`expires_at` is null) or (`expires_at` > `granted_at`)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_grants');
    }
};
