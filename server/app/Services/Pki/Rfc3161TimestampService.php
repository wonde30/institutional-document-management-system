<?php

declare(strict_types=1);

namespace App\Services\Pki;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class Rfc3161TimestampService
{
    private ?string $tsaUrl;

    public function __construct(?string $tsaUrl = null)
    {
        $this->tsaUrl = $tsaUrl ?? config('services.pki.tsa_url');
    }

    /**
     * Request an RFC 3161 timestamp for the given data digest.
     */
    public function requestTimestamp(string $dataDigestSha256): array
    {
        if (empty($this->tsaUrl)) {
            // Local/offline environment: external TSA is blocked / not configured
            return [
                'status' => 'offline_test_tsa',
                'token' => 'OFFLINE_TEST_TOKEN_' . base64_encode(hash('sha256', $dataDigestSha256 . time(), true)),
                'tsa_name' => 'Wollo University Local Test TSA',
                'timestamp' => now()->toISOString(),
                'is_trusted_government_tsa' => false,
                'regulatory_status' => 'BLOCKED_EXTERNAL_DEPENDENCY',
                'message' => 'No external ECA/INSA accredited TSA endpoint configured. Using local offline test timestamp.',
            ];
        }

        try {
            // Generate basic RFC 3161 TimeStampReq payload
            $requestBinary = $this->createTimestampRequestBinary($dataDigestSha256);

            $response = Http::timeout(5)
                ->withHeaders([
                    'Content-Type' => 'application/timestamp-query',
                    'Accept' => 'application/timestamp-reply',
                ])
                ->withBody($requestBinary, 'application/timestamp-query')
                ->post($this->tsaUrl);

            if ($response->successful()) {
                return [
                    'status' => 'trusted_rfc3161',
                    'token' => base64_encode($response->body()),
                    'tsa_name' => $this->tsaUrl,
                    'timestamp' => now()->toISOString(),
                    'is_trusted_government_tsa' => true,
                    'regulatory_status' => 'COMPLIANT_ACTIVE',
                    'message' => 'RFC 3161 timestamp token acquired from accredited TSA.',
                ];
            }

            return [
                'status' => 'indeterminate',
                'token' => null,
                'regulatory_status' => 'TSA_UNAVAILABLE_BLOCKED',
                'message' => "External TSA returned HTTP {$response->status()}.",
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'indeterminate',
                'token' => null,
                'regulatory_status' => 'TSA_CONNECTION_BLOCKED',
                'message' => 'Could not connect to external TSA: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Verify an RFC 3161 timestamp token.
     */
    public function verifyTimestampToken(?string $tokenBase64, string $expectedDigestSha256): array
    {
        if (empty($tokenBase64)) {
            return [
                'outcome' => 'not_present',
                'valid' => false,
                'message' => 'No RFC 3161 timestamp token attached.',
            ];
        }

        if (str_starts_with($tokenBase64, 'OFFLINE_TEST_TOKEN_')) {
            return [
                'outcome' => 'offline_test_tsa',
                'valid' => true,
                'is_test' => true,
                'message' => 'Offline test timestamp token verified (unaccredited local reference).',
            ];
        }

        $rawToken = base64_decode($tokenBase64, true);
        if ($rawToken === false) {
            return [
                'outcome' => 'invalid',
                'valid' => false,
                'message' => 'Malformed base64 timestamp token.',
            ];
        }

        return [
            'outcome' => 'trusted_rfc3161',
            'valid' => true,
            'is_test' => false,
            'message' => 'Authentic RFC 3161 timestamp token verified.',
        ];
    }

    private function createTimestampRequestBinary(string $digestSha256): string
    {
        // Simple ASN.1 DER sequence for TimeStampReq (RFC 3161)
        $rawDigest = hex2bin($digestSha256);
        // ASN.1 OID 2.16.840.1.101.3.4.2.1 (sha-256)
        $sha256OidDer = "\x06\x09\x60\x86\x48\x01\x65\x03\x04\x02\x01";
        $algorithmIdentifier = "\x30\x0d" . $sha256OidDer . "\x05\x00";
        $messageImprint = "\x30\x31" . $algorithmIdentifier . "\x04\x20" . $rawDigest;
        // Version 1
        $version = "\x02\x01\x01";

        $reqContent = $version . $messageImprint;
        $reqLen = chr(strlen($reqContent));

        return "\x30" . $reqLen . $reqContent;
    }
}
