<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_authors', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('research_id');
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->string('external_name', 200)->nullable();
            $table->unsignedSmallInteger('author_order');
            $table->string('contribution', 200)->nullable();

            $table->index('staff_id', 'research_authors_ix_1');
            $table->unique(['research_id', 'author_order'], 'research_authors_uq_1');
            $table->unique(['research_id', 'staff_id'], 'research_authors_uq_2');

            $table->foreign('research_id', 'research_authors_fk_1')->references('id')->on('research_records');
            $table->foreign('staff_id', 'research_authors_fk_2')->references('id')->on('staff_profiles');
        });

        DB::statement('ALTER TABLE `research_authors` ADD CONSTRAINT `research_authors_ck_1` CHECK ((((`staff_id` is not null) and (`external_name` is null)) or ((`staff_id` is null) and (`external_name` is not null))))');
        DB::statement('ALTER TABLE `research_authors` ADD CONSTRAINT `research_authors_ck_2` CHECK ((`author_order` > 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('research_authors');
    }
};
