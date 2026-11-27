<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correspondence_participants', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('correspondence_id');
            $table->unsignedBigInteger('party_id');
            $table->string('participation_type', 16);

            $table->index('party_id', 'correspondence_participants_ix_1');
            $table->unique(['correspondence_id', 'party_id', 'participation_type'], 'correspondence_participants_uq_1');

            $table->foreign('correspondence_id', 'correspondence_participants_fk_1')->references('id')->on('correspondences');
            $table->foreign('party_id', 'correspondence_participants_fk_2')->references('id')->on('communication_parties');
        });

        DB::statement('ALTER TABLE `correspondence_participants` ADD CONSTRAINT `correspondence_participants_ck_1` CHECK ((`participation_type` in (\'sender\',\'to\',\'cc\')))');
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondence_participants');
    }
};
