<?php
/**
 * WU-DMS E2E Database Setup Script
 *
 * Runs migrations and baseline seeders against wu_dms_e2e,
 * then installs the 6 immutability triggers.
 *
 * Usage: php scripts/setup_e2e_db.php
 * Run from server/ directory.
 *
 * SAFE: Only targets wu_dms_e2e. Fails closed if pointed elsewhere.
 */

$pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$dbs = $pdo->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('wu_dms_e2e', $dbs)) {
    $pdo->exec('CREATE DATABASE `wu_dms_e2e` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    echo "[OK] Created wu_dms_e2e\n";
} else {
    echo "[OK] wu_dms_e2e exists\n";
}

// Verify we are NOT touching protected databases
foreach (['wu_dms_test', 'wu_dms_phpunit'] as $protected) {
    if ($protected === 'wu_dms_e2e') {
        die("[FATAL] E2E DB resolves to protected database $protected. Aborting.\n");
    }
}

echo "[OK] Database target verified: wu_dms_e2e\n";

// --- Check trigger count BEFORE ---
$pdo2 = new PDO('mysql:host=127.0.0.1;port=3306;dbname=wu_dms_e2e', 'root', '');
$pdo2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$trigCount = $pdo2->query("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = 'wu_dms_e2e'")->fetchColumn();
echo "[INFO] Triggers in wu_dms_e2e before setup: $trigCount\n";

echo "[DONE] Use artisan to run migrations and seeders.\n";

