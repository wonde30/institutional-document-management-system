<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FileObject;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StorageManagerService
{
    /**
     * Get the storage disk instance.
     */
    public function getDisk(?string $disk = null): Filesystem
    {
        $diskName = $disk ?: config('filesystems.default', 'local');
        return Storage::disk($diskName);
    }

    /**
     * Store a file or raw binary content and compute cryptographic metadata.
     *
     * @param string|UploadedFile $file
     */
    public function storeFile(
        mixed $file,
        string $directory = 'documents',
        ?string $disk = null,
        ?string $customFilename = null,
        ?string $mimeType = null
    ): array {
        $diskName = $disk ?: config('filesystems.default', 'local');
        $filesystem = Storage::disk($diskName);
        $currentYear = date('Y');
        $targetDir = rtrim($directory, '/') . '/' . $currentYear;

        if ($file instanceof UploadedFile) {
            $fileObject = app(FileSecurityService::class)->storeUploadedFile($file, $directory);

            return [
                'storage_disk' => $fileObject->storage_disk,
                'storage_key' => $fileObject->storage_key,
                'original_filename' => $fileObject->original_filename,
                'detected_mime' => $fileObject->detected_mime,
                'byte_size' => $fileObject->byte_size,
                'sha256' => $fileObject->sha256,
            ];
        } else {
            // Raw string bytes
            $sha256 = hash('sha256', (string) $file);
            $filename = static::sanitize($customFilename ?: ('doc_' . Str::random(12) . '.pdf'));
            $detectedMime = $mimeType ?: 'application/pdf';
            $byteSize = strlen((string) $file);
            $storageKey = $targetDir . '/' . Str::uuid() . '_' . $filename;
            if (! $filesystem->put($storageKey, (string) $file)) {
                throw new RuntimeException("Failed to store file on disk '{$diskName}'.");
            }
        }

        if (! $storageKey) {
            throw new RuntimeException("Failed to store file on disk '{$diskName}'.");
        }

        return [
            'storage_disk' => $diskName,
            'storage_key' => $storageKey,
            'original_filename' => $filename,
            'detected_mime' => $detectedMime,
            'byte_size' => $byteSize,
            'sha256' => $sha256,
        ];
    }

    /**
     * Retrieve the binary content of a stored FileObject.
     */
    public function getBytes(FileObject $fileObject): string
    {
        $disk = $fileObject->storage_disk ?: 'local';
        $key = $fileObject->storage_key;

        // Check if disk has file
        if (Storage::disk($disk)->exists($key)) {
            return Storage::disk($disk)->get($key);
        }

        // Fallback: check storage_path('app/' . $key) or storage_path('app/private/' . $key)
        $candidates = [
            storage_path('app/' . $key),
            storage_path('app/private/' . $key),
            storage_path($key),
        ];

        foreach ($candidates as $candidate) {
            if (File::exists($candidate)) {
                return File::get($candidate);
            }
        }

        throw new RuntimeException("File '{$key}' not found on disk '{$disk}'.");
    }

    /**
     * Verify the cryptographic SHA-256 integrity of a stored FileObject.
     */
    public function verifyIntegrity(FileObject $fileObject): array
    {
        try {
            $bytes = $this->getBytes($fileObject);
            $observedSha256 = hash('sha256', $bytes);
            $isValid = hash_equals($fileObject->sha256, $observedSha256);

            return [
                'valid' => $isValid,
                'expected_sha256' => $fileObject->sha256,
                'observed_sha256' => $observedSha256,
                'byte_size' => strlen($bytes),
                'error' => $isValid ? null : 'SHA-256 checksum mismatch (file modification or corruption detected)',
            ];
        } catch (Throwable $e) {
            return [
                'valid' => false,
                'expected_sha256' => $fileObject->sha256,
                'observed_sha256' => null,
                'byte_size' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Probe a disk to determine availability, write-read roundtrip, and response latency.
     */
    public function checkDiskStatus(string $diskName): array
    {
        $startTime = microtime(true);
        $probeKey = 'probes/.probe_' . Str::random(16) . '.tmp';
        $testPayload = 'WU_DMS_STORAGE_PROBE_' . time() . '_' . Str::random(32);
        $testSha256 = hash('sha256', $testPayload);

        try {
            $disk = Storage::disk($diskName);

            // Write probe
            $writeSuccess = $disk->put($probeKey, $testPayload);
            if (! $writeSuccess) {
                return [
                    'disk' => $diskName,
                    'status' => 'degraded',
                    'available' => false,
                    'latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    'error' => 'Write probe returned false.',
                ];
            }

            // Read probe
            $readPayload = $disk->get($probeKey);
            $readSha256 = hash('sha256', $readPayload);

            // Cleanup probe
            $disk->delete($probeKey);

            $latency = round((microtime(true) - $startTime) * 1000, 2);

            if ($readSha256 !== $testSha256) {
                return [
                    'disk' => $diskName,
                    'status' => 'corrupted',
                    'available' => false,
                    'latency_ms' => $latency,
                    'error' => 'Probe read checksum mismatch.',
                ];
            }

            return [
                'disk' => $diskName,
                'status' => 'operational',
                'available' => true,
                'latency_ms' => $latency,
                'driver' => config("filesystems.disks.{$diskName}.driver"),
                'error' => null,
            ];
        } catch (Throwable $e) {
            return [
                'disk' => $diskName,
                'status' => 'offline',
                'available' => false,
                'latency_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sanitize filename to alphanumeric and safe characters.
     */
    public function sanitizeFilename(string $name): string
    {
        return static::sanitize($name);
    }

    /**
     * Static helper for filename sanitization.
     */
    public static function sanitize(string $name): string
    {
        $clean = basename(str_replace(['\\', '/'], '/', $name));
        $clean = preg_replace('/[^A-Za-z0-9._-]/', '_', $clean) ?? '';
        $clean = preg_replace('/\.{2,}/', '.', $clean) ?? '';
        $clean = trim($clean, '._');

        return $clean !== '' ? $clean : 'document.dat';
    }
}

