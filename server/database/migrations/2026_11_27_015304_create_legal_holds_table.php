<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_holds', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->string('code', 64);
            $table->text('reason');
            $table->unsignedBigInteger('placed_by');
            $table->dateTime('placed_at', 6);
            $table->unsignedBigInteger('released_by')->nullable();
            $table->dateTime('released_at', 6)->nullable();

            $table->index('placed_by', 'legal_holds_ix_1');
            $table->index('released_by', 'legal_holds_ix_2');
            $table->unique('code', 'legal_holds_uq_1');

            $table->foreign('placed_by', 'legal_holds_fk_1')->references('id')->on('users');
            $table->foreign('released_by', 'legal_holds_fk_2')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `legal_holds` ADD CONSTRAINT `legal_holds_ck_1` CHECK ((((`released_by` is null) and (`released_at` is null)) or ((`released_by` is not null) and (`released_at` is not null) and (`released_at` >= `placed_at`))))');
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_holds');
    }
};
