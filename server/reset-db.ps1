#!/usr/bin/env pwsh
# Reset the application database (wu_dms_test) to a clean seeded state.
# This script must NEVER run against the test database (wu_dms_phpunit).

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

# Ensure we're NOT using testing environment (unset any previous APP_ENV)
Remove-Item Env:\APP_ENV -ErrorAction SilentlyContinue
Remove-Item Env:\DB_DATABASE -ErrorAction SilentlyContinue

# Clear configuration cache
Write-Host "Clearing configuration cache..." -ForegroundColor Cyan
php artisan config:clear | Out-Null

# Get the current database name
$dbName = php artisan tinker --execute="echo config('database.connections.mysql.database');" 2>&1 | Select-Object -Last 1

Write-Host "`nDatabase: $dbName" -ForegroundColor Yellow

# Guard: never wipe the phpunit test database
if ($dbName -match '_phpunit$') {
    Write-Error "ABORT: This script must not run against wu_dms_phpunit. Current database: $dbName"
    exit 1
}

# Wipe the database
Write-Host "`nWiping database $dbName..." -ForegroundColor Cyan
php artisan db:wipe --force

# Run migrations
Write-Host "`nRunning migrations..." -ForegroundColor Cyan
php artisan migrate --force

# Seed baseline vocabulary
Write-Host "`nSeeding baseline reference vocabulary..." -ForegroundColor Cyan
php artisan db:seed --class=BaselineReferenceVocabularySeeder --force

# Seed institutional credentials
Write-Host "`nSeeding institutional credentials..." -ForegroundColor Cyan
php artisan db:seed --class=InstitutionalCredentialSeeder --force

# Seed workflow baseline
Write-Host "`nSeeding workflow baseline..." -ForegroundColor Cyan
php artisan db:seed --class=WorkflowBaselineSeeder --force

# Seed research project workflow
Write-Host "`nSeeding research project workflow..." -ForegroundColor Cyan
php artisan db:seed --class=ResearchProjectWorkflowSeeder --force

# Clear all caches
Write-Host "`nClearing all caches..." -ForegroundColor Cyan
php artisan optimize:clear

Write-Host "`nDatabase reset complete!" -ForegroundColor Green
