<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseBackupService
{
    protected string $mysqldumpPath;
    protected string $mysqlPath;
    protected string $mysqlbinlogPath;

    public function __construct()
    {
        $this->mysqldumpPath = $this->resolveBinary('mysqldump');
        $this->mysqlPath = $this->resolveBinary('mysql');
        $this->mysqlbinlogPath = $this->resolveBinary('mysqlbinlog');
    }

    /**
     * Resolve path to MySQL executable, checking common Windows installation locations and PATH.
     */
    protected function resolveBinary(string $binaryName): string
    {
        $exeName = PHP_OS_FAMILY === 'Windows' ? "{$binaryName}.exe" : $binaryName;

        $candidatePaths = [
            "C:\\Program Files\\MySQL\\MySQL Server 8.4\\bin\\{$exeName}",
            "C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\{$exeName}",
            "C:\\xampp\\mysql\\bin\\{$exeName}",
            "C:\\laragon\\bin\\mysql\\current\\bin\\{$exeName}",
            "/usr/bin/{$binaryName}",
            "/usr/local/bin/{$binaryName}",
        ];

        foreach ($candidatePaths as $path) {
            if (File::exists($path)) {
                return $path;
            }
        }

        // Check if executable is in PATH
        $lookupCmd = PHP_OS_FAMILY === 'Windows' ? "where {$exeName}" : "which {$binaryName}";
        $process = Process::fromShellCommandline($lookupCmd);
        $process->run();
        if ($process->isSuccessful()) {
            $lines = explode(PHP_EOL, trim($process->getOutput()));
            if (!empty($lines[0]) && File::exists(trim($lines[0]))) {
                return trim($lines[0]);
            }
        }

        throw new RuntimeException("Required MySQL binary '{$binaryName}' could not be located on this host.");
    }

    /**
     * Generate consistent single-transaction logical InnoDB dump.
     */
    public function dump(string $outputSqlPath, ?string $database = null): array
    {
        $startTime = microtime(true);
        $dbConfig = config('database.connections.mysql');
        $dbName = $database ?? $dbConfig['database'];
        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = (string) ($dbConfig['port'] ?? '3306');
        $user = $dbConfig['username'] ?? 'root';
        $pass = $dbConfig['password'] ?? '';

        // Query active binary log coordinates prior to backup
        $binlogStatus = null;
        try {
            $binlogRows = DB::select('SHOW BINARY LOG STATUS');
            if (!empty($binlogRows)) {
                $binlogStatus = [
                    'file' => $binlogRows[0]->File,
                    'position' => (int) $binlogRows[0]->Position,
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Binary log status query skipped: ' . $e->getMessage());
        }

        // Ensure output directory exists
        $outputDir = dirname($outputSqlPath);
        if (!File::isDirectory($outputDir)) {
            File::makeDirectory($outputDir, 0750, true);
        }

        // Generate temporary secure options file for credentials (never expose password in process list)
        $tempCnf = storage_path('app/pki/mysql_backup_' . bin2hex(random_bytes(8)) . '.cnf');
        $cnfContent = "[client]\n"
            . "user = \"{$user}\"\n"
            . "password = \"{$pass}\"\n"
            . "host = \"{$host}\"\n"
            . "port = {$port}\n";

        File::put($tempCnf, $cnfContent);
        if (PHP_OS_FAMILY !== 'Windows') {
            chmod($tempCnf, 0600);
        }

        try {
            $command = [
                $this->mysqldumpPath,
                "--defaults-extra-file={$tempCnf}",
                '--single-transaction',
                '--quick',
                '--routines',
                '--triggers',
                '--events',
                '--hex-blob',
                '--default-character-set=utf8mb4',
                '--set-gtid-purged=OFF',
                $dbName,
            ];

            $process = new Process($command);
            $process->setTimeout(300);
            
            // Stream output directly to file to prevent memory exhaustion
            $fileHandle = fopen($outputSqlPath, 'wb');
            if (!$fileHandle) {
                throw new RuntimeException("Cannot open destination dump file: {$outputSqlPath}");
            }

            $process->run(function ($type, $buffer) use ($fileHandle) {
                if ($type === Process::OUT) {
                    fwrite($fileHandle, $buffer);
                }
            });
            fclose($fileHandle);

            if (!$process->isSuccessful()) {
                throw new RuntimeException("mysqldump failed: " . $process->getErrorOutput());
            }

            // Verify file validity
            if (!File::exists($outputSqlPath) || filesize($outputSqlPath) < 500) {
                throw new RuntimeException("Generated SQL dump file is empty or corrupted: {$outputSqlPath}");
            }

            // Read header to confirm valid SQL dump
            $header = file_get_contents($outputSqlPath, false, null, 0, 100);
            if (!str_contains($header, 'MySQL dump') && !str_contains($header, '/*')) {
                throw new RuntimeException("SQL dump header validation failed: {$outputSqlPath}");
            }

            $byteSize = filesize($outputSqlPath);
            $sha256 = hash_file('sha256', $outputSqlPath);
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            return [
                'path' => $outputSqlPath,
                'database' => $dbName,
                'byte_size' => $byteSize,
                'sha256' => $sha256,
                'binlog_coordinates' => $binlogStatus,
                'duration_ms' => $durationMs,
                'completed_at' => now()->toIso8601String(),
            ];
        } finally {
            if (File::exists($tempCnf)) {
                File::delete($tempCnf);
            }
        }
    }

    /**
     * Restore database dump into an isolated target database.
     * Enforces protection against accidental live database overwrite.
     */
    public function restore(string $sqlPath, string $targetDatabase, bool $allowProductionOverwrite = false): array
    {
        $startTime = microtime(true);
        $prodDb = config('database.connections.mysql.database');

        if ($targetDatabase === $prodDb && !$allowProductionOverwrite) {
            throw new RuntimeException(
                "Restoration blocked: Target database '{$targetDatabase}' is the live production database. " .
                "Restoration tests must strictly target an isolated recovery database (e.g. wu_dms_recovery_test)."
            );
        }

        if (!File::exists($sqlPath)) {
            throw new RuntimeException("SQL dump file not found: {$sqlPath}");
        }

        $dbConfig = config('database.connections.mysql');
        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = (string) ($dbConfig['port'] ?? '3306');
        $user = $dbConfig['username'] ?? 'root';
        $pass = $dbConfig['password'] ?? '';

        $tempCnf = storage_path('app/pki/mysql_restore_' . bin2hex(random_bytes(8)) . '.cnf');
        $cnfContent = "[client]\nuser = \"{$user}\"\npassword = \"{$pass}\"\nhost = \"{$host}\"\nport = {$port}\n";
        File::put($tempCnf, $cnfContent);

        try {
            // Create target isolated database if it doesn't exist
            $createDbCmd = [
                $this->mysqlPath,
                "--defaults-extra-file={$tempCnf}",
                '-e',
                "CREATE DATABASE IF NOT EXISTS `{$targetDatabase}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;",
            ];
            $createProc = new Process($createDbCmd);
            $createProc->mustRun();

            // Execute restore with retry on transient deadlock
            $restoreCmd = [
                $this->mysqlPath,
                "--defaults-extra-file={$tempCnf}",
                '--default-character-set=utf8mb4',
                $targetDatabase,
            ];

            $maxAttempts = 3;
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                try {
                    $process = new Process($restoreCmd);
                    $process->setInput(fopen($sqlPath, 'rb'));
                    $process->setTimeout(600);
                    $process->mustRun();
                    break;
                } catch (\Throwable $e) {
                    if ($attempt === $maxAttempts || ! str_contains($e->getMessage(), 'Deadlock')) {
                        throw $e;
                    }
                    usleep(500000);
                }
            }

            // Verify target database schema and table count
            $verifyConfig = $dbConfig;
            $verifyConfig['database'] = $targetDatabase;
            config(['database.connections.mysql_recovery_check' => $verifyConfig]);

            $tables = DB::connection('mysql_recovery_check')->select('SHOW TABLES');
            $tableCount = count($tables);

            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            return [
                'target_database' => $targetDatabase,
                'sql_source' => $sqlPath,
                'restored_table_count' => $tableCount,
                'duration_ms' => $durationMs,
                'is_verified' => $tableCount >= 118,
                'completed_at' => now()->toIso8601String(),
            ];
        } finally {
            if (File::exists($tempCnf)) {
                File::delete($tempCnf);
            }
        }
    }

    /**
     * Replay binary logs up to an optional point-in-time timestamp into an isolated database.
     */
    public function replayBinlogs(
        string $binlogFilePath,
        string $targetDatabase,
        int $startPosition = 4,
        ?string $stopDatetime = null,
        ?string $sourceDatabase = null
    ): array {
        $startTime = microtime(true);
        if (!File::exists($binlogFilePath)) {
            throw new RuntimeException("Binary log file not found: {$binlogFilePath}");
        }

        $dbConfig = config('database.connections.mysql');
        $sourceDb = $sourceDatabase ?? $dbConfig['database'];
        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = (string) ($dbConfig['port'] ?? '3306');
        $user = $dbConfig['username'] ?? 'root';
        $pass = $dbConfig['password'] ?? '';

        $tempCnf = storage_path('app/pki/mysql_binlog_' . bin2hex(random_bytes(8)) . '.cnf');
        $cnfContent = "[client]\nuser = \"{$user}\"\npassword = \"{$pass}\"\nhost = \"{$host}\"\nport = {$port}\n";
        File::put($tempCnf, $cnfContent);

        try {
            $binlogCmd = [
                $this->mysqlbinlogPath,
                "--start-position={$startPosition}",
            ];

            if ($sourceDb !== $targetDatabase) {
                $binlogCmd[] = "--rewrite-db={$sourceDb}->{$targetDatabase}";
            }

            if ($stopDatetime !== null) {
                $binlogCmd[] = "--stop-datetime={$stopDatetime}";
            }

            $binlogCmd[] = $binlogFilePath;

            $binlogProcess = new Process($binlogCmd);
            $binlogProcess->setTimeout(300);
            $binlogProcess->mustRun();
            $sqlEvents = $binlogProcess->getOutput();

            if (!empty(trim($sqlEvents))) {
                // Pipe into mysql target database
                $mysqlCmd = [
                    $this->mysqlPath,
                    "--defaults-extra-file={$tempCnf}",
                    $targetDatabase,
                ];

                $mysqlProcess = new Process($mysqlCmd);
                $mysqlProcess->setInput($sqlEvents);
                $mysqlProcess->setTimeout(300);
                $mysqlProcess->mustRun();
            }

            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            return [
                'binlog_file' => $binlogFilePath,
                'source_database' => $sourceDb,
                'target_database' => $targetDatabase,
                'start_position' => $startPosition,
                'stop_datetime' => $stopDatetime,
                'duration_ms' => $durationMs,
                'status' => 'SUCCESS',
            ];
        } finally {
            if (File::exists($tempCnf)) {
                File::delete($tempCnf);
            }
        }
    }
}
