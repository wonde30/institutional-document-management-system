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
  //   2. Laravel API server (port 8000) — must be started externally before running E2E tests
  //      Run: cd server && php artisan serve --host=127.0.0.1 --port=8000
  //      (set DB_DATABASE=wu_dms_phpunit for the test database)
  webServer: [
    {
      // Use the Vite dev server so that /api and /sanctum proxy rules are active.
      // The preview server does NOT forward these routes — always use 'dev' for E2E.
      command: 'npm run dev -- --port 5173',
      port: 5173,
      reuseExistingServer: true,
      timeout: 60000,
    },
    {
      command: 'cd ../server && php artisan serve --host=127.0.0.1 --port=8000',
      port: 8000,
      reuseExistingServer: true,
      timeout: 60000,
      env: {
        DB_DATABASE: 'wu_dms_phpunit',
        DB_HOST: '127.0.0.1',
        DB_PORT: '3306',
        DB_USERNAME: 'root',
        DB_PASSWORD: '',
      },
    },
  ],
})
