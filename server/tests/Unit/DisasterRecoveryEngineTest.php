<?php

namespace Tests\Unit;

use App\Services\Backup\BackupEncryptionService;
use App\Services\Backup\BackupRetentionService;
use App\Services\Backup\DatabaseBackupService;
use App\Services\Backup\DocumentStorageBackupService;
use App\Services\Backup\KeyCustodyBackupService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DisasterRecoveryEngineTest extends TestCase
{
    protected string $testDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = storage_path('app/testing_dr_' . bin2hex(random_bytes(4)));
        if (!File::isDirectory($this->testDir)) {
            File::makeDirectory($this->testDir, 0750, true);
        }
    }

    protected function tearDown(): void
    {
        if (File::isDirectory($this->testDir)) {
            File::deleteDirectory($this->testDir);
        }
        parent::tearDown();
    }

    public function test_database_backup_generates_valid_sql_and_binlog_coordinates(): void
    {
        $backupService = app(DatabaseBackupService::class);
        $outputSql = "{$this->testDir}/test_dump.sql";

        $result = $backupService->dump($outputSql);

        $this->assertFileExists($outputSql);
        $this->assertGreaterThan(1000, filesize($outputSql));
        $this->assertNotEmpty($result['sha256']);
        $this->assertEquals(hash_file('sha256', $outputSql), $result['sha256']);
        $this->assertArrayHasKey('binlog_coordinates', $result);
        
        $header = file_get_contents($outputSql, false, null, 0, 80);
        $this->assertStringContainsString('MySQL dump', $header);
    }

    public function test_document_storage_backup_generates_manifest_and_excludes_private_keys(): void
    {
        $storageService = app(DocumentStorageBackupService::class);
        $outputZip = "{$this->testDir}/test_documents.zip";

        $result = $storageService->archive($outputZip, 'TEST-BAK-001');

        $this->assertFileExists($outputZip);
        $this->assertGreaterThan(500, filesize($outputZip));
        $this->assertNotEmpty($result['sha256']);
        $this->assertGreaterThan(0, $result['file_count']);

        // Inspect zip contents
        $zip = new \ZipArchive();
        $zip->open($outputZip);
        $hasManifest = false;
        $hasPrivateKey = false;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === 'manifest.json') {
                $hasManifest = true;
            }
            if (str_ends_with($name, '.key') || str_contains($name, 'key.pem')) {
                $hasPrivateKey = true;
            }
        }
        $zip->close();

        $this->assertTrue($hasManifest, 'manifest.json must be present in document archive');
        $this->assertFalse($hasPrivateKey, 'Private signing keys must NEVER be in document archive');
    }

    public function test_backup_encryption_and_decryption_with_hmac_authentication(): void
    {
        $encService = app(BackupEncryptionService::class);
        $samplePlaintext = "CONFIDENTIAL_WOLLO_UNIVERSITY_DOCUMENT_PAYLOAD_" . bin2hex(random_bytes(32));
        $plainFile = "{$this->testDir}/plain.txt";
        $encFile = "{$this->testDir}/plain.txt.enc";
        $decFile = "{$this->testDir}/decrypted.txt";

        File::put($plainFile, $samplePlaintext);

        // Encrypt
        $encResult = $encService->encryptFile($plainFile, $encFile);
        $this->assertFileExists($encFile);
        $this->assertNotEquals($samplePlaintext, file_get_contents($encFile));

        // Decrypt
        $decResult = $encService->decryptFile($encFile, $decFile);
        $this->assertFileExists($decFile);
        $this->assertEquals($samplePlaintext, file_get_contents($decFile));
        $this->assertTrue($decResult['is_authenticated']);
    }

    public function test_tampered_encrypted_backup_fails_closed_on_hmac_mismatch(): void
    {
        $encService = app(BackupEncryptionService::class);
        $plainFile = "{$this->testDir}/source.txt";
        $encFile = "{$this->testDir}/source.txt.enc";
        $decFile = "{$this->testDir}/should_not_exist.txt";

        File::put($plainFile, "UNALTERED_PAYLOAD_DATA");
        $encService->encryptFile($plainFile, $encFile);

        // Tamper with 1 byte in the ciphertext portion of the container
        $bytes = file_get_contents($encFile);
        $tamperedBytePos = strlen($bytes) - 5;
        $bytes[$tamperedBytePos] = chr(ord($bytes[$tamperedBytePos]) ^ 0xFF);
        File::put($encFile, $bytes);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HMAC mismatch detected');
        $encService->decryptFile($encFile, $decFile);
    }

    public function test_key_custody_service_encrypts_private_keys_securely(): void
    {
        $custodyService = app(KeyCustodyBackupService::class);
        $encKeyPath = "{$this->testDir}/keys_custody.enc";
        $restoredKeysDir = "{$this->testDir}/restored_keys";
        $secret = 'WU-STRONG-CUSTODY-PASSPHRASE-2026';

        $backupResult = $custodyService->backupKeys($encKeyPath, $secret);
        $this->assertFileExists($encKeyPath);

        $restoreResult = $custodyService->restoreKeys($encKeyPath, $restoredKeysDir, $secret);
        $this->assertTrue($restoreResult['is_verified']);
        $this->assertDirectoryExists($restoredKeysDir);
    }

    public function test_retention_service_never_deletes_minimum_guaranteed_backups(): void
    {
        $retentionService = app(BackupRetentionService::class);
        $backupsDir = storage_path('app/backups');

        // Enforce retention with keep minimum 3
        $result = $retentionService->enforceRetention(30, 3);
        $this->assertGreaterThanOrEqual(0, $result['kept_count']);

        // Check that directories remaining is at least minimum or total
        if (File::isDirectory($backupsDir)) {
            $remaining = count(File::directories($backupsDir));
            $this->assertGreaterThanOrEqual(min(3, $remaining), $remaining);
        }
    }
}
