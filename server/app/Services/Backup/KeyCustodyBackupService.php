<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class KeyCustodyBackupService
{
    protected BackupEncryptionService $encryptionService;

    public function __construct(BackupEncryptionService $encryptionService)
    {
        $this->encryptionService = $encryptionService;
    }

    /**
     * Package and encrypt private cryptographic keys in a strictly isolated key custody archive.
     * Private keys are NEVER stored unencrypted.
     */
    public function backupKeys(string $outputEncryptedPath, string $custodySecret): array
    {
        $tempZip = storage_path('app/pki/temp_keys_' . bin2hex(random_bytes(6)) . '.zip');
        $zip = new ZipArchive();
        if ($zip->open($tempZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Failed to initialize temporary key custody zip.");
        }

        $keyFilesCount = 0;

        // 1. Institutional CA private key
        $caKeyPath = storage_path('app/pki/ca/institutional_ca.key');
        if (File::exists($caKeyPath)) {
            $zip->addFile($caKeyPath, 'ca/institutional_ca.key');
            $keyFilesCount++;
        }

        // 2. User signing private keys
        $keysDir = storage_path('app/pki/keys');
        if (File::isDirectory($keysDir)) {
            foreach (File::allFiles($keysDir) as $keyFile) {
                if (str_ends_with($keyFile->getFilename(), '.pem') || str_ends_with($keyFile->getFilename(), '.key')) {
                    $zip->addFile($keyFile->getRealPath(), 'keys/' . $keyFile->getFilename());
                    $keyFilesCount++;
                }
            }
        }

        $zip->close();

        try {
            // Encrypt using AES-256-CBC + HMAC
            $encryptResult = $this->encryptionService->encryptFile($tempZip, $outputEncryptedPath, $custodySecret);
            $encryptResult['key_files_count'] = $keyFilesCount;
            return $encryptResult;
        } finally {
            if (File::exists($tempZip)) {
                File::delete($tempZip);
            }
        }
    }

    /**
     * Decrypt and restore private keys into an isolated target directory.
     */
    public function restoreKeys(string $encryptedKeyPath, string $targetDirectory, string $custodySecret): array
    {
        $tempZip = storage_path('app/pki/temp_dec_keys_' . bin2hex(random_bytes(6)) . '.zip');
        try {
            $this->encryptionService->decryptFile($encryptedKeyPath, $tempZip, $custodySecret);

            $zip = new ZipArchive();
            if ($zip->open($tempZip) !== true) {
                throw new RuntimeException("Failed to open decrypted key archive.");
            }

            if (!File::isDirectory($targetDirectory)) {
                File::makeDirectory($targetDirectory, 0700, true);
            }

            $zip->extractTo($targetDirectory);
            $extractedCount = $zip->numFiles;
            $zip->close();

            // Set strict 0600 permissions on restored private keys
            if (PHP_OS_FAMILY !== 'Windows') {
                foreach (File::allFiles($targetDirectory) as $f) {
                    chmod($f->getRealPath(), 0600);
                }
            }

            return [
                'target_directory' => $targetDirectory,
                'restored_keys_count' => $extractedCount,
                'is_verified' => true,
            ];
        } finally {
            if (File::exists($tempZip)) {
                File::delete($tempZip);
            }
        }
    }
}
