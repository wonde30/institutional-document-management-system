<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disposition_decisions', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('request_id');
            $table->unsignedBigInteger('decided_by');
            $table->string('decision', 16);
            $table->text('reason');
            $table->dateTime('decided_at', 6);

            $table->index('decided_by', 'disposition_decisions_ix_1');
            $table->unique('request_id', 'disposition_decisions_uq_1');

            $table->foreign('request_id', 'disposition_decisions_fk_1')->references('id')->on('disposition_requests');
            $table->foreign('decided_by', 'disposition_decisions_fk_2')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `disposition_decisions` ADD CONSTRAINT `disposition_decisions_ck_1` CHECK ((`decision` in (\'approve\',\'reject\')))');
    }

    public function down(): void
    {
        Schema::dropIfExists('disposition_decisions');
    }
};
