<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            throw new RuntimeException('WU baseline requires Oracle MySQL 8.4+, not SQLite/MariaDB.');
        }
        $version = DB::selectOne('SELECT VERSION() AS v')->v;
        if (stripos($version, 'MariaDB') !== false || version_compare(preg_replace('/[^0-9.].*$/', '', $version), '8.4.0', '<')) {
            throw new RuntimeException('WU baseline requires Oracle MySQL 8.4+.');
        }
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Baseline is development/testing only. Prepare reviewed incremental migrations for other environments.');
        }
        foreach ([
            'unit_types',
            'users',
            'organization_units',
            'organization_memberships',
            'primary_memberships',
            'permissions',
            'roles',
            'role_permissions',
            'authorization_scopes',
            'user_roles',
            'user_permissions',
            'document_categories',
            'confidentiality_levels',
            'reference_sequences',
            'documents',
            'tags',
            'document_tags',
            'file_objects',
            'document_versions',
            'version_files',
            'file_derivatives',
            'document_release_events',
            'document_relation_types',
            'document_relations',
            'document_grants',
            'document_comments',
            'correspondence_types',
            'priorities',
            'workflow_definitions',
            'workflow_versions',
            'workflow_states',
            'workflow_transitions',
            'workflow_instances',
            'workflow_tasks',
            'workflow_task_decisions',
            'workflow_events',
            'workflow_decision_events',
            'office_procedure_versions',
            'external_parties',
            'external_party_contacts',
            'communication_parties',
            'correspondences',
            'correspondence_procedures',
            'correspondence_participants',
            'dispatch_methods',
            'correspondence_dispatches',
            'document_movements',
            'movement_receipts',
            'document_assignments',
            'assignment_events',
            'archive_collections',
            'retention_policies',
            'retention_policy_versions',
            'archive_entries',
            'archive_actions',
            'legal_holds',
            'legal_hold_documents',
            'disposition_requests',
            'disposition_decisions',
            'disposition_executions',
            'file_integrity_checks',
            'staff_profiles',
            'institutions',
            'qualification_types',
            'staff_qualifications',
            'staff_certificates',
            'staff_employment_records',
            'hr_record_types',
            'staff_documents',
            'physical_storage_locations',
            'physical_originals',
            'physical_custody_events',
            'digitization_records',
            'physical_disposition_targets',
            'research_records',
            'research_authors',
            'project_types',
            'project_proposals',
            'project_proposal_members',
            'project_proposal_workflows',
            'projects',
            'project_progress_reports',
            'publications',
            'publication_authors',
            'research_publications',
            'project_publications',
            'accreditation_frameworks',
            'accreditation_framework_versions',
            'accreditation_standards',
            'accreditation_cycles',
            'accreditation_evidence',
            'accreditation_reviews',
            'signing_identities',
            'signature_requests',
            'digital_signatures',
            'signature_verifications',
            'stamp_definitions',
            'stamp_versions',
            'stamp_authorizations',
            'stamp_applications',
            'audit_events',
            'notification_events',
            'user_notifications',
            'notification_deliveries',
            'outbox_events',
            'idempotency_records',
            'export_jobs',
            'import_batches',
            'import_rows',
            'backup_runs',
            'restore_drills',
            'password_reset_tokens',
            'sessions',
            'jobs',
            'job_batches',
            'failed_jobs',
            'cache',
            'cache_locks'
        ] as $name) {
            if (Schema::hasTable($name)) {
                throw new RuntimeException('Existing table conflicts with WU baseline: '.$name.'. Reconcile instead of dropping it.');
            }
        }
        DB::statement("SET time_zone = '+00:00'");
        $mode = DB::selectOne('SELECT @@session.sql_mode AS mode')->mode;
        if (! str_contains($mode, 'STRICT_TRANS_TABLES') && ! str_contains($mode, 'STRICT_ALL_TABLES')) {
            throw new RuntimeException('Strict SQL mode is required.');
        }
        DB::statement('SET SESSION foreign_key_checks = 1');
    }
    public function down(): void { /* No schema created by this preflight. */ }
};
