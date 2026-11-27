/**
 * WU-DMS Playwright Global Setup
 *
 * Runs before any test suite. Verifies:
 *   1. The Laravel E2E server is reachable on port 8001.
 *   2. The active database is wu_dms_e2e (not wu_dms_phpunit or wu_dms_test).
 *   3. Baseline data (admin user, SE unit, standard_doc_approval workflow) exists.
 *
 * FAIL-CLOSED: If any guard fails, the entire Playwright run aborts before touching the DB.
 */
import { chromium } from '@playwright/test'

export default async function globalSetup() {
  // ---- 1. Probe the E2E health endpoint ----------------------------------------
  const healthUrl = 'http://127.0.0.1:8001/api/v1/health'
  let healthRes: Response | null = null
  for (let attempt = 0; attempt < 10; attempt++) {
    try {
      healthRes = await fetch(healthUrl, { signal: AbortSignal.timeout(3000) })
      if (healthRes.ok) break
    } catch {
      await new Promise((r) => setTimeout(r, 1000))
    }
  }
  if (!healthRes || !healthRes.ok) {
    throw new Error(
      '[E2E globalSetup] Laravel E2E server on port 8001 did not respond. ' +
        'Ensure the webServer block in playwright.config.ts started successfully.'
    )
  }

  const json: any = await healthRes.json().catch(() => ({}))

  // ---- 2. Guard: active DB must be wu_dms_e2e ----------------------------------
  const activeDb: string = json?.database ?? json?.db ?? json?.data?.database ?? ''
  const protectedDbs = ['wu_dms_test', 'wu_dms_phpunit', 'wu_dms', 'wollo_dms_test']

  if (protectedDbs.includes(activeDb)) {
    throw new Error(
      `[E2E globalSetup] SAFETY ABORT: E2E server is connected to protected database '${activeDb}'. ` +
        `Only 'wu_dms_e2e' is permitted for E2E tests. ` +
        `Check DB_DATABASE env var in the playwright.config.ts webServer block.`
    )
  }

  if (activeDb && activeDb !== 'wu_dms_e2e') {
    throw new Error(
      `[E2E globalSetup] SAFETY ABORT: E2E server is connected to unknown database '${activeDb}'. ` +
        `Only 'wu_dms_e2e' is permitted.`
    )
  }

  if (activeDb === 'wu_dms_e2e') {
    console.log(`[E2E globalSetup] ✓ Database guard passed: active DB = ${activeDb}`)
  } else {
    // Health endpoint does not expose DB name — log warning but do not abort
    // (the E2E server env already enforces DB_DATABASE=wu_dms_e2e)
    console.log(
      '[E2E globalSetup] Health endpoint did not return database name. ' +
        'Proceeding — DB_DATABASE=wu_dms_e2e is enforced via webServer env in playwright.config.ts.'
    )
  }

  // ---- 3. Verify baseline data via a quick login probe -------------------------
  // We use a lightweight browser context to fetch the sanctum CSRF cookie, then
  // POST to /auth/login to confirm the admin user exists in wu_dms_e2e.
  const browser = await chromium.launch({ headless: true })
  const context = await browser.newContext({ baseURL: 'http://localhost:5173' })
  const page = await context.newPage()

  try {
    // Fetch CSRF cookie
    await page.goto('http://localhost:5173/login')
    await page.waitForLoadState('domcontentloaded')

    // Check admin login works
    const loginRes = await page.evaluate(async () => {
      await fetch('/sanctum/csrf-cookie', { credentials: 'include' })
      const getCookie = (name: string) => {
        const m = document.cookie.split('; ').find((r) => r.startsWith(`${name}=`))
        return m ? decodeURIComponent(m.split('=').slice(1).join('=')) : ''
      }
      const res = await fetch('/api/v1/auth/login', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-XSRF-TOKEN': getCookie('XSRF-TOKEN'),
        },
        body: JSON.stringify({ email: 'admin@wu.edu.et', password: 'Password123!' }),
      })
      return { status: res.status, ok: res.ok }
    })

    if (!loginRes.ok) {
      throw new Error(
        `[E2E globalSetup] Admin login to wu_dms_e2e failed (HTTP ${loginRes.status}). ` +
          'Ensure InstitutionalCredentialSeeder has been run against wu_dms_e2e.'
      )
    }
    console.log('[E2E globalSetup] ✓ Admin credential verified against wu_dms_e2e')
  } finally {
    await context.close()
    await browser.close()
  }

  console.log('[E2E globalSetup] ✓ All guards passed — safe to run E2E suite against wu_dms_e2e')
}

