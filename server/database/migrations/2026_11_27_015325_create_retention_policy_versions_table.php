<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retention_policy_versions', function (Blueprint $table) {
            $table->collation('utf8mb4_0900_as_cs');

            $table->id();
            $table->unsignedBigInteger('policy_id');
            $table->unsignedInteger('version_number');
            $table->string('trigger_code', 64);
            $table->unsignedInteger('duration_months')->nullable();
            $table->string('disposition_action', 16);
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at', 6)->nullable();
            $table->date('effective_from');

            $table->index('approved_by', 'retention_policy_versions_ix_1');
            $table->unique(['policy_id', 'version_number'], 'retention_policy_versions_uq_1');

            $table->foreign('policy_id', 'retention_policy_versions_fk_1')->references('id')->on('retention_policies');
            $table->foreign('approved_by', 'retention_policy_versions_fk_2')->references('id')->on('users');
        });

        DB::statement('ALTER TABLE `retention_policy_versions` ADD CONSTRAINT `retention_policy_versions_ck_1` CHECK ((`version_number` > 0))');
        DB::statement('ALTER TABLE `retention_policy_versions` ADD CONSTRAINT `retention_policy_versions_ck_2` CHECK ((`disposition_action` in (\'review\',\'transfer\',\'retain\',\'destroy\')))');
        DB::statement('ALTER TABLE `retention_policy_versions` ADD CONSTRAINT `retention_policy_versions_ck_3` CHECK ((((`approved_by` is null) and (`approved_at` is null)) or ((`approved_by` is not null) and (`approved_at` is not null))))');
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_policy_versions');
    }
};
