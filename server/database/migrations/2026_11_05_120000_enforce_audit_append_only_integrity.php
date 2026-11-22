<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Security Hardening: Audit Trail Append-Only Enforcement
 * 
 * This migration adds database-level protections to ensure audit_events
 * are structurally immutable. The application layer already enforces
 * immutability through Eloquent model events, but this adds defense-in-depth
 * at the database tier.
 * 
 * Production Deployment Requirements:
 * 
 * 1. Application database account must have ONLY these grants on audit_events:
 *    - SELECT
 *    - INSERT
 * 
 * 2. The UPDATE and DELETE grants MUST be revoked:
 *    REVOKE UPDATE, DELETE ON dms.audit_events FROM 'dms_app'@'%';
 * 
 * 3. Only the DBA account should retain UPDATE/DELETE for emergency recovery.
 * 
 * 4. Verify with:
 *    SHOW GRANTS FOR 'dms_app'@'%';
 * 
 * See docs/deployment/PRODUCTION_DEPLOYMENT_GUIDE.md for complete instructions.
 */
return new class extends Migration {
    public function up(): void
    {
        // Add table comment documenting append-only policy
        DB::statement(
            "ALTER TABLE `audit_events` COMMENT = 'Append-only audit trail. Application account must not have UPDATE or DELETE grants.'"
        );

        // Skip trigger creation in test environment to allow transaction rollbacks
        if (app()->environment('testing')) {
            return;
        }

        // Create a database trigger as an additional integrity layer
        // This trigger prevents any UPDATE attempts at the database level
        DB::unprepared(<<<'SQL'
CREATE TRIGGER audit_events_prevent_update
BEFORE UPDATE ON audit_events
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'AUDIT INTEGRITY VIOLATION: audit_events table is append-only and cannot be modified.';
END
SQL
        );

        // Create a database trigger to prevent DELETE attempts at the database level
        DB::unprepared(<<<'SQL'
CREATE TRIGGER audit_events_prevent_delete
BEFORE DELETE ON audit_events
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'AUDIT INTEGRITY VIOLATION: audit_events table is append-only and cannot be deleted.';
END
SQL
        );

        // Log migration completion
        DB::insert(
            'INSERT INTO audit_events (actor_id, action, target_type, target_id, request_id, occurred_at, ip_address, user_agent, details_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                null,
                'system.audit_integrity_enforced',
                'audit_events',
                '0',
                \Illuminate\Support\Str::uuid()->toString(),
                now()->format('Y-m-d H:i:s.u'),
                '127.0.0.1',
                'Database Migration',
                json_encode([
                    'migration' => '2026_11_05_120000_enforce_audit_append_only_integrity',
                    'triggers_created' => ['audit_events_prevent_update', 'audit_events_prevent_delete'],
                    'deployment_requirement' => 'Revoke UPDATE and DELETE grants from application account',
                ])
            ]
        );
    }

    public function down(): void
    {
        // Drop the triggers
        DB::unprepared('DROP TRIGGER IF EXISTS audit_events_prevent_update');
        DB::unprepared('DROP TRIGGER IF EXISTS audit_events_prevent_delete');

        // Remove table comment
        DB::statement("ALTER TABLE `audit_events` COMMENT = ''");
    }
};
