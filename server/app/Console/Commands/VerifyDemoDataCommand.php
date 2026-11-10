<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FileObject;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class VerifyDemoDataCommand extends Command
{
    protected $signature = 'dms:verify-demo';
    protected $description = 'Verify relational integrity, demo user accounts, and physical file checksums in wu_dms_demo';

    public function handle(): int
    {
        $this->info('================================================================');
        $this->info(' Wollo University DMS - Demonstration Data Integrity Verifier   ');
        $this->info('================================================================');

        // 1. Strict Target Database Guard
        try {
            $currentDb = DB::select('SELECT DATABASE() as db')[0]->db ?? null;
            $configuredDb = config('database.connections.mysql.database');
        } catch (Throwable $e) {
            $this->error("Database connection failed: {$e->getMessage()}");
            return 1;
        }

        if ($currentDb !== 'wu_dms_demo' || $configuredDb !== 'wu_dms_demo') {
            $this->error("CRITICAL SAFETY VIOLATION: Current database is [{$currentDb}].");
            $this->error("This verification command is strictly restricted to database 'wu_dms_demo'.");
            return 1;
        }

        $results = [];
        $hasFailures = false;

        // 2. Verify Demo User Accounts
        $demoEmails = [
            'demo.admin@example.test',
            'demo.records@example.test',
            'demo.head.cs@example.test',
            'demo.head.eng@example.test',
            'demo.approver@example.test',
            'demo.archivist@example.test',
            'demo.research@example.test',
            'demo.qa@example.test',
            'demo.hr@example.test',
            'demo.finance@example.test',
            'demo.auditor@example.test',
            'demo.staff@example.test',
        ];

        $existingUsersCount = User::whereIn('email', $demoEmails)->count();
        if ($existingUsersCount === count($demoEmails)) {
            $results[] = ['Demo User Accounts', 'PASS', "All 12 required synthetic demo accounts exist"];
        } else {
            $hasFailures = true;
            $results[] = ['Demo User Accounts', 'FAIL', "Found {$existingUsersCount}/12 required synthetic accounts"];
        }

        // Verify Staff Profiles
        $profilesCount = DB::table('staff_profiles')
            ->join('users', 'staff_profiles.user_id', '=', 'users.id')
            ->whereIn('users.email', $demoEmails)
            ->count();
        if ($profilesCount === count($demoEmails)) {
            $results[] = ['Staff Profiles', 'PASS', "All 12 demo users have linked staff profiles"];
        } else {
            $hasFailures = true;
            $results[] = ['Staff Profiles', 'FAIL', "Found {$profilesCount}/12 linked staff profiles"];
        }

        // Verify Primary Memberships
        $membershipsCount = DB::table('organization_memberships')
            ->join('users', 'organization_memberships.user_id', '=', 'users.id')
            ->whereIn('users.email', $demoEmails)
            ->count();
        if ($membershipsCount >= count($demoEmails)) {
            $results[] = ['Org Memberships', 'PASS', "{$membershipsCount} institutional memberships registered"];
        } else {
            $hasFailures = true;
            $results[] = ['Org Memberships', 'FAIL', "Incomplete organization memberships ({$membershipsCount})"];
        }

        // 3. Verify Referential Integrity
        $fkChecks = [
            'Documents -> Units' => 'SELECT COUNT(*) as cnt FROM documents d LEFT JOIN organization_units u ON d.owner_unit_id = u.id WHERE u.id IS NULL',
            'Documents -> Categories' => 'SELECT COUNT(*) as cnt FROM documents d LEFT JOIN document_categories c ON d.category_id = c.id WHERE c.id IS NULL',
            'Versions -> Documents' => 'SELECT COUNT(*) as cnt FROM document_versions v LEFT JOIN documents d ON v.document_id = d.id WHERE d.id IS NULL',
            'Version Files -> Files' => 'SELECT COUNT(*) as cnt FROM version_files vf LEFT JOIN file_objects fo ON vf.file_object_id = fo.id WHERE fo.id IS NULL',
            'Movements -> Documents' => 'SELECT COUNT(*) as cnt FROM document_movements dm LEFT JOIN documents d ON dm.document_id = d.id WHERE d.id IS NULL',
            'Correspondences -> Priorities' => 'SELECT COUNT(*) as cnt FROM correspondences c LEFT JOIN priorities p ON c.priority_id = p.id WHERE p.id IS NULL',
            'Correspondences -> Participants' => 'SELECT COUNT(*) as cnt FROM correspondence_participants cp LEFT JOIN correspondences c ON cp.correspondence_id = c.id WHERE c.id IS NULL',
            'Workflow Instances -> Versions' => 'SELECT COUNT(*) as cnt FROM workflow_instances wi LEFT JOIN workflow_versions wv ON wi.workflow_version_id = wv.id WHERE wv.id IS NULL',
            'Workflow Tasks -> Instances' => 'SELECT COUNT(*) as cnt FROM workflow_tasks wt LEFT JOIN workflow_instances wi ON wt.instance_id = wi.id WHERE wi.id IS NULL',
            'Archive Entries -> Collections' => 'SELECT COUNT(*) as cnt FROM archive_entries ae LEFT JOIN archive_collections ac ON ae.collection_id = ac.id WHERE ac.id IS NULL',
            'Research Authors -> Records' => 'SELECT COUNT(*) as cnt FROM research_authors ra LEFT JOIN research_records rr ON ra.research_id = rr.id WHERE rr.id IS NULL',
            'Accreditation Evidence -> Standards' => 'SELECT COUNT(*) as cnt FROM accreditation_evidence ae LEFT JOIN accreditation_standards s ON ae.standard_id = s.id WHERE s.id IS NULL',
        ];

        foreach ($fkChecks as $checkName => $query) {
            $orphans = DB::select($query)[0]->cnt ?? 0;
            if ($orphans === 0) {
                $results[] = ["FK: {$checkName}", 'PASS', 'Zero orphaned records detected'];
            } else {
                $hasFailures = true;
                $results[] = ["FK: {$checkName}", 'FAIL', "{$orphans} orphaned records detected"];
            }
        }

        // 4. Verify Physical Files & Checksums
        $fileObjects = FileObject::all();
        $fileCount = $fileObjects->count();
        $missingFiles = 0;
        $corruptFiles = 0;
        $unwatermarked = 0;

        foreach ($fileObjects as $fo) {
            $disk = Storage::disk($fo->storage_disk);
            if (!$disk->exists($fo->storage_key)) {
                $missingFiles++;
                continue;
            }

            $fullPath = $disk->path($fo->storage_key);
            $actualHash = hash_file('sha256', $fullPath);
            if ($actualHash !== $fo->sha256) {
                $corruptFiles++;
            }

            $content = $disk->get($fo->storage_key);
            if (!str_contains($content, 'DEMONSTRATION DATA - NOT AN OFFICIAL WOLLO UNIVERSITY RECORD')) {
                $unwatermarked++;
            }
        }

        if ($fileCount > 0 && $missingFiles === 0 && $corruptFiles === 0 && $unwatermarked === 0) {
            $results[] = ['Physical File Storage', 'PASS', "{$fileCount}/{$fileCount} physical files exist on disk with valid SHA-256 hashes & watermark"];
        } else {
            $hasFailures = true;
            $results[] = ['Physical File Storage', 'FAIL', "Missing: {$missingFiles}, Hash Mismatch: {$corruptFiles}, Unwatermarked: {$unwatermarked} (Total: {$fileCount})"];
        }

        // 5. Output Verification Matrix
        $this->table(['Integrity Check Category', 'Status', 'Diagnostic Details'], $results);

        if ($hasFailures) {
            $this->error('Demonstration database verification FAILED. Please review the table above.');
            return 1;
        }

        $this->info('✓ All demonstration data integrity checks PASSED successfully!');
        return 0;
    }
}
