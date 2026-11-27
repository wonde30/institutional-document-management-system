<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_memberships', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('unit_id');
            $table->string('position_title', 160)->nullable();
            $table->dateTime('starts_at', 6);
            $table->dateTime('ends_at', 6)->nullable();
            $table->unsignedBigInteger('created_by');
            $table->dateTime('created_at', 6);

            $table->index(['unit_id', 'ends_at', 'user_id'], 'organization_memberships_ix_1');
            $table->index('created_by', 'organization_memberships_ix_2');
            $table->unique(['user_id', 'unit_id', 'starts_at'], 'organization_memberships_uq_1');

            $table->foreign('user_id', 'organization_memberships_fk_1')->references('id')->on('users');
            $table->foreign('unit_id', 'organization_memberships_fk_2')->references('id')->on('organization_units');
            $table->foreign('created_by', 'organization_memberships_fk_3')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `organization_memberships` ADD CONSTRAINT `organization_memberships_ck_1` CHECK (((`ends_at` is null) or (`ends_at` > `starts_at`)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_memberships');
    }
};
