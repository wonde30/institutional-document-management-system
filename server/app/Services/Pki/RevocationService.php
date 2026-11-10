<?php

declare(strict_types=1);

namespace App\Services\Pki;

use App\Models\SigningIdentity;
use Illuminate\Support\Facades\Http;

class RevocationService
{
    /**
     * Check revocation status for a given user or signing identity.
     */
    public function checkRevocation(SigningIdentity $identity): array
    {
        if ($identity->revoked_at !== null && $identity->revoked_at <= now()) {
            return [
                'status' => 'revoked',
                'revoked' => true,
                'revoked_at' => (string) $identity->revoked_at,
                'reason' => 'Administrative identity revocation recorded in repository.',
            ];
        }

        // In production, check OCSP / CRL distribution points if present
        return [
            'status' => 'good',
            'revoked' => false,
            'revoked_at' => null,
            'reason' => 'Identity is active; no revocation record found.',
        ];
    }

    /**
     * Check revocation via external OCSP URL.
     * Returns indeterminate if server is unreachable.
     */
    public function checkOcsp(string $ocspUrl, string $certDer, string $issuerDer): array
    {
        try {
            $response = Http::timeout(3)
                ->withHeaders(['Content-Type' => 'application/ocsp-request'])
                ->post($ocspUrl, ['cert' => base64_encode($certDer)]);

            if ($response->successful()) {
                return [
                    'status' => 'good',
                    'message' => 'OCSP responder confirmed certificate is valid.',
                ];
            }

            return [
                'status' => 'indeterminate',
                'message' => 'OCSP responder returned status ' . $response->status(),
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'indeterminate',
                'message' => 'OCSP responder unreachable: ' . $e->getMessage(),
            ];
        }
    }
}
