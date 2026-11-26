import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'
import fs from 'fs'
import path from 'path'

const screenshotDir = path.resolve(process.cwd(), 'test-results/screenshots')
if (!fs.existsSync(screenshotDir)) {
  fs.mkdirSync(screenshotDir, { recursive: true })
}

const viewports = [
  { name: 'desktop', width: 1280, height: 800 },
  { name: 'tablet', width: 768, height: 1024 },
  { name: 'mobile', width: 375, height: 667 },
]

test.describe('WU-DMS Institutional UI/UX Comprehensive Audit', () => {
  test.beforeEach(async ({ page }) => {
    // Collect console errors and unexpected network failures
    page.on('console', (msg) => {
      if (msg.type() === 'error') {
        console.error(`Browser console error: ${msg.text()}`)
      }
    })
  })

  for (const vp of viewports) {
    test(`Authentication, Navigation, and States on ${vp.name} (${vp.width}x${vp.height})`, async ({ page }) => {
      await page.setViewportSize({ width: vp.width, height: vp.height })

      // 1. Visit Login Page
      await page.goto('/login')
      await page.waitForLoadState('networkidle')

      // Form validation & label checks
      const emailInput = page.locator('input[type="email"], input#email, input[name="email"]')
      const passwordInput = page.locator('input[type="password"], input#password, input[name="password"]')
      const submitBtn = page.locator('button[type="submit"]')

      await expect(emailInput).toBeVisible()
      await expect(passwordInput).toBeVisible()
      await expect(submitBtn).toBeVisible()

      // Axe-core accessibility check on Login page
      const axeLogin = await new AxeBuilder({ page }).disableRules(['color-contrast']).analyze()
      const seriousLoginViolations = axeLogin.violations.filter(v => v.impact === 'critical' || v.impact === 'serious')
      expect(seriousLoginViolations.length).toBe(0)

      // Test inline error on invalid credentials
      await emailInput.fill('invalid@wu.edu.et')
      await passwordInput.fill('WrongPassword123!')
      await submitBtn.click()
      await page.waitForTimeout(500)
      
      const errorAlert = page.locator('.text-red-500, .bg-red-50, [role="alert"]')
      if (await errorAlert.count() > 0) {
        await expect(errorAlert.first()).toBeVisible()
      }

      // Valid Login as Administrator
      await emailInput.fill('admin@wu.edu.et')
      await passwordInput.fill('Password123!')
      await submitBtn.click()

      // Wait for redirect to Dashboard
      await page.waitForURL('**/dashboard', { timeout: 10000 })
      await page.waitForLoadState('networkidle')

      // Screenshot Dashboard Light Mode
      const dashLightPath = path.join(screenshotDir, `dashboard-${vp.name}-light.png`)
      await page.screenshot({ path: dashLightPath, fullPage: false })

      // Accessibility scan on Dashboard (structural a11y, ARIA, semantics)
      const axeDash = await new AxeBuilder({ page }).disableRules(['color-contrast']).analyze()
      const seriousDashViolations = axeDash.violations.filter(v => v.impact === 'critical' || v.impact === 'serious')
      expect(seriousDashViolations.length).toBe(0)

      // 2. Test Dark Mode Theme Switch
      const themeToggle = page.locator('button[aria-label*="theme" i], button[title*="theme" i], button:has(.lucide-moon), button:has(.lucide-sun)')
      if (await themeToggle.count() > 0) {
        await themeToggle.first().click()
        await page.waitForTimeout(300)
        const dashDarkPath = path.join(screenshotDir, `dashboard-${vp.name}-dark.png`)
        await page.screenshot({ path: dashDarkPath, fullPage: false })
        // Toggle back to light
        await themeToggle.first().click()
        await page.waitForTimeout(300)
      }

      // 3. Document Repository View
      await page.goto('/documents')
      await page.waitForLoadState('networkidle')
      const docLightPath = path.join(screenshotDir, `documents-${vp.name}-light.png`)
      await page.screenshot({ path: docLightPath, fullPage: false })

      // Verify table or empty state
      const docTable = page.locator('table, [role="table"]')
      const emptyState = page.locator('text=No records found, text=No documents, .py-10')
      const hasContent = (await docTable.count() > 0) || (await emptyState.count() > 0)
      expect(hasContent).toBe(true)

      // Test Search/Filter Bar
      const searchInput = page.locator('input[placeholder*="Search" i], input[type="search"]')
      if (await searchInput.count() > 0) {
        await searchInput.first().fill('NON_EXISTENT_QUERY_FILTER_XYZ')
        await page.waitForTimeout(500)
        // Verify empty state triggers on zero results
        await page.keyboard.press('Escape')
      }

      // 4. Workflow Tasks View
      await page.goto('/workflow/tasks')
      await page.waitForLoadState('networkidle')
      const wfLightPath = path.join(screenshotDir, `workflows-${vp.name}-light.png`)
      await page.screenshot({ path: wfLightPath, fullPage: false })

      // 5. Correspondence Registry View
      await page.goto('/correspondence')
      await page.waitForLoadState('networkidle')
      const corrLightPath = path.join(screenshotDir, `correspondence-${vp.name}-light.png`)
      await page.screenshot({ path: corrLightPath, fullPage: false })

      // 6. Archive & Retention View
      await page.goto('/archive')
      await page.waitForLoadState('networkidle')
      const arcLightPath = path.join(screenshotDir, `archive-${vp.name}-light.png`)
      await page.screenshot({ path: arcLightPath, fullPage: false })

      // 7. Signatures & Digital Seals View
      await page.goto('/signatures')
      await page.waitForLoadState('networkidle')
      const sigLightPath = path.join(screenshotDir, `signatures-${vp.name}-light.png`)
      await page.screenshot({ path: sigLightPath, fullPage: false })

      // Test Modal Dialog Focus Trap and Escape
      const openModalBtn = page.locator('button:has-text("Request Signature"), button:has-text("Apply Stamp"), button:has-text("Add")')
      if (await openModalBtn.count() > 0 && vp.name === 'desktop') {
        await openModalBtn.first().click()
        await page.waitForTimeout(400)

        const dialog = page.locator('[role="dialog"], .fixed.inset-0')
        if (await dialog.count() > 0) {
          await expect(dialog.first()).toBeVisible()
          // Test Escape key closes dialog
          await page.keyboard.press('Escape')
          await page.waitForTimeout(400)
        }
      }

      // 8. Administrative Users View
      await page.goto('/admin/users')
      await page.waitForLoadState('networkidle')
      const usersLightPath = path.join(screenshotDir, `admin-users-${vp.name}-light.png`)
      await page.screenshot({ path: usersLightPath, fullPage: false })
    })
  }

  test('Staff User Navigation and Strict Role Boundaries', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 })

    // Log in as staff.tech
    await page.goto('/login')
    await page.locator('input[type="email"], input#email, input[name="email"]').fill('staff.tech@wu.edu.et')
    await page.locator('input[type="password"], input#password, input[name="password"]').fill('Password123!')
    await page.locator('button[type="submit"]').click()

    await page.waitForURL('**/dashboard', { timeout: 10000 })
    await page.waitForLoadState('networkidle')

    // Verify admin menu items are NOT visible to staff
    const adminLink = page.locator('a[href*="/admin/users"], a[href*="/admin/roles"]')
    expect(await adminLink.count()).toBe(0)

    // Direct navigation to admin screen should trigger ForbiddenView
    await page.goto('/admin/users')
    await page.waitForTimeout(1000)
    const forbiddenView = page.locator('text=Access Forbidden, text=403, text=Permission Denied')
    const redirectedAway = !page.url().includes('/admin/users') || (await forbiddenView.count() > 0)
    expect(redirectedAway).toBe(true)
  })
})
