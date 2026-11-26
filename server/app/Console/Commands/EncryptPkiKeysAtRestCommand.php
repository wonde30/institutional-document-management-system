<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Pki\CertificateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Throwable;

class EncryptPkiKeysAtRestCommand extends Command
{
    protected $signature = 'pki:encrypt-keys-at-rest {--dry-run : Report plaintext keys without modifying files}';

    protected $description = 'Safely migrate plaintext PKI private keys (CA and user keys) to encrypted-at-rest storage with backup verification.';

    public function handle(CertificateService $certService): int
    {
        $pkiBase = $certService->getPkiBasePath();
        $caKey = $pkiBase . '/ca/institutional_ca.key';
        $userKeys = File::glob($pkiBase . '/keys/user_*_key.pem') ?: [];

        $candidates = [];
        if (File::exists($caKey)) {
            $candidates[] = $caKey;
        }
        foreach ($userKeys as $path) {
            $candidates[] = $path;
        }

        if (empty($candidates)) {
            $this->info('No PKI private key files found.');
            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $backupBase = (string) (config('services.pki.backup_path') ?: storage_path('app/pki_backups'));
        $backupDir = $backupBase . DIRECTORY_SEPARATOR . now()->format('Ymd_His');
        $migrated = 0;
        $alreadyEncrypted = 0;
        $failed = 0;

        foreach ($candidates as $path) {
            $content = File::get($path);
            $trimmed = trim($content);

            if (! str_starts_with($trimmed, '-----BEGIN')) {
                // Verify existing encrypted payload is decryptable
                try {
                    $certService->decryptPrivateKeyPem($content);
                    $alreadyEncrypted++;
                } catch (Throwable $e) {
                    $this->error("Failed to verify existing encrypted key {$path}: {$e->getMessage()}");
                    $failed++;
                }
                continue;
            }

            if ($dryRun) {
                $this->line("[DRY-RUN] Would encrypt plaintext key: {$path}");
                $migrated++;
                continue;
            }

            try {
                // 1. Validate original private key & extract public key fingerprint before migration
                $origPriv = openssl_pkey_get_private($content);
                if (! $origPriv) {
                    throw new \RuntimeException('Original PEM key is unreadable by OpenSSL.');
                }
                $origDetails = openssl_pkey_get_details($origPriv);
                $origPubKey = $origDetails['key'] ?? null;

                // 2. Create verified backup before modifying original
                File::ensureDirectoryExists($backupDir, 0700, true);
                $relPath = ltrim(str_replace($pkiBase, '', $path), '/\\');
                $backupPath = $backupDir . DIRECTORY_SEPARATOR . $relPath;
                File::ensureDirectoryExists(dirname($backupPath), 0700, true);
                File::put($backupPath, $content);
                @chmod($backupPath, 0600);

                if (hash_file('sha256', $backupPath) !== hash('sha256', $content)) {
                    throw new \RuntimeException('Backup SHA-256 verification failed.');
                }

                // 3. Encrypt and write atomically
                $encrypted = Crypt::encryptString($content);
                $decrypted = $certService->decryptPrivateKeyPem($encrypted);
                $decPriv = openssl_pkey_get_private($decrypted);
                $decDetails = $decPriv ? openssl_pkey_get_details($decPriv) : null;

                if (! $decDetails || ($decDetails['key'] ?? null) !== $origPubKey) {
                    throw new \RuntimeException('Post-encryption public key verification failed.');
                }

                File::put($path, $encrypted);
                @chmod($path, 0600);
                $migrated++;
            } catch (Throwable $e) {
                $this->error("Migration failed for {$path}: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("PKI key-at-rest migration summary: migrated={$migrated}, already_encrypted={$alreadyEncrypted}, failed={$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
