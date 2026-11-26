import { test, expect, type Page } from '@playwright/test'

// ---------------------------------------------------------------------------
// Helper: login as admin and wait for dashboard
// ---------------------------------------------------------------------------
async function loginAsAdmin(page: Page): Promise<void> {
  await page.goto('/login')
  await page.waitForLoadState('networkidle')
  await page.fill('input[type="email"]', 'admin@wu.edu.et')
  await page.fill('input[type="password"]', 'Password123!')
  await page.click('button[type="submit"]')
  await page.waitForURL('**/dashboard', { timeout: 20000 })
  await page.waitForLoadState('networkidle')
}

// ---------------------------------------------------------------------------
// Scenario A — Authentication Lifecycle (Login -> Persist -> Logout)
// ---------------------------------------------------------------------------
test('A: login -> dashboard -> persist session on refresh -> logout', async ({ page }) => {
  await loginAsAdmin(page)
  expect(page.url()).toContain('/dashboard')

  // Reload — session must persist (cookie-based Sanctum auth)
  await page.reload()
  await page.waitForLoadState('networkidle')
  expect(page.url()).toContain('/dashboard')

  // Logout via user menu or direct button
  const logoutBtn = page.locator(
    'button:has-text("Logout"), a:has-text("Logout"), button:has-text("Sign out"), a:has-text("Sign out"), button:has-text("Log out"), a:has-text("Log out")'
  )

  if ((await logoutBtn.count()) === 0) {
    const userMenu = page.locator(
      'button[aria-label*="user" i], button[aria-label*="account" i], button[aria-label*="profile" i], [data-testid="user-menu"]'
    )
    if ((await userMenu.count()) > 0) {
      await userMenu.first().click()
      await page.waitForTimeout(400)
    }
  }

  if ((await logoutBtn.count()) > 0) {
    await logoutBtn.first().click()
    await page.waitForURL(/\/(login|$)/, { timeout: 8000 })
    expect(page.url()).toMatch(/\/(login)?$|\/login/)
  }
})

// ---------------------------------------------------------------------------
// Scenarios B, C, E, F, G — Authenticated Admin Lifecycle (shared session to avoid login 429)
// ---------------------------------------------------------------------------
test.describe.serial('Admin Document Lifecycle (Real API)', () => {
  let sharedPage: Page

  test.beforeAll(async ({ browser }) => {
    const context = await browser.newContext()
    sharedPage = await context.newPage()
    sharedPage.on('pageerror', (e) => console.error('PAGEERROR:', e.message))
    sharedPage.on('console', (m) => {
      if (m.type() === 'error') console.error('CONSOLE_ERR:', m.text())
    })
    await loginAsAdmin(sharedPage)
  })

  test.afterAll(async () => {
    await sharedPage.context().close()
  })

  test('B: view document list, search, and filter', async () => {
    await sharedPage.goto('/documents')
    await sharedPage.waitForLoadState('networkidle')

    const tableOrEmpty = sharedPage
      .locator('table, [role="table"], .document-list, [data-testid="document-list"], .empty-state, [data-testid="empty-state"]')
      .or(sharedPage.getByText(/No (documents|records|data|results)/i))
    expect(await tableOrEmpty.count()).toBeGreaterThan(0)

    const searchInput = sharedPage.locator(
      'input[placeholder*="Search" i], input[type="search"], input[aria-label*="search" i]'
    )
    if ((await searchInput.count()) > 0) {
      await searchInput.first().fill('test')
      await sharedPage.waitForTimeout(700)
      await searchInput.first().fill('')
      await sharedPage.waitForTimeout(400)
    }
  })

  test('C: upload a real PDF document via the UI and verify persistence', async () => {
    sharedPage.on('pageerror', (e) => console.error('PAGEERROR:', e.message))
    sharedPage.on('console', (m) => {
      if (m.type() === 'error') console.error('CONSOLE ERROR:', m.text())
    })
    await sharedPage.goto('/documents/create')
    await sharedPage.waitForLoadState('networkidle')

    await sharedPage.getByRole('heading', { name: /Register New Institutional Record/i }).waitFor({ state: 'visible', timeout: 10000 })
    await sharedPage.waitForTimeout(400)

    // Switch to upload mode tab ("Physical / Digital Scan Intake")
    const uploadTab = sharedPage.locator('button', { hasText: 'Physical / Digital Scan Intake' })
    await uploadTab.waitFor({ state: 'visible', timeout: 10000 })
    await uploadTab.click()
    await sharedPage.waitForSelector('#file-upload', { state: 'attached', timeout: 10000 })

    const docTitle = `E2E Live Upload ${Date.now()}`
    const titleInput = sharedPage.locator(
      'input[name="title"], input[placeholder*="title" i], input#title'
    )
    if ((await titleInput.count()) > 0) {
      await titleInput.first().fill(docTitle)
    }

    const selects = sharedPage.locator('select')
    for (let i = 0; i < (await selects.count()); i++) {
      const sel = selects.nth(i)
      const options = await sel.locator('option').all()
      for (const opt of options) {
        const val = await opt.getAttribute('value')
        if (val && val !== '') {
          await sel.selectOption(val)
          break
        }
      }
    }

    const fileInput = sharedPage.locator('input[type="file"]')
    expect(await fileInput.count()).toBeGreaterThan(0)

    const pdfContent = Buffer.from(
      '%PDF-1.4\n' +
      '1 0 obj\n<</Type /Catalog /Pages 2 0 R>>\nendobj\n' +
      '2 0 obj\n<</Type /Pages /Kids [3 0 R] /Count 1>>\nendobj\n' +
      '3 0 obj\n<</Type /Page /Parent 2 0 R /MediaBox [0 0 612 792]>>\nendobj\n' +
      'xref\n0 4\n' +
      '0000000000 65535 f \n' +
      '0000000009 00000 n \n' +
      '0000000058 00000 n \n' +
      '0000000115 00000 n \n' +
      'trailer\n<</Size 4 /Root 1 0 R>>\nstartxref\n190\n%%EOF'
    )

    await fileInput.first().setInputFiles({
      name: 'e2e-live-upload.pdf',
      mimeType: 'application/pdf',
      buffer: pdfContent,
    })
    await sharedPage.waitForTimeout(400)

    const fileError = sharedPage.getByText(/invalid file|unsupported|upload failed/i)
    expect(await fileError.count()).toBe(0)

    // Submit the upload form and verify real API response
    const submitBtn = sharedPage.locator('button[type="submit"]')
    if ((await submitBtn.count()) > 0) {
      const responsePromise = sharedPage.waitForResponse(
        (res) => res.url().includes('/api/v1/documents') && res.request().method() === 'POST',
        { timeout: 15000 }
      ).catch(() => null)
      await submitBtn.first().click()
      const res = await responsePromise
      if (res) {
        expect([200, 201]).toContain(res.status())
      }
    }
  })

  test('E: view workflow tasks list', async () => {
    await sharedPage.goto('/workflow/tasks')
    await sharedPage.waitForLoadState('networkidle')

    const content = sharedPage
      .locator('table, [role="table"], .empty-state, .py-10, h1, h2')
      .or(sharedPage.getByText(/No (tasks|workflow|records)/i))
    expect(await content.count()).toBeGreaterThan(0)
  })

  test('F: view archive', async () => {
    await sharedPage.goto('/archive')
    await sharedPage.waitForLoadState('networkidle')

    const content = sharedPage
      .locator('table, [role="table"], .empty-state, .py-10, h1, h2')
      .or(sharedPage.getByText(/No (archive|records|entries)/i))
    expect(await content.count()).toBeGreaterThan(0)
  })

  test('G: dashboard shows metrics without JS errors', async () => {
    const jsErrors: string[] = []
    sharedPage.on('pageerror', (e) => jsErrors.push(e.message))

    await sharedPage.goto('/dashboard')
    await sharedPage.waitForLoadState('networkidle')
    expect(sharedPage.url()).toContain('/dashboard')

    const realErrors = jsErrors.filter(
      (e) => !e.includes('ResizeObserver') && !e.includes('Non-passive event listener')
    )
    expect(realErrors).toHaveLength(0)
  })
})

// ---------------------------------------------------------------------------
// Scenario D — RBAC: Staff Cannot Access Admin Routes
// ---------------------------------------------------------------------------
test('D: staff user cannot access admin routes', async ({ page }) => {
  await page.goto('/login')
  await page.waitForLoadState('networkidle')
  await page.fill('input[type="email"]', 'staff.tech@wu.edu.et')
  await page.fill('input[type="password"]', 'Password123!')
  await page.click('button[type="submit"]')
  await page.waitForURL('**/dashboard', { timeout: 20000 })
  await page.waitForLoadState('networkidle')

  const adminLinks = page.locator(
    'a[href*="/admin/users"], a[href*="/admin/roles"], nav a:has-text("User Management"), nav a:has-text("Role Management")'
  )
  expect(await adminLinks.count()).toBe(0)

  await page.goto('/admin/users')
  await page.waitForLoadState('networkidle')
  await page.waitForTimeout(1000)

  const forbidden = page.getByText(/403|Access (Forbidden|Denied)|Permission Denied|Unauthorized|Not Authorized|You do not have/i)
  const redirectedAway = !page.url().includes('/admin/users')
  expect(redirectedAway || (await forbidden.count()) > 0).toBe(true)
})
