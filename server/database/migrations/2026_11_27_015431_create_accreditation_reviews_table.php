<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accreditation_reviews', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('evidence_id');
            $table->unsignedBigInteger('reviewer_id');
            $table->string('decision', 16);
            $table->text('comment')->nullable();
            $table->dateTime('reviewed_at', 6);

            $table->index(['evidence_id', 'reviewed_at', 'id'], 'accreditation_reviews_ix_1');
            $table->index('reviewer_id', 'accreditation_reviews_ix_2');

            $table->foreign('evidence_id', 'accreditation_reviews_fk_1')->references('id')->on('accreditation_evidence');
            $table->foreign('reviewer_id', 'accreditation_reviews_fk_2')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `accreditation_reviews` ADD CONSTRAINT `accreditation_reviews_ck_1` CHECK ((`decision` in (\'accepted\',\'rejected\',\'needs_revision\')))');
    }

    public function down(): void
    {
        Schema::dropIfExists('accreditation_reviews');
    }
};
