<?php

declare(strict_types=1);

namespace App\Services\Pki;

use App\Models\FileObject;
use App\Models\SigningIdentity;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class KeyCustodyService
{
    private string $keysDirectory;
    private CertificateService $certService;

    public function __construct(CertificateService $certService)
    {
        $this->certService = $certService;
        $this->keysDirectory = storage_path('app/pki/keys');

        if (! File::isDirectory($this->keysDirectory)) {
            File::makeDirectory($this->keysDirectory, 0700, true, true);
        }
    }

    /**
     * Resolve or provision a signing key and identity for the given user.
     * Enforces that the private key is held exclusively in secure server-side custody
     * and NEVER stored in the database.
     */
    public function getOrCreateIdentity(User $user): SigningIdentity
    {
        $existing = SigningIdentity::where('user_id', $user->id)
            ->where(function ($q) {
                $q->whereNull('revoked_at')->orWhere('revoked_at', '>', now());
            })
            ->first();

        $keyPath = $this->getUserKeyPath($user->id);

        if ($existing && File::exists($keyPath) && $existing->public_certificate_file_id) {
            return $existing;
        }

        // Provision new signing keypair and X.509 certificate
        $commonName = $user->name ?: "Wollo University Signer {$user->id}";
        $email = $user->email ?: "user_{$user->id}@wu.edu.et";

        $certResult = $this->certService->generateUserSigningCertificate(
            $user->id,
            $commonName,
            $email,
            'Institutional Faculty & Staff'
        );

        // Store private key securely on filesystem (strictly mode 0600)
        File::put($keyPath, $certResult['private_key_pem']);
        @chmod($keyPath, 0600);

        // Store public certificate in FileObject repository
        $now = now()->format('Y-m-d H:i:s.u');
        $certRelativeKey = 'pki/certs/cert_user_' . $user->id . '_' . Str::random(8) . '.pem';
        $certStoragePath = storage_path('app/' . $certRelativeKey);
        File::ensureDirectoryExists(dirname($certStoragePath));
        File::put($certStoragePath, $certResult['certificate_pem']);

        $certFile = FileObject::create([
            'storage_disk' => 'local',
            'storage_key' => $certRelativeKey,
            'original_filename' => "x509_certificate_user_{$user->id}.pem",
            'detected_mime' => 'application/x-x509-ca-cert',
            'byte_size' => strlen($certResult['certificate_pem']),
            'sha256' => hash('sha256', $certResult['certificate_pem']),
            'uploaded_by' => $user->id,
            'uploaded_at' => $now,
            'scan_status' => 'clean',
            'storage_status' => 'ready',
        ]);

        $parsed = $certResult['parsed'];

        if ($existing) {
            $existing->update([
                'public_certificate_file_id' => $certFile->id,
                'valid_from' => $parsed['valid_from'],
                'valid_until' => $parsed['valid_to'],
                'revoked_at' => null,
            ]);
            return $existing;
        }

        return SigningIdentity::create([
            'user_id' => $user->id,
            'provider_code' => 'wollo_institutional_pki',
            'provider_identity_reference' => 'WU-PKI-USER-' . $user->id . '-' . Str::random(8),
            'public_certificate_file_id' => $certFile->id,
            'valid_from' => $parsed['valid_from'],
            'valid_until' => $parsed['valid_to'],
            'revoked_at' => null,
        ]);
    }

    /**
     * Sign data using the user's private key within secure memory.
     * Enforces strict actor ownership: only the user who owns the key can sign.
     */
    public function signData(User $actor, string $data): string
    {
        $keyPath = $this->getUserKeyPath($actor->id);
        if (! File::exists($keyPath)) {
            $this->getOrCreateIdentity($actor);
        }

        $privateKeyPem = File::get($keyPath);
        $privateKey = openssl_pkey_get_private($privateKeyPem);

        if (! $privateKey) {
            throw new RuntimeException('Unable to load private key from secure custody.');
        }

        $signature = '';
        $success = openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (! $success) {
            throw new RuntimeException('Cryptographic signing failed: ' . openssl_error_string());
        }

        return $signature;
    }

    /**
     * Get user certificate PEM from associated FileObject or filesystem.
     */
    public function getUserCertificatePem(int $userId): string
    {
        $certPath = storage_path("app/pki/certs/user_{$userId}_cert.pem");
        if (File::exists($certPath)) {
            return File::get($certPath);
        }

        $identity = SigningIdentity::with('publicCertificateFile')
            ->where('user_id', $userId)
            ->first();

        if ($identity && $identity->publicCertificateFile) {
            $filePath = storage_path('app/' . $identity->publicCertificateFile->storage_key);
            if (File::exists($filePath)) {
                return File::get($filePath);
            }
        }

        throw new RuntimeException("Public X.509 certificate for user {$userId} not found.");
    }

    /**
     * Revoke a signing key identity.
     */
    public function revokeIdentity(int $userId, string $reason = 'Administrative revocation'): void
    {
        $identity = SigningIdentity::where('user_id', $userId)
            ->whereNull('revoked_at')
            ->first();

        if ($identity) {
            $identity->update(['revoked_at' => now()->format('Y-m-d H:i:s.u')]);
        }
    }

    private function getUserKeyPath(int $userId): string
    {
        return $this->keysDirectory . "/user_{$userId}_key.pem";
    }
}
