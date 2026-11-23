<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('research_records', function (Blueprint $table) {
            if (!Schema::hasColumn('research_records', 'submitted_by')) {
                $table->unsignedBigInteger('submitted_by')->nullable()->after('workflow_status');
                $table->foreign('submitted_by', 'research_records_submitted_by_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('research_records', 'reviewed_by')) {
                $table->unsignedBigInteger('reviewed_by')->nullable()->after('submitted_by');
                $table->foreign('reviewed_by', 'research_records_reviewed_by_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('research_records', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('reviewed_by');
                $table->foreign('approved_by', 'research_records_approved_by_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('research_records', 'rejected_by')) {
                $table->unsignedBigInteger('rejected_by')->nullable()->after('approved_by');
                $table->foreign('rejected_by', 'research_records_rejected_by_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('research_records', 'submitted_at')) {
                $table->dateTime('submitted_at', 6)->nullable()->after('rejected_by');
            }
            if (!Schema::hasColumn('research_records', 'decision_remarks')) {
                $table->text('decision_remarks')->nullable()->after('submitted_at');
            }
        });

        Schema::table('project_proposals', function (Blueprint $table) {
            if (!Schema::hasColumn('project_proposals', 'submitted_by')) {
                $table->unsignedBigInteger('submitted_by')->nullable()->after('workflow_status');
                $table->foreign('submitted_by', 'project_proposals_submitted_by_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('project_proposals', 'reviewed_by')) {
                $table->unsignedBigInteger('reviewed_by')->nullable()->after('submitted_by');
                $table->foreign('reviewed_by', 'project_proposals_reviewed_by_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('project_proposals', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('reviewed_by');
                $table->foreign('approved_by', 'project_proposals_approved_by_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('project_proposals', 'rejected_by')) {
                $table->unsignedBigInteger('rejected_by')->nullable()->after('approved_by');
                $table->foreign('rejected_by', 'project_proposals_rejected_by_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            }
            if (!Schema::hasColumn('project_proposals', 'submitted_at')) {
                $table->dateTime('submitted_at', 6)->nullable()->after('rejected_by');
            }
            if (!Schema::hasColumn('project_proposals', 'decision_remarks')) {
                $table->text('decision_remarks')->nullable()->after('submitted_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('research_records', function (Blueprint $table) {
            if (Schema::hasColumn('research_records', 'decision_remarks')) {
                $table->dropColumn('decision_remarks');
            }
            if (Schema::hasColumn('research_records', 'submitted_at')) {
                $table->dropColumn('submitted_at');
            }
            if (Schema::hasColumn('research_records', 'rejected_by')) {
                $table->dropForeign('research_records_rejected_by_fk');
                $table->dropColumn('rejected_by');
            }
            if (Schema::hasColumn('research_records', 'approved_by')) {
                $table->dropForeign('research_records_approved_by_fk');
                $table->dropColumn('approved_by');
            }
            if (Schema::hasColumn('research_records', 'reviewed_by')) {
                $table->dropForeign('research_records_reviewed_by_fk');
                $table->dropColumn('reviewed_by');
            }
            if (Schema::hasColumn('research_records', 'submitted_by')) {
                $table->dropForeign('research_records_submitted_by_fk');
                $table->dropColumn('submitted_by');
            }
        });

        Schema::table('project_proposals', function (Blueprint $table) {
            if (Schema::hasColumn('project_proposals', 'decision_remarks')) {
                $table->dropColumn('decision_remarks');
            }
            if (Schema::hasColumn('project_proposals', 'submitted_at')) {
                $table->dropColumn('submitted_at');
            }
            if (Schema::hasColumn('project_proposals', 'rejected_by')) {
                $table->dropForeign('project_proposals_rejected_by_fk');
                $table->dropColumn('rejected_by');
            }
            if (Schema::hasColumn('project_proposals', 'approved_by')) {
                $table->dropForeign('project_proposals_approved_by_fk');
                $table->dropColumn('approved_by');
            }
            if (Schema::hasColumn('project_proposals', 'reviewed_by')) {
                $table->dropForeign('project_proposals_reviewed_by_fk');
                $table->dropColumn('reviewed_by');
            }
            if (Schema::hasColumn('project_proposals', 'submitted_by')) {
                $table->dropForeign('project_proposals_submitted_by_fk');
                $table->dropColumn('submitted_by');
            }
        });
    }
};
