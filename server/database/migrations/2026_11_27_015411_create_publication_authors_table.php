<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publication_authors', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('publication_id');
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->string('external_name', 200)->nullable();
            $table->unsignedSmallInteger('author_order');
            $table->boolean('is_corresponding')->default(false);

            $table->index('staff_id', 'publication_authors_ix_1');
            $table->unique(['publication_id', 'author_order'], 'publication_authors_uq_1');
            $table->unique(['publication_id', 'staff_id'], 'publication_authors_uq_2');

            $table->foreign('publication_id', 'publication_authors_fk_1')->references('id')->on('publications');
            $table->foreign('staff_id', 'publication_authors_fk_2')->references('id')->on('staff_profiles');
        });

        DB::statement('ALTER TABLE `publication_authors` ADD CONSTRAINT `publication_authors_ck_1` CHECK ((((`staff_id` is not null) and (`external_name` is null)) or ((`staff_id` is null) and (`external_name` is not null))))');
        DB::statement('ALTER TABLE `publication_authors` ADD CONSTRAINT `publication_authors_ck_2` CHECK ((`author_order` > 0))');
        DB::statement('ALTER TABLE `publication_authors` ADD CONSTRAINT `publication_authors_ck_3` CHECK ((`is_corresponding` in (0,1)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_authors');
    }
};
