<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Extend document_text_extractions for asynchronous status tracking & error handling
        Schema::table('document_text_extractions', function (Blueprint $table) {
            $table->string('processing_status', 24)->default('completed')->after('confidence_score');
            $table->text('error_details')->nullable()->after('processing_status');
        });

        DB::statement("ALTER TABLE `document_text_extractions` MODIFY `raw_text` LONGTEXT NULL");
        DB::statement("ALTER TABLE `document_text_extractions` MODIFY `searchable_text` LONGTEXT NULL");

        // 2. Extend digital_signatures for cryptographic verification status & trust tier
        Schema::table('digital_signatures', function (Blueprint $table) {
            $table->string('verification_status', 32)->default('verified')->after('signature_format');
            $table->string('trust_tier', 32)->default('development_certificate')->after('verification_status');
            $table->json('certificate_metadata')->nullable()->after('trust_tier');
        });

        // 3. Extend workflow_tasks for asynchronous parallel branch tracking
        Schema::table('workflow_tasks', function (Blueprint $table) {
            $table->string('branch_key', 64)->nullable()->after('required_role_id');
        });

        // 4. Extend workflow_transitions for fork/join coordination
        Schema::table('workflow_transitions', function (Blueprint $table) {
            $table->string('join_policy', 32)->nullable()->after('approval_policy');
            $table->json('fork_state_ids')->nullable()->after('join_policy');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_transitions', function (Blueprint $table) {
            $table->dropColumn(['join_policy', 'fork_state_ids']);
        });

        Schema::table('workflow_tasks', function (Blueprint $table) {
            $table->dropColumn('branch_key');
        });

        Schema::table('digital_signatures', function (Blueprint $table) {
            $table->dropColumn(['verification_status', 'trust_tier', 'certificate_metadata']);
        });

        Schema::table('document_text_extractions', function (Blueprint $table) {
            $table->dropColumn(['processing_status', 'error_details']);
        });
    }
};
