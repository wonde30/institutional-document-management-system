<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_responses', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->unsignedBigInteger('assignment_id');
            $table->unsignedBigInteger('response_document_id');
            $table->string('relationship_role', 24)->default('full_reply');
            $table->boolean('is_fulfilling')->default(true);
            $table->unsignedBigInteger('linked_by');
            $table->dateTime('linked_at', 6);

            $table->primary(['assignment_id', 'response_document_id']);

            $table->index('response_document_id', 'assignment_responses_ix_1');
            $table->index('linked_by', 'assignment_responses_ix_2');

            $table->foreign('assignment_id', 'assignment_responses_fk_1')->references('id')->on('document_assignments')->restrictOnDelete();
            $table->foreign('response_document_id', 'assignment_responses_fk_2')->references('id')->on('documents')->restrictOnDelete();
            $table->foreign('linked_by', 'assignment_responses_fk_3')->references('id')->on('users')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE `assignment_responses` ADD CONSTRAINT `assignment_responses_ck_1` CHECK ((`relationship_role` in (\'full_reply\',\'interim_reply\',\'supporting_report\',\'partial_reply\')))');
        DB::statement('ALTER TABLE `assignment_responses` ADD CONSTRAINT `assignment_responses_ck_2` CHECK ((`is_fulfilling` in (0,1)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_responses');
    }
};
