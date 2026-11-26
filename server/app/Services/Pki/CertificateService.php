<?php

declare(strict_types=1);

namespace App\Services\Pki;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use RuntimeException;

class CertificateService
{
    private string $storageBasePath;
    private string $caCertPath;
    private string $caKeyPath;
    private string $cnfPath;

    public function __construct()
    {
        $this->storageBasePath = $this->getPkiBasePath();
        $this->caCertPath = $this->storageBasePath . '/ca/institutional_ca.crt';
        $this->caKeyPath = $this->storageBasePath . '/ca/institutional_ca.key';
        $this->cnfPath = $this->storageBasePath . '/openssl.cnf';

        $this->ensureDirectories();
        $this->ensureOpenSslConfig();
    }

    public function getPkiBasePath(): string
    {
        return (string) (config('services.pki.base_path') ?: storage_path('app/pki'));
    }

    private function ensureDirectories(): void
    {
        $dirs = [
            $this->storageBasePath,
            $this->storageBasePath . '/ca',
            $this->storageBasePath . '/keys',
            $this->storageBasePath . '/certs',
        ];

        foreach ($dirs as $dir) {
            if (! File::isDirectory($dir)) {
                File::makeDirectory($dir, 0700, true, true);
            }
        }
    }

    private function ensureOpenSslConfig(): void
    {
        if (! File::exists($this->cnfPath)) {
            $configContent = <<<'CONF'
[ req ]
default_bits        = 2048
default_keyfile     = privkey.pem
distinguished_name  = req_distinguished_name
attributes          = req_attributes
x509_extensions     = v3_ca
string_mask         = utf8only

[ req_distinguished_name ]
countryName                     = Country Name (2 letter code)
countryName_default             = ET
stateOrProvinceName             = State or Province Name (full name)
localityName                    = Locality Name (eg, city)
organizationName                = Organization Name (eg, company)
organizationalUnitName          = Organizational Unit Name (eg, section)
commonName                      = Common Name (e.g. server FQDN or YOUR name)
emailAddress                    = Email Address

[ req_attributes ]

[ v3_ca ]
subjectKeyIdentifier = hash
authorityKeyIdentifier = keyid:always,issuer
basicConstraints = critical, CA:true
keyUsage = critical, digitalSignature, cRLSign, keyCertSign

[ usr_cert ]
basicConstraints = CA:false
subjectKeyIdentifier = hash
authorityKeyIdentifier = keyid,issuer
keyUsage = critical, nonRepudiation, digitalSignature, keyEncipherment
extendedKeyUsage = emailProtection, codeSigning, 1.2.840.113583.1.1.5
CONF;
            File::put($this->cnfPath, $configContent);
        }

        putenv('OPENSSL_CONF=' . $this->cnfPath);
    }

    private function getOpenSslConfig(array $extra = []): array
    {
        return array_merge([
            'config' => $this->cnfPath,
        ], $extra);
    }

    /**
     * Get or generate the Wollo University Institutional Internal CA.
     * Note: This is an internal test/institutional CA, not an ECA/INSA accredited national root.
     */
    public function getOrGenerateInstitutionalCa(): array
    {
        if (File::exists($this->caCertPath) && File::exists($this->caKeyPath)) {
            $certPem = File::get($this->caCertPath);
            $rawKey = File::get($this->caKeyPath);
            $keyPem = $this->decryptPrivateKeyPem($rawKey);
            return [
                'cert_pem' => $certPem,
                'key_pem' => $keyPem,
                'parsed' => $this->parseCertificate($certPem),
            ];
        }

        // Generate Institutional CA Root Key & Self-Signed CA Cert
        $keyConfig = $this->getOpenSslConfig([
            'private_key_bits' => 4096,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $privKey = openssl_pkey_new($keyConfig);
        if (! $privKey) {
            throw new RuntimeException('Failed to generate Institutional CA private key: ' . openssl_error_string());
        }

        openssl_pkey_export($privKey, $keyPem, null, $this->getOpenSslConfig()); // $keyPem is plaintext in memory

        $dn = [
            'countryName' => 'ET',
            'stateOrProvinceName' => 'Amhara',
            'localityName' => 'Dessie',
            'organizationName' => 'Wollo University',
            'organizationalUnitName' => 'Institutional Trust & Records Authority',
            'commonName' => 'Wollo University Institutional Root CA (TEST / UNACCREDITED)',
            'emailAddress' => 'pki-admin@wu.edu.et',
        ];

        $csr = openssl_csr_new($dn, $privKey, $this->getOpenSslConfig(['digest_alg' => 'sha256']));
        if (! $csr) {
            throw new RuntimeException('Failed to generate CA CSR: ' . openssl_error_string());
        }

        // 10-year validity for Root CA
        $caCert = openssl_csr_sign($csr, null, $privKey, 3650, $this->getOpenSslConfig([
            'digest_alg' => 'sha256',
            'x509_extensions' => 'v3_ca',
        ]));

        if (! $caCert) {
            throw new RuntimeException('Failed to sign CA certificate: ' . openssl_error_string());
        }

        openssl_x509_export($caCert, $certPem);

        File::put($this->caKeyPath, Crypt::encryptString($keyPem));
        File::put($this->caCertPath, $certPem);

        return [
            'cert_pem' => $certPem,
            'key_pem' => $keyPem,
            'parsed' => $this->parseCertificate($certPem),
        ];
    }

    /**
     * Generate an authorized user signing keypair and X.509 certificate.
     */
    public function generateUserSigningCertificate(
        int $userId,
        string $commonName,
        string $email,
        string $organizationalUnit = 'Academic & Administrative Staff',
        int $validDays = 730
    ): array {
        $ca = $this->getOrGenerateInstitutionalCa();

        $keyConfig = $this->getOpenSslConfig([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $userPrivKey = openssl_pkey_new($keyConfig);
        if (! $userPrivKey) {
            throw new RuntimeException('Failed to generate user private key: ' . openssl_error_string());
        }

        openssl_pkey_export($userPrivKey, $userKeyPem, null, $this->getOpenSslConfig()); // $userKeyPem is plaintext in memory; encryption happens in KeyCustodyService

        $dn = [
            'countryName' => 'ET',
            'stateOrProvinceName' => 'Amhara',
            'localityName' => 'Dessie',
            'organizationName' => 'Wollo University',
            'organizationalUnitName' => $organizationalUnit,
            'commonName' => $commonName,
            'emailAddress' => $email,
        ];

        $csr = openssl_csr_new($dn, $userPrivKey, $this->getOpenSslConfig(['digest_alg' => 'sha256']));
        if (! $csr) {
            throw new RuntimeException('Failed to generate user CSR: ' . openssl_error_string());
        }

        // Sign user cert with Institutional CA
        $caCert = openssl_x509_read($ca['cert_pem']);
        $caKey = openssl_pkey_get_private($ca['key_pem']);

        $serial = time() + ($userId * 100000);
        $userCert = openssl_csr_sign($csr, $caCert, $caKey, $validDays, $this->getOpenSslConfig([
            'digest_alg' => 'sha256',
            'x509_extensions' => 'usr_cert',
        ]), $serial);

        if (! $userCert) {
            throw new RuntimeException('Failed to issue user signing certificate: ' . openssl_error_string());
        }

        openssl_x509_export($userCert, $userCertPem);

        // Store user certificate on disk
        $certFilename = "user_{$userId}_cert.pem";
        $certPath = $this->storageBasePath . '/certs/' . $certFilename;
        File::put($certPath, $userCertPem);

        // Note: The private key is managed strictly through KeyCustodyService
        return [
            'certificate_pem' => $userCertPem,
            'private_key_pem' => $userKeyPem,
            'certificate_path' => $certPath,
            'parsed' => $this->parseCertificate($userCertPem),
        ];
    }

    /**
     * Parse X.509 PEM certificate and extract cryptographic attributes.
     */
    public function parseCertificate(string $certPem): array
    {
        $x509 = openssl_x509_read($certPem);
        if (! $x509) {
            throw new RuntimeException('Invalid X.509 certificate format.');
        }

        $parsed = openssl_x509_parse($x509);
        if (! $parsed) {
            throw new RuntimeException('Unable to parse X.509 certificate data.');
        }

        $validFrom = isset($parsed['validFrom_time_t'])
            ? date('Y-m-d H:i:s', $parsed['validFrom_time_t'])
            : null;
        $validTo = isset($parsed['validTo_time_t'])
            ? date('Y-m-d H:i:s', $parsed['validTo_time_t'])
            : null;

        $now = now()->timestamp;
        $isExpired = isset($parsed['validTo_time_t']) && $now > $parsed['validTo_time_t'];
        $isNotYetValid = isset($parsed['validFrom_time_t']) && $now < $parsed['validFrom_time_t'];

        $subjectDn = $this->formatDn($parsed['subject'] ?? []);
        $issuerDn = $this->formatDn($parsed['issuer'] ?? []);
        $isSelfSigned = ($subjectDn === $issuerDn);

        // Compute fingerprint (SHA-256)
        openssl_x509_export($x509, $cleanPem);
        $der = $this->pemToDer($cleanPem);
        $fingerprintSha256 = hash('sha256', $der);

        return [
            'subject_dn' => $subjectDn,
            'issuer_dn' => $issuerDn,
            'common_name' => $parsed['subject']['CN'] ?? null,
            'organization' => $parsed['subject']['O'] ?? null,
            'organizational_unit' => $parsed['subject']['OU'] ?? null,
            'country' => $parsed['subject']['C'] ?? null,
            'email' => $parsed['subject']['emailAddress'] ?? null,
            'serial_number' => $parsed['serialNumber'] ?? ($parsed['serialNumberHex'] ?? null),
            'valid_from' => $validFrom,
            'valid_to' => $validTo,
            'valid_from_timestamp' => $parsed['validFrom_time_t'] ?? null,
            'valid_to_timestamp' => $parsed['validTo_time_t'] ?? null,
            'is_expired' => $isExpired,
            'is_not_yet_valid' => $isNotYetValid,
            'is_self_signed' => $isSelfSigned,
            'fingerprint_sha256' => $fingerprintSha256,
            'signature_type_sn' => $parsed['signatureTypeSN'] ?? 'RSA-SHA256',
            'is_test_certificate' => true,
            'regulatory_classification' => 'TEST ONLY - UNACCREDITED INSTITUTIONAL PKI (NOT GOVERNMENT ACCREDITED)',
        ];
    }

    /**
     * Validate an X.509 certificate against dates and optionally the CA certificate.
     */
    public function validateCertificate(string $certPem, ?string $caCertPem = null): array
    {
        try {
            $parsed = $this->parseCertificate($certPem);
        } catch (\Throwable $e) {
            return [
                'valid' => false,
                'status' => 'error',
                'message' => 'Malformed certificate: ' . $e->getMessage(),
            ];
        }

        if ($parsed['is_expired']) {
            return [
                'valid' => false,
                'status' => 'expired',
                'message' => "Certificate expired on {$parsed['valid_to']}.",
                'details' => $parsed,
            ];
        }

        if ($parsed['is_not_yet_valid']) {
            return [
                'valid' => false,
                'status' => 'not_yet_valid',
                'message' => "Certificate is not valid until {$parsed['valid_from']}.",
                'details' => $parsed,
            ];
        }

        // Verify chain if CA cert is provided
        if ($caCertPem) {
            $tempCert = tempnam(sys_get_temp_dir(), 'wu_vcert_');
            $tempCa = tempnam(sys_get_temp_dir(), 'wu_vca_');
            File::put($tempCert, $certPem);
            File::put($tempCa, $caCertPem);

            // openssl_x509_checkpurpose with CA verification
            $chainValid = openssl_x509_checkpurpose($certPem, X509_PURPOSE_ANY, [$tempCa]);

            @unlink($tempCert);
            @unlink($tempCa);

            if (! $chainValid) {
                return [
                    'valid' => false,
                    'status' => 'chain_verification_failed',
                    'message' => 'Certificate signature chain could not be verified against the CA trust anchor.',
                    'details' => $parsed,
                ];
            }
        }

        return [
            'valid' => true,
            'status' => 'test_valid_unaccredited',
            'message' => 'Certificate is cryptographically valid (Institutional Test PKI).',
            'details' => $parsed,
        ];
    }

    /**
     * Decrypt an encrypted private key payload.
     * Runtime operations strictly reject plaintext PEM keys; legacy plaintext keys
     * must be migrated first via `php artisan pki:encrypt-keys-at-rest`.
     */
    public function decryptPrivateKeyPem(string $storedContent): string
    {
        $trimmed = trim($storedContent);
        if ($trimmed === '') {
            throw new RuntimeException('Private key material is empty.');
        }

        if (str_starts_with($trimmed, '-----BEGIN')) {
            throw new RuntimeException('Plaintext private key material is rejected at runtime. Run pki:encrypt-keys-at-rest to migrate.');
        }

        try {
            $decrypted = Crypt::decryptString($trimmed);
        } catch (DecryptException $e) {
            throw new RuntimeException('Unable to decrypt private key from secure custody.', 0, $e);
        }

        if (! str_contains($decrypted, 'PRIVATE KEY')) {
            throw new RuntimeException('Decrypted private key material is invalid.');
        }

        return $decrypted;
    }

    private function formatDn(array $dn): string
    {
        $parts = [];
        foreach ($dn as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $parts[] = "{$key}={$value}";
        }
        return implode(', ', $parts);
    }

    public function pemToDer(string $pem): string
    {
        $lines = explode("\n", trim($pem));
        $filtered = array_filter($lines, function ($line) {
            return ! str_starts_with(trim($line), '-----BEGIN') && ! str_starts_with(trim($line), '-----END');
        });
        return base64_decode(implode('', $filtered));
    }

    public function derToPem(string $der, string $type = 'CERTIFICATE'): string
    {
        return "-----BEGIN {$type}-----\n" .
            chunk_split(base64_encode($der), 64, "\n") .
            "-----END {$type}-----\n";
    }
}
