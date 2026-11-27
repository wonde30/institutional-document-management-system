import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './e2e',
  // Extended timeout for real-network E2E tests (login round-trips, dashboard load, etc.)
  timeout: 45000,
  expect: {
    timeout: 10000,
  },
  fullyParallel: false,
  retries: 1,
  workers: 1,
  reporter: 'list',
  // Global setup verifies the E2E database is wu_dms_e2e before any test runs.
  // Global teardown is intentionally omitted: the E2E database persists between
  // runs for forensic inspection. Use the cleanup script to reset if needed.
  globalSetup: './e2e/global-setup.ts',
  use: {
    baseURL: 'http://localhost:5173',
    trace: 'on-first-retry',
    // Allow extra time for the Sanctum CSRF cookie + login round trip
    actionTimeout: 15000,
    navigationTimeout: 30000,
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'mobile-chrome',
      use: { ...devices['Pixel 5'] },
    },
  ],
  // Two web servers:
  //   1. Vite dev server (port 5173) — includes /api and /sanctum proxy to :8000
  //   2. Laravel API server (port 8001) — DEDICATED E2E SERVER targeting wu_dms_e2e.
  //      NEVER targets wu_dms_phpunit or wu_dms_test.
  webServer: [
    {
      // Use the Vite dev server so that /api and /sanctum proxy rules are active.
      // The preview server does NOT forward these routes — always use 'dev' for E2E.
      // VITE_PROXY_TARGET points to the dedicated E2E Laravel server on port 8001.
      command: 'npm run dev -- --port 5173',
      port: 5173,
      reuseExistingServer: false,
      timeout: 60000,
      env: {
        VITE_PROXY_TARGET: 'http://127.0.0.1:8001',
      },
    },
    {
      // Dedicated E2E Laravel server on port 8001 — targets wu_dms_e2e exclusively.
      // global-setup.ts verifies the active DB is wu_dms_e2e before tests begin.
      command: 'cd ../server && php artisan serve --host=127.0.0.1 --port=8001',
      port: 8001,
      reuseExistingServer: false,
      timeout: 60000,
      env: {
        APP_ENV: 'e2e',
        DB_DATABASE: 'wu_dms_e2e',
        DB_HOST: '127.0.0.1',
        DB_PORT: '3306',
        DB_USERNAME: 'root',
        DB_PASSWORD: '',
        QUEUE_CONNECTION: 'sync',
        SESSION_DRIVER: 'file',
        CACHE_DRIVER: 'array',
        LOG_CHANNEL: 'stderr',
        LOG_LEVEL: 'warning',
      },
    },
  ],
})
