<?php

namespace Tests\Feature;

use App\Services\Backup\BackupCoordinatorService;
use App\Services\Backup\DatabaseBackupService;
use App\Services\Backup\RestorationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DisasterRecoveryRestorationTest extends TestCase
{
    protected string $isolatedDb = 'wu_dms_recovery_test';

    protected function tearDown(): void
    {
        try {
            DB::purge('mysql_pitr_check');
            DB::statement("DROP DATABASE IF EXISTS `{$this->isolatedDb}`");
        } catch (\Throwable) {
            // Best effort
        }

        parent::tearDown();
    }

    public function test_full_backup_and_isolated_database_restoration_to_wu_dms_recovery_test(): void
    {
        $coordinator = app(BackupCoordinatorService::class);
        $restorationService = app(RestorationService::class);

        // Execute full backup
        $backupResult = $coordinator->executeFullBackup(false);
        $this->assertEquals('completed', $backupResult['status']);
        $this->assertFileExists($backupResult['manifest_path']);

        // Restore into isolated target database
        $restoreResult = $restorationService->restoreFromBackup(
            $backupResult['backup_id'],
            $this->isolatedDb
        );

        $this->assertEquals('SUCCESS', $restoreResult['status']);
        $this->assertEquals($this->isolatedDb, $restoreResult['target_database']);
        $this->assertGreaterThanOrEqual(118, $restoreResult['tables_restored']);
        $this->assertGreaterThan(0, $restoreResult['files_verified']);
        $this->assertGreaterThan(0, $restoreResult['rto_seconds']);
    }

    public function test_restoration_service_blocks_overwriting_live_production_database(): void
    {
        $backupService = app(DatabaseBackupService::class);
        $prodDb = config('database.connections.mysql.database');
        $dummySql = storage_path('app/testing_dummy.sql');
        File::put($dummySql, "-- MySQL dump test\nSELECT 1;\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Restoration blocked: Target database');
        
        try {
            $backupService->restore($dummySql, $prodDb, false);
        } finally {
            if (File::exists($dummySql)) {
                File::delete($dummySql);
            }
        }
    }

    public function test_corrupted_manifest_fails_closed(): void
    {
        $coordinator = app(BackupCoordinatorService::class);
        $restorationService = app(RestorationService::class);

        $backupResult = $coordinator->executeFullBackup(false);
        $manifestPath = $backupResult['manifest_path'];

        // Corrupt database SHA-256 in manifest
        $manifest = json_decode(file_get_contents($manifestPath), true);
        $manifest['database']['sha256'] = '0000000000000000000000000000000000000000000000000000000000000000';
        File::put($manifestPath, json_encode($manifest));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Integrity failure: Database artifact SHA-256 does not match manifest');
        $restorationService->restoreFromBackup($backupResult['backup_id'], $this->isolatedDb);
    }

    public function test_dr_drill_artisan_command_executes_successfully(): void
    {
        $exitCode = Artisan::call('dms:dr-drill');
        $this->assertEquals(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Baseline 118-table schema completeness: VERIFIED', $output);
        $this->assertStringContainsString('SHA-256 File Byte Preservation: 100% MATCH', $output);
        $this->assertStringContainsString('Real Backup Run', $output);
        $this->assertStringContainsString('Isolated Restore Drill', $output);
    }

    public function test_point_in_time_recovery_replays_binary_logs_into_isolated_database(): void
    {
        $dbService = app(DatabaseBackupService::class);
        $coordinator = app(BackupCoordinatorService::class);
        $restorationService = app(RestorationService::class);

        // 1. Take base backup and record binlog position
        $backupResult = $coordinator->executeFullBackup(false);
        $manifest = json_decode(file_get_contents($backupResult['manifest_path']), true);
        $binlogCoords = $manifest['database']['binlog_coordinates'];

        if (empty($binlogCoords)) {
            $this->markTestSkipped('Binary logging coordinates not available.');
        }

        $binlogFile = $binlogCoords['file'];

        // Resolve the binary log directory from the database server itself so the
        // test remains portable across development and CI environments.
        $binlogDirectory = null;
        try {
            $binlogVariable = DB::selectOne("SHOW VARIABLES LIKE 'log_bin_basename'");
            if ($binlogVariable && !empty($binlogVariable->Value)) {
                $binlogDirectory = dirname((string) $binlogVariable->Value);
            }
        } catch (\Throwable) {
            $binlogDirectory = null;
        }

        if ($binlogDirectory === null) {
            $this->markTestSkipped('Unable to determine the binary log directory from the database server.');
        }

        $binlogFullPath = rtrim($binlogDirectory, '/\\') . DIRECTORY_SEPARATOR . $binlogFile;

        if (!File::exists($binlogFullPath)) {
            $this->markTestSkipped("Binary log file {$binlogFullPath} not accessible.");
        }

        // 2. Restore the base backup into isolated database
        $restorationService->restoreFromBackup($backupResult['backup_id'], $this->isolatedDb);

        // 3. Capture current position right before delta insert
        $preDeltaStatus = DB::select('SHOW BINARY LOG STATUS');
        $preDeltaPos = (int) $preDeltaStatus[0]->Position;

        // 4. Perform a delta transaction in the active database
        $testEmail = 'pitr_' . bin2hex(random_bytes(4)) . '@wu.edu.et';
        DB::table('users')->insert([
            'name' => 'PITR Replay Test User',
            'email' => $testEmail,
            'password' => bcrypt('Secret123!'),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 5. Replay binary logs starting from preDeltaPos into isolated database
        $sourceDb = config('database.connections.mysql.database');
        $replayResult = $dbService->replayBinlogs(
            $binlogFullPath,
            $this->isolatedDb,
            $preDeltaPos,
            null,
            $sourceDb
        );

        $this->assertEquals('SUCCESS', $replayResult['status']);

        // 6. Verify the isolated database now contains the newly replayed record!
        $verifyConfig = config('database.connections.mysql');
        $verifyConfig['database'] = $this->isolatedDb;
        config(['database.connections.mysql_pitr_check' => $verifyConfig]);

        $replayedUser = DB::connection('mysql_pitr_check')
            ->table('users')
            ->where('email', $testEmail)
            ->first();

        $this->assertNotNull($replayedUser, 'The delta transaction from binary logs must be present in the recovered database.');
    }
}
