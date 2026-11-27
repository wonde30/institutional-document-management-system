<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DocumentCategory;
use App\Models\OrganizationUnit;
use App\Models\ProjectType;
use App\Models\User;
use Database\Seeders\BaselineReferenceVocabularySeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

class AuditPhase4ConcurrencyTest extends TestCase
{
    protected User $admin;

    protected OrganizationUnit $unit;

    protected ProjectType $projectType;

    /** @var array<int, int> */
    protected array $createdDocumentIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('wu_dms_phpunit', DB::connection()->getDatabaseName());

        if (! User::where('email', 'admin@wu.edu.et')->exists() || ! DocumentCategory::exists()) {
            $this->seed(BaselineReferenceVocabularySeeder::class);
        }

        $this->admin = User::where('email', 'admin@wu.edu.et')->firstOrFail();
        $this->unit = OrganizationUnit::firstOrFail();
        $this->projectType = ProjectType::firstOrFail();
    }

    protected function tearDown(): void
    {
        if (! empty($this->createdDocumentIds)) {
            $pdo = $this->createIndependentPdo();
            $in = implode(',', array_map('intval', array_unique($this->createdDocumentIds)));
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            $pdo->exec('SET @dms_allow_version_mutation = 1');
            $pdo->exec("DELETE FROM document_versions WHERE document_id IN ({$in})");
            $pdo->exec("DELETE FROM research_records WHERE document_id IN ({$in})");
            $pdo->exec("DELETE FROM physical_custody_events WHERE physical_original_id IN (SELECT id FROM physical_originals WHERE document_id IN ({$in}))");
            $pdo->exec("DELETE FROM physical_originals WHERE document_id IN ({$in})");
            $pdo->exec("DELETE FROM project_proposals WHERE document_id IN ({$in})");
            $pdo->exec("DELETE FROM documents WHERE id IN ({$in})");
            $pdo->exec("DELETE FROM reference_sequences WHERE sequence_key IN ('RES','HR','PROJ')");
            $pdo->exec('SET @dms_allow_version_mutation = 0');
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        parent::tearDown();
    }

    /**
     * Spawn parallel OS-level PHP processes firing real HTTP kernel requests against wu_dms_phpunit.
     *
     * @param  array<int, array{method: string, uri: string, payload: array<string, mixed>}>  $requests
     * @return array<int, array{status: int, body: array<string, mixed>|null, raw: string}>
     */
    protected function runParallelRequests(array $requests): array
    {
        $serverRoot = str_replace('\\', '/', base_path());
        $adminId = $this->admin->id;
        $processes = [];
        $workerFiles = [];

        try {
            foreach ($requests as $idx => $req) {
                $jsonReq = base64_encode((string) json_encode($req, JSON_THROW_ON_ERROR));
                $workerScript = <<<PHP
<?php
putenv('APP_ENV=testing');
putenv('DB_CONNECTION=mysql');
putenv('DB_DATABASE=wu_dms_phpunit');
\$_ENV['APP_ENV'] = 'testing';
\$_SERVER['APP_ENV'] = 'testing';
\$_ENV['DB_CONNECTION'] = 'mysql';
\$_SERVER['DB_CONNECTION'] = 'mysql';
\$_ENV['DB_DATABASE'] = 'wu_dms_phpunit';
\$_SERVER['DB_DATABASE'] = 'wu_dms_phpunit';

require '{$serverRoot}/vendor/autoload.php';
\$app = require '{$serverRoot}/bootstrap/app.php';
\$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();

if (\\Illuminate\\Support\\Facades\\DB::connection()->getDatabaseName() !== 'wu_dms_phpunit') {
    fwrite(STDERR, 'Refusing non-phpunit DB: ' . \\Illuminate\\Support\\Facades\\DB::connection()->getDatabaseName());
    exit(3);
}

\$reqData = json_decode(base64_decode('{$jsonReq}'), true);
\$user = \\App\\Models\\User::findOrFail({$adminId});
\$httpReq = \\Illuminate\\Http\\Request::create(
    \$reqData['uri'],
    \$reqData['method'],
    \$reqData['payload'],
    [],
    [],
    ['HTTP_ACCEPT' => 'application/json']
);
\$httpReq->setUserResolver(fn () => \$user);
\$app->instance('request', \$httpReq);
\\Illuminate\\Support\\Facades\\Auth::setUser(\$user);

\$kernel = \$app->make(\\Illuminate\\Contracts\\Http\\Kernel::class);
\$response = \$kernel->handle(\$httpReq);
echo json_encode([
    'status' => \$response->getStatusCode(),
    'body' => json_decode((string) \$response->getContent(), true),
]);
\$kernel->terminate(\$httpReq, \$response);
PHP;
                $tmpFile = sys_get_temp_dir().DIRECTORY_SEPARATOR.'wu_ph4_worker_'.getmypid().'_'.$idx.'_'.Str::random(4).'.php';
                file_put_contents($tmpFile, $workerScript);
                $workerFiles[] = $tmpFile;

                $proc = proc_open(
                    [PHP_BINARY, $tmpFile],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                    $pipes,
                    $serverRoot,
                    null
                );
                $this->assertNotFalse($proc, "Failed to spawn concurrent worker {$idx}");
                fclose($pipes[0]);
                $processes[$idx] = ['proc' => $proc, 'stdout' => $pipes[1], 'stderr' => $pipes[2]];
            }

            $results = [];
            foreach ($processes as $idx => $handle) {
                $stdout = (string) stream_get_contents($handle['stdout']);
                $stderr = (string) stream_get_contents($handle['stderr']);
                fclose($handle['stdout']);
                fclose($handle['stderr']);
                $exit = proc_close($handle['proc']);
                $this->assertSame(0, $exit, "Worker {$idx} exited ({$exit}). STDOUT: {$stdout} STDERR: {$stderr}");

                $decoded = json_decode($stdout, true);
                $results[$idx] = [
                    'status' => (int) ($decoded['status'] ?? 0),
                    'body' => is_array($decoded['body'] ?? null) ? $decoded['body'] : null,
                    'raw' => $stdout,
                ];
            }

            return $results;
        } finally {
            foreach ($workerFiles as $f) {
                if (is_file($f)) {
                    @unlink($f);
                }
            }
        }
    }

    public function test_concurrent_same_type_requests_allocate_unique_sequential_research_references(): void
    {
        $year = date('Y');
        $beforeNext = DB::table('reference_sequences')
            ->where('sequence_key', 'RES')
            ->where('period_key', $year)
            ->value('next_value');

        $tag = Str::random(6);
        $results = $this->runParallelRequests([
            [
                'method' => 'POST',
                'uri' => '/api/v1/research/outputs',
                'payload' => [
                    'title' => "Concurrent Research A {$tag}",
                    'office_id' => $this->unit->id,
                    'completion_date' => now()->toDateString(),
                ],
            ],
            [
                'method' => 'POST',
                'uri' => '/api/v1/research/outputs',
                'payload' => [
                    'title' => "Concurrent Research B {$tag}",
                    'office_id' => $this->unit->id,
                    'completion_date' => now()->toDateString(),
                ],
            ],
        ]);

        foreach ($results as $r) {
            if (! empty($r['body']['data']['document']['id'])) {
                $this->createdDocumentIds[] = (int) $r['body']['data']['document']['id'];
            }
        }

        $this->assertSame(201, $results[0]['status'], $results[0]['raw']);
        $this->assertSame(201, $results[1]['status'], $results[1]['raw']);

        $ref0 = $results[0]['body']['data']['document']['reference_number'] ?? null;
        $ref1 = $results[1]['body']['data']['document']['reference_number'] ?? null;

        $this->assertIsString($ref0);
        $this->assertIsString($ref1);
        $this->assertMatchesRegularExpression('/^WU\/RES\/\d{4}\/\d{4,}$/', $ref0);
        $this->assertMatchesRegularExpression('/^WU\/RES\/\d{4}\/\d{4,}$/', $ref1);
        $this->assertNotSame($ref0, $ref1);

        $n0 = (int) substr($ref0, strrpos($ref0, '/') + 1);
        $n1 = (int) substr($ref1, strrpos($ref1, '/') + 1);
        $this->assertSame(1, abs($n0 - $n1));

        // Read committed state on an independent PDO connection (outside TestCase's rolled-back tx)
        $pdo = $this->createIndependentPdo();
        $stmt = $pdo->prepare('SELECT next_value FROM reference_sequences WHERE sequence_key = ? AND period_key = ?');
        $stmt->execute(['RES', $year]);
        $afterNext = (int) $stmt->fetchColumn();
        $this->assertSame(max($n0, $n1) + 1, $afterNext);

        if ($beforeNext !== null) {
            $this->assertSame((int) $beforeNext + 2, $afterNext);
        }
    }

    public function test_concurrent_different_record_types_allocate_res_hr_and_proj_without_collision(): void
    {
        $tag = Str::random(6);
        $results = $this->runParallelRequests([
            [
                'method' => 'POST',
                'uri' => '/api/v1/research/outputs',
                'payload' => [
                    'title' => "Cross-Type Research {$tag}",
                    'office_id' => $this->unit->id,
                    'completion_date' => now()->toDateString(),
                ],
            ],
            [
                'method' => 'POST',
                'uri' => '/api/v1/staff/records',
                'payload' => [
                    'title' => "Cross-Type Staff Custody {$tag}",
                    'office_id' => $this->unit->id,
                    'physical_retention' => 'required',
                ],
            ],
            [
                'method' => 'POST',
                'uri' => '/api/v1/projects',
                'payload' => [
                    'title' => "Cross-Type Project {$tag}",
                    'office_id' => $this->unit->id,
                    'project_type_id' => $this->projectType->id,
                ],
            ],
        ]);

        foreach ($results as $r) {
            if (! empty($r['body']['data']['document']['id'])) {
                $this->createdDocumentIds[] = (int) $r['body']['data']['document']['id'];
            }
        }

        $this->assertSame(201, $results[0]['status'], $results[0]['raw']);
        $this->assertSame(201, $results[1]['status'], $results[1]['raw']);
        $this->assertSame(201, $results[2]['status'], $results[2]['raw']);

        $resRef = $results[0]['body']['data']['document']['reference_number'] ?? '';
        $hrRef = $results[1]['body']['data']['document']['reference_number'] ?? '';
        $hrPhysRef = $results[1]['body']['data']['physical_reference'] ?? '';
        $projRef = $results[2]['body']['data']['document']['reference_number'] ?? '';

        $this->assertMatchesRegularExpression('/^WU\/RES\/\d{4}\/\d{4,}$/', $resRef);
        $this->assertMatchesRegularExpression('/^WU\/HR\/\d{4}\/\d{4,}$/', $hrRef);
        $this->assertSame($hrRef.'-PHY', $hrPhysRef);
        $this->assertMatchesRegularExpression('/^WU\/PROJ\/\d{4}\/\d{4,}$/', $projRef);
    }

    public function test_independent_pdo_row_locks_prevent_concurrent_sequence_reads(): void
    {
        $year = date('Y');
        $key = 'PH4_PDO_LOCK';

        $pdo1 = $this->createIndependentPdo();
        $pdo2 = $this->createIndependentPdo();

        try {
            // Committed setup outside TestCase's outer DB transaction
            $pdo1->prepare('DELETE FROM reference_sequences WHERE sequence_key = ? AND period_key = ?')->execute([$key, $year]);
            $pdo1->prepare('INSERT INTO reference_sequences (sequence_key, period_key, next_value) VALUES (?, ?, 10)')->execute([$key, $year]);

            $pdo1->beginTransaction();
            $s1 = $pdo1->prepare('SELECT id, next_value FROM reference_sequences WHERE sequence_key = ? AND period_key = ? FOR UPDATE');
            $s1->execute([$key, $year]);
            $r1 = $s1->fetch(PDO::FETCH_ASSOC);
            $val1 = (int) $r1['next_value'];

            // Connection 2 attempting NOWAIT must fail immediately because Connection 1 holds the X-lock
            $pdo2->beginTransaction();
            $blocked = false;
            try {
                $s2Nowait = $pdo2->prepare('SELECT id, next_value FROM reference_sequences WHERE sequence_key = ? AND period_key = ? FOR UPDATE NOWAIT');
                $s2Nowait->execute([$key, $year]);
            } catch (\PDOException) {
                $blocked = true;
            }
            $this->assertTrue($blocked, 'InnoDB FOR UPDATE row lock must block competing sequence reader.');
            $pdo2->rollBack();

            $pdo1->prepare('UPDATE reference_sequences SET next_value = ? WHERE id = ?')->execute([$val1 + 1, $r1['id']]);
            $pdo1->commit();

            $pdo2->beginTransaction();
            $s2 = $pdo2->prepare('SELECT id, next_value FROM reference_sequences WHERE sequence_key = ? AND period_key = ? FOR UPDATE');
            $s2->execute([$key, $year]);
            $r2 = $s2->fetch(PDO::FETCH_ASSOC);
            $val2 = (int) $r2['next_value'];
            $pdo2->prepare('UPDATE reference_sequences SET next_value = ? WHERE id = ?')->execute([$val2 + 1, $r2['id']]);
            $pdo2->commit();

            $this->assertSame(10, $val1);
            $this->assertSame(11, $val2);
        } finally {
            if ($pdo1->inTransaction()) {
                $pdo1->rollBack();
            }
            if ($pdo2->inTransaction()) {
                $pdo2->rollBack();
            }
            $pdo1->prepare('DELETE FROM reference_sequences WHERE sequence_key = ? AND period_key = ?')->execute([$key, $year]);
        }
    }

    protected function createIndependentPdo(): PDO
    {
        $host = config('database.connections.mysql.host', '127.0.0.1');
        $port = config('database.connections.mysql.port', '3306');
        $db = config('database.connections.mysql.database', 'wu_dms_phpunit');
        $user = config('database.connections.mysql.username', 'root');
        $pass = config('database.connections.mysql.password', '');

        $this->assertSame('wu_dms_phpunit', $db);

        return new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    }
}
