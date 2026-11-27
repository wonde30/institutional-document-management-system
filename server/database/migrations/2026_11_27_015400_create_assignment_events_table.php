<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_events', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('assignment_id');
            $table->unsignedInteger('sequence_number');
            $table->string('action', 24);
            $table->unsignedBigInteger('actor_id');
            $table->unsignedBigInteger('target_user_id')->nullable();
            $table->dateTime('occurred_at', 6);
            $table->text('comment')->nullable();

            $table->index('actor_id', 'assignment_events_ix_1');
            $table->index('target_user_id', 'assignment_events_ix_2');
            $table->unique(['assignment_id', 'sequence_number'], 'assignment_events_uq_1');

            $table->foreign('assignment_id', 'assignment_events_fk_1')->references('id')->on('document_assignments');
            $table->foreign('actor_id', 'assignment_events_fk_2')->references('id')->on('users');
            $table->foreign('target_user_id', 'assignment_events_fk_3')->references('id')->on('users')->nullOnDelete();
        });

        DB::statement('ALTER TABLE `assignment_events` ADD CONSTRAINT `assignment_events_ck_1` CHECK ((`action` in (\'assigned\',\'claimed\',\'delegated\',\'delegation_accepted\',\'delegation_revoked\',\'reassigned\',\'accepted\',\'completed\',\'returned\',\'cancelled\',\'participant_removed\')))');
        DB::statement('ALTER TABLE `assignment_events` ADD CONSTRAINT `assignment_events_ck_2` CHECK ((`sequence_number` > 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_events');
    }
};
