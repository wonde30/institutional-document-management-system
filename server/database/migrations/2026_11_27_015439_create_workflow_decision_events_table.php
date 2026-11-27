<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_decision_events', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->unsignedBigInteger('decision_id');
            $table->unsignedBigInteger('event_id');

            $table->primary('decision_id');

            $table->unique('event_id', 'workflow_decision_events_uq_1');

            $table->foreign('decision_id', 'workflow_decision_events_fk_1')->references('id')->on('workflow_task_decisions');
            $table->foreign('event_id', 'workflow_decision_events_fk_2')->references('id')->on('workflow_events');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_decision_events');
    }
};
