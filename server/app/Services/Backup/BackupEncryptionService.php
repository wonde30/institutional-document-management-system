<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\File;
use RuntimeException;

class BackupEncryptionService
{
    protected const MAGIC_HEADER = 'WUDMS_ENC_V1';
    protected const PBKDF2_ROUNDS = 100000;

    /**
     * Get or derive backup master encryption secret.
     * Uses DMS_BACKUP_ENCRYPTION_KEY if set, otherwise falls back to APP_KEY.
     */
    protected function getEncryptionSecret(): string
    {
        $secret = config('services.backup.encryption_key') ?: config('app.key');
        if (empty($secret)) {
            throw new RuntimeException("No encryption secret configured. Set DMS_BACKUP_ENCRYPTION_KEY or APP_KEY.");
        }
        if (str_starts_with($secret, 'base64:')) {
            $secret = base64_decode(substr($secret, 7));
        }
        return $secret;
    }

    /**
     * Encrypt an arbitrary backup artifact (SQL or ZIP) with AES-256-CBC and HMAC-SHA256 authentication.
     */
    public function encryptFile(string $inputFilePath, string $outputEncryptedPath, ?string $customSecret = null): array
    {
        $startTime = microtime(true);
        if (!File::exists($inputFilePath)) {
            throw new RuntimeException("Source file for encryption does not exist: {$inputFilePath}");
        }

        $secret = $customSecret ?? $this->getEncryptionSecret();
        $salt = random_bytes(16);
        $iv = random_bytes(16);

        // Derive 64 bytes: 32 bytes for AES encryption key, 32 bytes for HMAC authentication key
        $derivedKey = hash_pbkdf2('sha256', $secret, $salt, self::PBKDF2_ROUNDS, 64, true);
        $encKey = substr($derivedKey, 0, 32);
        $hmacKey = substr($derivedKey, 32, 32);

        $plaintext = file_get_contents($inputFilePath);
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-cbc', $encKey, OPENSSL_RAW_DATA, $iv);

        if ($ciphertext === false) {
            throw new RuntimeException("AES-256 encryption failed: " . openssl_error_string());
        }

        // Encrypt-then-MAC
        $payloadToAuthenticate = self::MAGIC_HEADER . $salt . $iv . $ciphertext;
        $hmac = hash_hmac('sha256', $payloadToAuthenticate, $hmacKey, true);

        // Binary container format: [MAGIC (12b)] [SALT (16b)] [IV (16b)] [HMAC (32b)] [CIPHERTEXT]
        $container = self::MAGIC_HEADER . $salt . $iv . $hmac . $ciphertext;

        $outputDir = dirname($outputEncryptedPath);
        if (!File::isDirectory($outputDir)) {
            File::makeDirectory($outputDir, 0750, true);
        }

        File::put($outputEncryptedPath, $container);
        if (PHP_OS_FAMILY !== 'Windows') {
            chmod($outputEncryptedPath, 0600);
        }

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);
        $encSha256 = hash_file('sha256', $outputEncryptedPath);

        return [
            'original_path' => $inputFilePath,
            'encrypted_path' => $outputEncryptedPath,
            'original_bytes' => strlen($plaintext),
            'encrypted_bytes' => strlen($container),
            'sha256' => $encSha256,
            'cipher' => 'aes-256-cbc',
            'auth' => 'hmac-sha256',
            'pbkdf2_rounds' => self::PBKDF2_ROUNDS,
            'duration_ms' => $durationMs,
        ];
    }

    /**
     * Decrypt and authenticate an encrypted backup artifact.
     * Fails closed if HMAC authentication fails (tampering detection).
     */
    public function decryptFile(string $encryptedFilePath, string $outputDecryptedPath, ?string $customSecret = null): array
    {
        $startTime = microtime(true);
        if (!File::exists($encryptedFilePath)) {
            throw new RuntimeException("Encrypted backup file not found: {$encryptedFilePath}");
        }

        $fileHandle = fopen($encryptedFilePath, 'rb');
        if (!$fileHandle) {
            throw new RuntimeException("Cannot open encrypted backup file: {$encryptedFilePath}");
        }

        $headerLen = strlen(self::MAGIC_HEADER);
        $magic = fread($fileHandle, $headerLen);
        if ($magic !== self::MAGIC_HEADER) {
            fclose($fileHandle);
            throw new RuntimeException("Invalid backup container format: Magic header mismatch in {$encryptedFilePath}");
        }

        $salt = fread($fileHandle, 16);
        $iv = fread($fileHandle, 16);
        $expectedHmac = fread($fileHandle, 32);
        $ciphertext = stream_get_contents($fileHandle);
        fclose($fileHandle);

        $secret = $customSecret ?? $this->getEncryptionSecret();
        $derivedKey = hash_pbkdf2('sha256', $secret, $salt, self::PBKDF2_ROUNDS, 64, true);
        $encKey = substr($derivedKey, 0, 32);
        $hmacKey = substr($derivedKey, 32, 32);

        // Authenticate HMAC before decryption
        $payloadToAuthenticate = self::MAGIC_HEADER . $salt . $iv . $ciphertext;
        $calculatedHmac = hash_hmac('sha256', $payloadToAuthenticate, $hmacKey, true);

        if (!hash_equals($expectedHmac, $calculatedHmac)) {
            throw new RuntimeException(
                "Backup authentication failure: HMAC mismatch detected. " .
                "The backup archive has been tampered with, corrupted, or an incorrect secret key was provided."
            );
        }

        $plaintext = openssl_decrypt($ciphertext, 'aes-256-cbc', $encKey, OPENSSL_RAW_DATA, $iv);
        if ($plaintext === false) {
            throw new RuntimeException("AES-256 decryption failed: " . openssl_error_string());
        }

        $outputDir = dirname($outputDecryptedPath);
        if (!File::isDirectory($outputDir)) {
            File::makeDirectory($outputDir, 0750, true);
        }

        File::put($outputDecryptedPath, $plaintext);
        if (PHP_OS_FAMILY !== 'Windows') {
            chmod($outputDecryptedPath, 0600);
        }

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);
        $decSha256 = hash_file('sha256', $outputDecryptedPath);

        return [
            'encrypted_path' => $encryptedFilePath,
            'decrypted_path' => $outputDecryptedPath,
            'decrypted_bytes' => strlen($plaintext),
            'sha256' => $decSha256,
            'duration_ms' => $durationMs,
            'is_authenticated' => true,
        ];
    }
}
