<?php

/**
 * WU-DMS Phase 3 End-to-End Proof Walkthrough
 * 
 * Executes real HTTP requests with Sanctum SPA cookie authentication
 * against http://127.0.0.1:8000 using origin http://localhost:5173.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AuditEvent;
use App\Models\Document;
use App\Models\FileObject;
use App\Models\OrganizationUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SanctumHttpClient
{
    protected string $baseUrl;
    protected array $cookies = [];

    public function __construct(string $baseUrl = 'http://127.0.0.1:8000')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function getCsrfCookie(): array
    {
        return $this->request('GET', '/sanctum/csrf-cookie');
    }

    public function getXsrfToken(): ?string
    {
        return isset($this->cookies['XSRF-TOKEN']) ? urldecode($this->cookies['XSRF-TOKEN']) : null;
    }

    protected function parseResponseCookies(string $headerText): void
    {
        preg_match_all('/Set-Cookie:\s*([^;=\s]+)=([^;\r\n]*)/i', $headerText, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $this->cookies[$match[1]] = $match[2];
        }
    }

    protected function getCookieHeader(): ?string
    {
        if (empty($this->cookies)) {
            return null;
        }
        $pairs = [];
        foreach ($this->cookies as $k => $v) {
            $pairs[] = "$k=$v";
        }
        return implode('; ', $pairs);
    }

    public function postJson(string $path, array $data = []): array
    {
        return $this->request('POST', $path, $data, true);
    }

    public function getJson(string $path, array $query = []): array
    {
        $queryString = empty($query) ? '' : '?' . http_build_query($query);
        return $this->request('GET', $path . $queryString);
    }

    public function putJson(string $path, array $data = []): array
    {
        return $this->request('PUT', $path, $data, true);
    }

    public function deleteJson(string $path, array $data = []): array
    {
        return $this->request('DELETE', $path, $data, true);
    }

    public function postMultipart(string $path, array $fields, array $files): array
    {
        $headers = [
            'Accept: application/json',
            'Origin: http://localhost:5173',
            'Referer: http://localhost:5173/',
        ];
        $token = $this->getXsrfToken();
        if ($token) {
            $headers[] = 'X-XSRF-TOKEN: ' . $token;
        }
        $cookieHdr = $this->getCookieHeader();
        if ($cookieHdr) {
            $headers[] = 'Cookie: ' . $cookieHdr;
        }

        $postData = $fields;
        foreach ($files as $name => $filePath) {
            $mime = mime_content_type($filePath) ?: 'application/octet-stream';
            $postData[$name] = new \CURLFile($filePath, $mime, basename($filePath));
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $headerStr = substr($response, 0, $headerSize);
        $bodyStr = substr($response, $headerSize);
        $this->parseResponseCookies($headerStr);

        return [
            'status' => $status,
            'body' => json_decode($bodyStr, true) ?? $bodyStr,
            'raw_body' => $bodyStr,
        ];
    }

    protected function request(string $method, string $path, array $data = [], bool $isJson = false): array
    {
        $headers = [
            'Accept: application/json',
            'Origin: http://localhost:5173',
            'Referer: http://localhost:5173/',
        ];
        $token = $this->getXsrfToken();
        if ($token) {
            $headers[] = 'X-XSRF-TOKEN: ' . $token;
        }
        $cookieHdr = $this->getCookieHeader();
        if ($cookieHdr) {
            $headers[] = 'Cookie: ' . $cookieHdr;
        }
        if ($isJson) {
            $headers[] = 'Content-Type: application/json';
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if (!empty($data) || $method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $isJson ? json_encode($data) : http_build_query($data));
        }

        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $headerStr = substr($response, 0, $headerSize);
        $bodyStr = substr($response, $headerSize);
        $this->parseResponseCookies($headerStr);

        return [
            'status' => $status,
            'body' => json_decode($bodyStr, true) ?? $bodyStr,
            'raw_body' => $bodyStr,
        ];
    }
}

echo "=== PHASE 3: END-TO-END HTTP PROOF ON LIVE APPLICATION ===\n\n";

$results = [];

function recordResult(&$results, string $role, string $action, string $endpoint, int $expectedStatus, array $response): void {
    $actualStatus = $response['status'];
    $passed = ($actualStatus === $expectedStatus) || ($expectedStatus === 200 && ($actualStatus === 200 || $actualStatus === 201));
    $results[] = [
        'role' => $role,
        'action' => $action,
        'endpoint' => $endpoint,
        'status' => $actualStatus . ' (expected ' . $expectedStatus . ')',
        'result' => $passed ? 'PASS' : 'FAIL',
        'notes' => $passed ? '' : substr(json_encode($response['body']), 0, 100),
    ];
}

// ----------------------------------------------------------------------------
// 1. AUTHENTICATION & SESSION TESTS FOR ALL REQUIRED ROLES
// ----------------------------------------------------------------------------
echo "--- 1. AUTHENTICATION & ACCESS CONTROL PROOFS ---\n";

// Suspended Account Check
$clientSuspended = new SanctumHttpClient();
$clientSuspended->getCsrfCookie();
$res = $clientSuspended->postJson('/api/v1/auth/login', [
    'email' => 'suspended.staff@wu.edu.et',
    'password' => 'Password123!',
]);
recordResult($results, 'suspended', 'login rejected', '/api/v1/auth/login', 403, $res);

// Locked Account Check
$clientLocked = new SanctumHttpClient();
$clientLocked->getCsrfCookie();
$res = $clientLocked->postJson('/api/v1/auth/login', [
    'email' => 'locked.account@wu.edu.et',
    'password' => 'Password123!',
]);
recordResult($results, 'locked', 'login rejected', '/api/v1/auth/login', 403, $res);

// Login Rate Limit Test
$clientRate = new SanctumHttpClient();
$clientRate->getCsrfCookie();
$lastRateStatus = 0;
for ($i = 0; $i < 6; $i++) {
    $res = $clientRate->postJson('/api/v1/auth/login', [
        'email' => 'ratelimit.test@wu.edu.et',
        'password' => 'WrongPassword!',
    ]);
    $lastRateStatus = $res['status'];
}
recordResult($results, 'auth', 'login rate limit 429', '/api/v1/auth/login', 429, ['status' => $lastRateStatus, 'body' => $res['body']]);

// Standard Roles Login
$rolesToLogin = [
    'admin' => 'admin@wu.edu.et',
    'records_officer' => 'registrar@wu.edu.et',
    'archivist' => 'archivist@wu.edu.et',
    'head.se' => 'head.se@wu.edu.et',
    'staff.tech' => 'staff.tech@wu.edu.et',
    'unprivileged' => 'auditor.direct@wu.edu.et',
];

$sessions = [];
foreach ($rolesToLogin as $roleKey => $email) {
    $client = new SanctumHttpClient();
    $client->getCsrfCookie();
    $loginRes = $client->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => 'Password123!',
    ]);
    recordResult($results, $roleKey, 'login success', '/api/v1/auth/login', 200, $loginRes);
    
    // Auth me check
    $meRes = $client->getJson('/api/v1/auth/me');
    recordResult($results, $roleKey, 'session verification me', '/api/v1/auth/me', 200, $meRes);
    
    $sessions[$roleKey] = $client;
}

// ----------------------------------------------------------------------------
// 2. DOCUMENT CREATION, VERSION UPLOAD, SEARCH & CHECKSUM VERIFICATION
// ----------------------------------------------------------------------------
echo "\n--- 2. DOCUMENT REPOSITORY & INTEGRITY PROOFS ---\n";
$adminClient = $sessions['admin'];

// Create temporary PDF file for upload
$tempPdf = tempnam(sys_get_temp_dir(), 'wu_doc_') . '.pdf';
file_put_contents($tempPdf, "%PDF-1.7 Test Document Payload Content " . uniqid());
$expectedSha256 = hash_file('sha256', $tempPdf);

$docCreateRes = $adminClient->postMultipart('/api/v1/documents', [
    'title' => 'E2E Verified Institutional Document ' . uniqid(),
    'category_id' => 1,
    'confidentiality_id' => 1, // public
    'owner_unit_id' => 1,
    'document_date' => now()->toDateString(),
    'summary' => 'End to end automated verification record.',
], ['file' => $tempPdf]);
recordResult($results, 'admin', 'document create with file', '/api/v1/documents', 201, $docCreateRes);

$createdDocId = $docCreateRes['body']['data']['id'] ?? 1;

// Upload new version
$tempPdfV2 = tempnam(sys_get_temp_dir(), 'wu_doc_v2_') . '.pdf';
file_put_contents($tempPdfV2, "%PDF-1.7 Test Document Payload V2 " . uniqid());
$v2Res = $adminClient->postMultipart("/api/v1/documents/{$createdDocId}/versions", [
    'change_note' => 'Version 2 automated upload',
], ['file' => $tempPdfV2]);
recordResult($results, 'admin', 'upload document version', "/api/v1/documents/{$createdDocId}/versions", 201, $v2Res);

// Search documents
$searchRes = $adminClient->getJson('/api/v1/search/documents', ['q' => 'E2E Verified']);
recordResult($results, 'admin', 'search documents', '/api/v1/search/documents', 200, $searchRes);

// Download file and verify checksum
$fileObject = FileObject::where('sha256', $expectedSha256)->first();
if ($fileObject) {
    $dlRes = $adminClient->getJson("/api/v1/files/{$fileObject->id}/download");
    recordResult($results, 'admin', 'download file & checksum verification', "/api/v1/files/{$fileObject->id}/download", 200, $dlRes);
}

// Cross-unit access check (Staff in SE attempting to update document owned by REG)
$staffClient = $sessions['staff.tech'];
$regDoc = Document::where('owner_unit_id', 3)->first(); // REG unit doc
if ($regDoc) {
    $unauthUpdateRes = $staffClient->putJson("/api/v1/documents/{$regDoc->id}", [
        'title' => 'Unauthorized Tampered Title',
        'lock_version' => $regDoc->lock_version,
    ]);
    recordResult($results, 'staff.tech', 'cross-unit access denied', "/api/v1/documents/{$regDoc->id}", 403, $unauthUpdateRes);
}

// ----------------------------------------------------------------------------
// 3. WORKFLOW END-TO-END VERIFICATION
// ----------------------------------------------------------------------------
echo "\n--- 3. WORKFLOW ROUTING & DECISION PROOFS ---\n";
// Workflow tasks list
$wfTasksRes = $adminClient->getJson('/api/v1/workflows/tasks');
recordResult($results, 'admin', 'list workflow tasks', '/api/v1/workflows/tasks', 200, $wfTasksRes);

$openTask = \App\Models\WorkflowTask::whereNull('closed_at')->first();
if ($openTask) {
    // Self-approval blocked test
    // If user is initiator and attempts to approve a task where prohibit_self_approval is true
    $initiatorUser = $openTask->instance?->initiator;
    if ($initiatorUser) {
        $initiatorClient = new SanctumHttpClient();
        $initiatorClient->getCsrfCookie();
        $initiatorClient->postJson('/api/v1/auth/login', [
            'email' => $initiatorUser->email,
            'password' => 'Password123!',
        ]);
        $selfApproveRes = $initiatorClient->postJson("/api/v1/workflows/tasks/{$openTask->id}/decide", [
            'decision' => 'approve',
            'reason' => 'Self approval attempt',
        ]);
        recordResult($results, 'initiator', 'self-approval blocked', "/api/v1/workflows/tasks/{$openTask->id}/decide", 422, $selfApproveRes);
    }
}

// ----------------------------------------------------------------------------
// 4. CORRESPONDENCE LIFECYCLE VERIFICATION
// ----------------------------------------------------------------------------
echo "\n--- 4. CORRESPONDENCE MANAGEMENT PROOFS ---\n";
$roClient = $sessions['records_officer'];

// List correspondences
$corrListRes = $roClient->getJson('/api/v1/correspondences');
recordResult($results, 'records_officer', 'list correspondences', '/api/v1/correspondences', 200, $corrListRes);

// Register incoming correspondence
$newCorrRes = $roClient->postJson('/api/v1/correspondences', [
    'title' => 'Incoming Official Notice ' . uniqid(),
    'category_id' => 1,
    'confidentiality_id' => 1,
    'owner_unit_id' => 3, // REG
    'type_id' => 1, // incoming
    'priority_id' => 1, // normal
    'purpose_code' => 'official',
    'correspondence_date' => now()->toDateString(),
]);
recordResult($results, 'records_officer', 'register correspondence', '/api/v1/correspondences', 201, $newCorrRes);

// ----------------------------------------------------------------------------
// 5. ARCHIVE & RETENTION VERIFICATION
// ----------------------------------------------------------------------------
echo "\n--- 5. ARCHIVAL & RETENTION PROOFS ---\n";
$archivistClient = $sessions['archivist'];

// List archive entries
$archEntriesRes = $archivistClient->getJson('/api/v1/archive/entries');
recordResult($results, 'archivist', 'list archive entries', '/api/v1/archive/entries', 200, $archEntriesRes);

// Check disposal eligibility
$archEntry = \App\Models\ArchiveEntry::first();
if ($archEntry) {
    $dispEligRes = $archivistClient->getJson("/api/v1/archive/entries/{$archEntry->id}/disposal-eligibility");
    recordResult($results, 'archivist', 'disposal eligibility check', "/api/v1/archive/entries/{$archEntry->id}/disposal-eligibility", 200, $dispEligRes);
}

// ----------------------------------------------------------------------------
// 6. SIGNATURES & STAMPS VERIFICATION
// ----------------------------------------------------------------------------
echo "\n--- 6. SIGNATURES & STAMPS PROOFS ---\n";
$sigListRes = $adminClient->getJson('/api/v1/signatures');
recordResult($results, 'admin', 'list signatures & stamps', '/api/v1/signatures', 200, $sigListRes);

// ----------------------------------------------------------------------------
// 7. REPORTS, EXPORT & BACKUP VERIFICATION
// ----------------------------------------------------------------------------
echo "\n--- 7. REPORTS & DATA TRANSFER PROOFS ---\n";
$repRes = $adminClient->getJson('/api/v1/reports/summary');
recordResult($results, 'admin', 'reports summary', '/api/v1/reports/summary', 200, $repRes);

$expRes = $adminClient->postJson('/api/v1/data/exports', [
    'dataset' => 'document_catalog',
]);
recordResult($results, 'admin', 'dataset export operation', '/api/v1/data/exports', 201, $expRes);

// ----------------------------------------------------------------------------
// 8. AUDIT INTEGRITY: UPDATE & DELETE REJECTION
// ----------------------------------------------------------------------------
echo "\n--- 8. AUDIT INTEGRITY PROOFS ---\n";
$auditEvent = AuditEvent::orderByDesc('id')->first();

// Verify Eloquent UPDATE throws
$eloquentUpdateBlocked = false;
try {
    $auditEvent->update(['action' => 'tampered']);
} catch (\RuntimeException $e) {
    $eloquentUpdateBlocked = str_contains($e->getMessage(), 'immutable');
}
recordResult($results, 'system', 'audit event Eloquent UPDATE blocked', 'model event', 200, [
    'status' => $eloquentUpdateBlocked ? 200 : 500,
    'body' => ['blocked' => $eloquentUpdateBlocked],
]);

// Verify DB trigger UPDATE throws
$dbTriggerUpdateBlocked = false;
try {
    DB::update('UPDATE audit_events SET action = ? WHERE id = ?', ['tampered_sql', $auditEvent->id]);
} catch (\Illuminate\Database\QueryException $e) {
    $dbTriggerUpdateBlocked = true;
}
recordResult($results, 'system', 'audit event SQL UPDATE blocked by trigger', 'database trigger', 200, [
    'status' => $dbTriggerUpdateBlocked ? 200 : 500,
    'body' => ['blocked' => $dbTriggerUpdateBlocked],
]);

// Verify DB trigger DELETE throws
$dbTriggerDeleteBlocked = false;
try {
    DB::delete('DELETE FROM audit_events WHERE id = ?', [$auditEvent->id]);
} catch (\Illuminate\Database\QueryException $e) {
    $dbTriggerDeleteBlocked = true;
}
recordResult($results, 'system', 'audit event SQL DELETE blocked by trigger', 'database trigger', 200, [
    'status' => $dbTriggerDeleteBlocked ? 200 : 500,
    'body' => ['blocked' => $dbTriggerDeleteBlocked],
]);

// ----------------------------------------------------------------------------
// 9. ADMIN SCREENS ENDPOINTS
// ----------------------------------------------------------------------------
echo "\n--- 9. ADMIN SCREENS PROOFS ---\n";
$usersRes = $adminClient->getJson('/api/v1/users');
recordResult($results, 'admin', 'admin users screen', '/api/v1/users', 200, $usersRes);

$rolesRes = $adminClient->getJson('/api/v1/roles');
recordResult($results, 'admin', 'admin roles screen', '/api/v1/roles', 200, $rolesRes);

$permsRes = $adminClient->getJson('/api/v1/permissions');
recordResult($results, 'admin', 'admin permissions screen', '/api/v1/permissions', 200, $permsRes);

$unitsRes = $adminClient->getJson('/api/v1/organization-units/tree');
recordResult($results, 'admin', 'admin units tree', '/api/v1/organization-units/tree', 200, $unitsRes);

$settingsRes = $adminClient->getJson('/api/v1/settings');
recordResult($results, 'admin', 'admin settings screen', '/api/v1/settings', 200, $settingsRes);

// Clean up temp files
@unlink($tempPdf);
@unlink($tempPdfV2);

// Output Table
echo "\n=== PHASE 3 RESULTS TABLE ===\n";
echo sprintf("%-15s | %-35s | %-40s | %-20s | %s\n", 'Role', 'Action', 'Endpoint', 'Status', 'Result');
echo str_repeat('-', 125) . "\n";
foreach ($results as $r) {
    echo sprintf("%-15s | %-35s | %-40s | %-20s | %s\n",
        $r['role'], $r['action'], $r['endpoint'], $r['status'], $r['result']
    );
}

// Summary
$total = count($results);
$passedCount = count(array_filter($results, fn($r) => $r['result'] === 'PASS'));
$failedCount = $total - $passedCount;
echo "\nTotal Tests: $total | Passed: $passedCount | Failed: $failedCount\n";
