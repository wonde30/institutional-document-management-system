<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class DocumentStorageBackupService
{
    /**
     * Archive document storage repositories and generate a cryptographic SHA-256 manifest.
     * Strictly excludes private signing keys from document archives.
     */
    public function archive(string $outputZipPath, string $backupId): array
    {
        $startTime = microtime(true);
        $outputDir = dirname($outputZipPath);
        if (!File::isDirectory($outputDir)) {
            File::makeDirectory($outputDir, 0750, true);
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($outputZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($openResult !== true) {
            throw new RuntimeException("Failed to initialize zip archive at '{$outputZipPath}', code: {$openResult}");
        }

        $manifestEntries = [];
        $totalBytes = 0;

        // 1. Gather files from document storage locations
        $sourceLocations = [
            'documents' => storage_path('app/documents'),
            'private_documents' => storage_path('app/private/documents'),
            'public_certs' => storage_path('app/pki/certs'),
        ];

        // Include institutional CA public certificate only (exclude .key)
        $caCertPath = storage_path('app/pki/ca/institutional_ca.crt');
        if (File::exists($caCertPath)) {
            $sha256 = hash_file('sha256', $caCertPath);
            $size = filesize($caCertPath);
            $totalBytes += $size;
            $zip->addFile($caCertPath, 'pki/ca/institutional_ca.crt');
            $manifestEntries[] = [
                'archive_path' => 'pki/ca/institutional_ca.crt',
                'byte_size' => $size,
                'sha256' => $sha256,
                'source_type' => 'ca_public_certificate',
            ];
        }

        foreach ($sourceLocations as $groupName => $dirPath) {
            if (File::isDirectory($dirPath)) {
                $files = File::allFiles($dirPath);
                foreach ($files as $file) {
                    $ext = strtolower($file->getExtension());
                    // NEVER include private keys in document archive
                    if ($ext === 'key' || str_contains($file->getFilename(), 'key.pem')) {
                        continue;
                    }

                    $relativePath = "storage/{$groupName}/" . str_replace('\\', '/', $file->getRelativePathname());
                    $fullPath = $file->getRealPath();
                    $sha256 = hash_file('sha256', $fullPath);
                    $size = $file->getSize();
                    $totalBytes += $size;

                    $zip->addFile($fullPath, $relativePath);
                    $manifestEntries[] = [
                        'archive_path' => $relativePath,
                        'original_filename' => $file->getFilename(),
                        'byte_size' => $size,
                        'sha256' => $sha256,
                        'source_type' => $groupName,
                    ];
                }
            }
        }

        // 2. Cross-reference file_objects table in MySQL
        $dbFileRecords = [];
        try {
            $dbFiles = DB::table('file_objects')->get();
            foreach ($dbFiles as $df) {
                $dbFileRecords[] = [
                    'id' => $df->id,
                    'storage_disk' => $df->storage_disk,
                    'storage_key' => $df->storage_key,
                    'original_filename' => $df->original_filename,
                    'sha256' => $df->sha256,
                    'byte_size' => $df->byte_size,
                ];
            }
        } catch (\Throwable $e) {
            // If DB query fails or during standalone file backup
        }

        $manifestData = [
            'backup_id' => $backupId,
            'created_at' => now()->toIso8601String(),
            'total_files' => count($manifestEntries),
            'total_bytes' => $totalBytes,
            'db_file_objects_count' => count($dbFileRecords),
            'manifest_version' => '1.0',
            'files' => $manifestEntries,
            'db_references' => $dbFileRecords,
        ];

        $manifestJson = json_encode($manifestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $zip->addFromString('manifest.json', $manifestJson);

        $closeResult = $zip->close();
        if (!$closeResult) {
            throw new RuntimeException("Failed to finalize zip archive: {$outputZipPath}");
        }

        if (!File::exists($outputZipPath) || filesize($outputZipPath) === 0) {
            throw new RuntimeException("Document archive generated but file is missing or zero bytes: {$outputZipPath}");
        }

        $archiveSha256 = hash_file('sha256', $outputZipPath);
        $archiveSize = filesize($outputZipPath);
        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        return [
            'archive_path' => $outputZipPath,
            'backup_id' => $backupId,
            'file_count' => count($manifestEntries),
            'uncompressed_bytes' => $totalBytes,
            'archive_byte_size' => $archiveSize,
            'sha256' => $archiveSha256,
            'duration_ms' => $durationMs,
            'completed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Restore document archive to an isolated target storage directory and verify every file against manifest.
     */
    public function restore(string $zipPath, string $targetDirectory): array
    {
        $startTime = microtime(true);
        if (!File::exists($zipPath)) {
            throw new RuntimeException("Document archive file not found: {$zipPath}");
        }

        if (!File::isDirectory($targetDirectory)) {
            File::makeDirectory($targetDirectory, 0750, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("Failed to open document archive for extraction: {$zipPath}");
        }

        $manifestContent = $zip->getFromName('manifest.json');
        if ($manifestContent === false) {
            $zip->close();
            throw new RuntimeException("Archive corruption: Missing manifest.json in {$zipPath}");
        }

        $manifest = json_decode($manifestContent, true);
        if (!$manifest || !isset($manifest['files'])) {
            $zip->close();
            throw new RuntimeException("Archive corruption: Invalid or unparseable manifest.json");
        }

        // Extract files
        $extractResult = $zip->extractTo($targetDirectory);
        $zip->close();
        if (!$extractResult) {
            throw new RuntimeException("Failed to extract archive files to target directory: {$targetDirectory}");
        }

        // Verify SHA-256 byte-level integrity for every extracted file
        $verifiedCount = 0;
        $corruptedFiles = [];
        $missingFiles = [];

        foreach ($manifest['files'] as $entry) {
            $normPath = str_replace('\\', '/', $entry['archive_path']);
            $extractedFilePath = $targetDirectory . '/' . $normPath;
            if (!File::exists($extractedFilePath)) {
                $missingFiles[] = $entry['archive_path'];
                continue;
            }

            $observedSha256 = hash_file('sha256', $extractedFilePath);
            if ($observedSha256 !== $entry['sha256']) {
                $corruptedFiles[] = [
                    'path' => $entry['archive_path'],
                    'expected_sha256' => $entry['sha256'],
                    'observed_sha256' => $observedSha256,
                ];
            } else {
                $verifiedCount++;
            }
        }

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);
        $isClean = empty($corruptedFiles) && empty($missingFiles);

        return [
            'target_directory' => $targetDirectory,
            'manifest_files_count' => count($manifest['files']),
            'verified_files_count' => $verifiedCount,
            'missing_files' => $missingFiles,
            'corrupted_files' => $corruptedFiles,
            'is_verified' => $isClean,
            'duration_ms' => $durationMs,
            'completed_at' => now()->toIso8601String(),
        ];
    }
}
