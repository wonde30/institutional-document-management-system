<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * Adds MySQL FULLTEXT index on documents(title, summary) to enable high-performance
     * natural language and boolean full-text querying across English and Ethiopic text.
     */
    public function up(): void
    {
        if (Schema::hasTable('documents')) {
            $hasIndex = collect(DB::select("SHOW INDEX FROM `documents` WHERE Key_name = 'documents_fx_title_summary'"))->isNotEmpty();
            if (! $hasIndex) {
                DB::statement('ALTER TABLE `documents` ADD FULLTEXT KEY `documents_fx_title_summary` (`title`, `summary`)');
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('documents')) {
            $hasIndex = collect(DB::select("SHOW INDEX FROM `documents` WHERE Key_name = 'documents_fx_title_summary'"))->isNotEmpty();
            if ($hasIndex) {
                DB::statement('ALTER TABLE `documents` DROP INDEX `documents_fx_title_summary`');
            }
        }
    }
};
