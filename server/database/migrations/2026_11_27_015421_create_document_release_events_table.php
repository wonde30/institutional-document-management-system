<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_release_events', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('document_version_id');
            $table->string('action', 16);
            $table->unsignedBigInteger('actor_id');
            $table->text('reason')->nullable();
            $table->dateTime('occurred_at', 6);

            $table->index(['document_version_id', 'occurred_at', 'id'], 'document_release_events_ix_1');
            $table->index('actor_id', 'document_release_events_ix_2');

            $table->foreign('document_version_id', 'document_release_events_fk_1')->references('id')->on('document_versions');
            $table->foreign('actor_id', 'document_release_events_fk_2')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `document_release_events` ADD CONSTRAINT `document_release_events_ck_1` CHECK ((`action` in (\'release\',\'withdraw\')))');
    }

    public function down(): void
    {
        Schema::dropIfExists('document_release_events');
    }
};
