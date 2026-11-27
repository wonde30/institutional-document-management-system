<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_participants', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('assignment_id');
            $table->unsignedBigInteger('user_id');
            $table->string('role_code', 24)->default('contributor');
            $table->string('status', 16)->default('pending');
            $table->dateTime('assigned_at', 6);
            $table->dateTime('responded_at', 6)->nullable();

            $table->index(['user_id', 'status'], 'assignment_participants_ix_1');
            $table->unique(['assignment_id', 'user_id'], 'assignment_participants_uq_1');

            $table->foreign('assignment_id', 'assignment_participants_fk_1')->references('id')->on('document_assignments')->cascadeOnDelete();
            $table->foreign('user_id', 'assignment_participants_fk_2')->references('id')->on('users')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE `assignment_participants` ADD CONSTRAINT `assignment_participants_ck_1` CHECK ((`role_code` in (\'lead\',\'writer\',\'contributor\',\'secretary\')))');
        DB::statement('ALTER TABLE `assignment_participants` ADD CONSTRAINT `assignment_participants_ck_2` CHECK ((`status` in (\'pending\',\'accepted\',\'completed\',\'reassigned\',\'declined\')))');
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_participants');
    }
};
