<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_events', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('instance_id');
            $table->unsignedInteger('sequence_number');
            $table->unsignedBigInteger('transition_id')->nullable();
            $table->unsignedBigInteger('actor_id');
            $table->string('event_kind', 16);
            $table->text('reason')->nullable();
            $table->dateTime('occurred_at', 6);
            $table->string('idempotency_key', 100);

            $table->index(['instance_id', 'occurred_at'], 'workflow_events_ix_1');
            $table->index('transition_id', 'workflow_events_ix_2');
            $table->index('actor_id', 'workflow_events_ix_3');
            $table->unique(['instance_id', 'sequence_number'], 'workflow_events_uq_1');
            $table->unique(['instance_id', 'idempotency_key'], 'workflow_events_uq_2');

            $table->foreign('instance_id', 'workflow_events_fk_1')->references('id')->on('workflow_instances');
            $table->foreign('transition_id', 'workflow_events_fk_2')->references('id')->on('workflow_transitions');
            $table->foreign('actor_id', 'workflow_events_fk_3')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `workflow_events` ADD CONSTRAINT `workflow_events_ck_1` CHECK ((`sequence_number` > 0))');
        DB::statement('ALTER TABLE `workflow_events` ADD CONSTRAINT `workflow_events_ck_2` CHECK ((((`event_kind` = \'transition\') and (`transition_id` is not null)) or ((`event_kind` in (\'start\',\'cancel\')) and (`transition_id` is null))))');
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_events');
    }
};
