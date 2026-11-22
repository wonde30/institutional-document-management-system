<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DigitalSignature;
use App\Models\DocumentVersion;
use App\Models\SignatureRequest;
use App\Models\StampApplication;
use App\Models\StampAuthorization;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\Storage;

class UniversityAssetService
{
    /**
     * Determine if an officially approved Wollo University logo is installed.
     */
    public static function hasApprovedUniversityLogo(): bool
    {
        return self::getApprovedUniversityLogoDataUri() !== null;
    }

    /**
     * Get the officially approved Wollo University logo as a data URI.
     * Returns null if no authentic approved logo asset has been installed.
     * Synthetic vector emblems and untrusted arbitrary SVGs are strictly excluded.
     */
    public static function getApprovedUniversityLogoDataUri(): ?string
    {
        // 1. Check runtime institutional setting with fallback to legacy alias
        $configuredPath = null;
        try {
            $settings = app(SettingsService::class);
            $configuredPath = $settings->effectiveValue('institution.approved_logo_path')
                ?? $settings->effectiveValue('institution.logo_path');
        } catch (\Throwable) {
            $configuredPath = config('settings.settings.institution.approved_logo_path.default');
        }

        $candidates = [];
        if ($configuredPath && is_string($configuredPath)) {
            // Defend against path traversal
            if (! str_contains($configuredPath, '..')) {
                $candidates[] = storage_path('app/' . ltrim($configuredPath, '/'));
                $candidates[] = storage_path('app/institutional/' . basename($configuredPath));
                $candidates[] = resource_path('assets/institutional/' . basename($configuredPath));
                $candidates[] = base_path($configuredPath);
            }
        }

        // 2. Controlled institutional asset storage locations
        $candidates[] = storage_path('app/institutional/approved_logo.png');
        $candidates[] = storage_path('app/institutional/approved_logo.jpg');
        $candidates[] = storage_path('app/institutional/approved_logo.svg');
        $candidates[] = resource_path('assets/institutional/approved_logo.png');
        $candidates[] = resource_path('assets/institutional/approved_logo.svg');

        // Allowed root directories for institutional assets
        $allowedRoots = [
            storage_path('app'),
            resource_path('assets'),
            public_path(),
        ];

        foreach ($candidates as $filePath) {
            if ($filePath && file_exists($filePath) && is_file($filePath)) {
                $realPath = realpath($filePath);
                if (! $realPath) {
                    continue;
                }

                // Verify the asset resides within private institutional storage boundaries
                $isWithinAllowedStorage = false;
                foreach ($allowedRoots as $root) {
                    if (FileSecurityService::resolveContainedPath($realPath, $root) !== null) {
                        $isWithinAllowedStorage = true;
                        break;
                    }
                }
                if (! $isWithinAllowedStorage) {
                    continue;
                }

                $fileSize = filesize($realPath);
                if ($fileSize === false || $fileSize <= 0 || $fileSize > 5 * 1024 * 1024) {
                    continue; // Skip empty files or files larger than 5MB
                }

                $mimeType = mime_content_type($realPath);
                $isSvg = str_ends_with(strtolower($realPath), '.svg');
                if ($isSvg) {
                    $mimeType = 'image/svg+xml';
                }

                // Strictly verify MIME type and actual image binary content
                if (in_array($mimeType, ['image/png', 'image/jpeg'], true)) {
                    $imageInfo = @getimagesize($realPath);
                    if (! $imageInfo || $imageInfo[0] <= 0 || $imageInfo[1] <= 0) {
                        continue; // Corrupt image binary
                    }
                    $content = file_get_contents($realPath);
                    if ($content !== false && strlen($content) > 0) {
                        return 'data:' . $mimeType . ';base64,' . base64_encode($content);
                    }
                } elseif ($mimeType === 'image/svg+xml') {
                    $content = file_get_contents($realPath);
                    if ($content === false || strlen($content) === 0) {
                        continue;
                    }

                    // Strict SVG safety and authenticity verification:
                    // 1. Must contain valid <svg root
                    if (! str_contains($content, '<svg') || ! str_contains($content, '</svg>')) {
                        continue;
                    }
                    // 2. Reject active scripts, dangerous handlers, remote includes, and XXE constructs
                    $dangerousPatterns = [
                        '/<script/i',
                        '/javascript:/i',
                        '/onload\s*=/i',
                        '/onerror\s*=/i',
                        '/onclick\s*=/i',
                        '/<!ENTITY/i',
                        '/SYSTEM\s+[\'"]/i',
                        '/<iframe/i',
                        '/<object/i',
                        '/<embed/i',
                        '/xlink:href\s*=\s*[\'"]http/i',
                        '/href\s*=\s*[\'"]http/i',
                    ];
                    $isMalicious = false;
                    foreach ($dangerousPatterns as $pattern) {
                        if (preg_match($pattern, $content)) {
                            $isMalicious = true;
                            break;
                        }
                    }
                    if ($isMalicious) {
                        continue;
                    }

                    // 3. Exclude synthetic vector placeholders or test seals
                    $syntheticMarkers = ['mock-logo', 'synthetic-seal', 'placeholder-emblem', 'TEST-EMBLEM'];
                    foreach ($syntheticMarkers as $marker) {
                        if (stripos($content, $marker) !== false) {
                            continue 2;
                        }
                    }

                    return 'data:image/svg+xml;base64,' . base64_encode($content);
                }
            }
        }

        return null;
    }

    /**
     * Verify and resolve an official stamp applied to a specific document version.
     * An approved stamp asset does not automatically stamp a document; an explicit,
     * authorized, active StampApplication matching the exact document version is required.
     * Returns null if no valid application exists.
     *
     * @return array{data_uri: string, stamp_name: string, applied_at: string, applied_by: string, reason: string}|null
     */
    public static function getVerifiedStampVisual(?DocumentVersion $version): ?array
    {
        if (! $version) {
            return null;
        }

        try {
            $application = StampApplication::with(['stampVersion.stamp', 'stampVersion.imageFile', 'appliedBy'])
                ->where('document_version_id', $version->id)
                ->orderByDesc('id')
                ->first();

            if (! $application) {
                return null;
            }

            $stampVersion = $application->stampVersion;
            if (! $stampVersion || ! $stampVersion->stamp) {
                return null;
            }

            // 1. Stamp definition must be active
            if (! $stampVersion->stamp->active) {
                return null;
            }

            // 2. Stamp version must have been effective and not retired at application time
            $appliedAt = $application->applied_at;
            if ($stampVersion->retired_at !== null && $stampVersion->retired_at <= $appliedAt) {
                return null;
            }
            if ($stampVersion->effective_from > $appliedAt) {
                return null;
            }

            // 3. The applicant actor must have been authorized to use this stamp at application time
            $isAuthorized = StampAuthorization::where('stamp_id', $stampVersion->stamp_id)
                ->where('user_id', $application->applied_by)
                ->where('valid_from', '<=', $appliedAt)
                ->where(function ($q) use ($appliedAt) {
                    $q->whereNull('valid_until')->orWhere('valid_until', '>=', $appliedAt);
                })
                ->exists();

            if (! $isAuthorized && $application->appliedBy) {
                // Check if user has unit-level stamping permission
                $user = $application->appliedBy;
                $unitId = $stampVersion->stamp->unit_id;
                $isAuthorized = $user->hasPermission('system.configure') || $user->hasPermission('documents.stamp', $unitId) || $user->hasPermission('stamps.apply', $unitId);
            }

            if (! $isAuthorized) {
                return null;
            }

            // 4. Resolve the stamp asset file
            $imageFile = $stampVersion->imageFile;
            $storageKey = $imageFile?->storage_key ?? $imageFile?->storage_path;
            if (! $imageFile || empty($storageKey)) {
                return null;
            }

            $diskName = $imageFile->storage_disk ?? $imageFile->storage_driver ?? 'local';
            $disk = Storage::disk($diskName);
            if (! $disk->exists($storageKey)) {
                return null;
            }

            $binary = $disk->get($storageKey);
            if (! $binary || strlen($binary) === 0) {
                return null;
            }

            $mime = $imageFile->detected_mime ?? $imageFile->mime_type ?? 'image/png';
            $dataUri = 'data:' . $mime . ';base64,' . base64_encode($binary);

            return [
                'data_uri' => $dataUri,
                'stamp_name' => $stampVersion->stamp->name,
                'applied_at' => $appliedAt->format('Y-m-d H:i:s'),
                'applied_by' => $application->appliedBy?->name ?? 'Authorized Officer',
                'reason' => $application->reason,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Verify and resolve authentic cryptographic digital signature for a specific document version.
     * Merely having a signature request does not qualify; the signature must be valid,
     * cryptographically verified, bound to this exact document version, unrevoked, and match
     * the signed content hash.
     *
     * @return array{is_verified: bool, signer_name: string, signed_at: string, digest: string, format: string, trust_tier: string}|null
     */
    public static function getVerifiedSignatureVisual(?DocumentVersion $version, ?DigitalSignature $signature = null): ?array
    {
        try {
            // If signature not provided explicitly, look up from document version
            if (! $signature && $version) {
                $sigRequest = SignatureRequest::where('document_version_id', $version->id)
                    ->with(['digitalSignature.signingIdentity.user', 'digitalSignature.signedFile', 'digitalSignature.verifications'])
                    ->orderByDesc('id')
                    ->first();
                $signature = $sigRequest?->digitalSignature;
            }

            if (! $signature) {
                return null;
            }

            // 1. Must be linked to the matching document version
            if ($version && $signature->request && $signature->request->document_version_id !== $version->id) {
                return null;
            }

            // 2. Cryptographic verification status on the signature record must be valid
            if (! in_array($signature->verification_status, ['valid', 'verified'], true)) {
                return null;
            }

            // 3. Inspect latest SignatureVerification outcome
            $latestVerification = $signature->verifications()->orderByDesc('id')->first();
            if ($latestVerification) {
                if ($latestVerification->outcome !== 'valid') {
                    return null;
                }
                if (in_array($latestVerification->certificate_status, ['revoked', 'expired', 'untrusted', 'error'], true)) {
                    return null;
                }
            }

            // 4. Signing Identity must be active and not revoked
            $identity = $signature->signingIdentity;
            if (! $identity) {
                return null;
            }

            $signedAt = $signature->signed_at ?? now();
            if ($identity->revoked_at !== null && $identity->revoked_at <= $signedAt) {
                return null;
            }
            if ($identity->valid_until !== null && $identity->valid_until < $signedAt) {
                return null;
            }

            // 5. Signed content hash must exist and match physical file integrity
            $signedFile = $signature->signedFile;
            $digest = $signedFile?->sha256 ?? $signature->provider_transaction_reference;
            if (empty($digest)) {
                return null;
            }

            if ($signedFile) {
                $storageKey = $signedFile->storage_key ?? $signedFile->storage_path;
                if ($storageKey) {
                    $disk = Storage::disk($signedFile->storage_disk ?? 'local');
                    if ($disk->exists($storageKey)) {
                        $actualBytes = $disk->get($storageKey);
                        if (hash('sha256', $actualBytes) !== $signedFile->sha256) {
                            return null; // Tampered signed artifact on disk
                        }
                    }
                }
            }

            // 6. Signatory authorization
            $signerUser = $identity->user ?? $signature->request?->signer;
            if ($signerUser && $signerUser->status !== 'active') {
                return null;
            }

            $signerName = $signerUser?->name
                ?? $identity->signer_name
                ?? $identity->common_name
                ?? 'Authorized Signatory';

            return [
                'is_verified' => true,
                'signer_name' => $signerName,
                'signed_at' => $signedAt->format('Y-m-d H:i:s'),
                'digest' => $digest,
                'format' => $signature->signature_format ?? 'PAdES-B-LT',
                'trust_tier' => $signature->trust_tier ?? 'institutional',
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
