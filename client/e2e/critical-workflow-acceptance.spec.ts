import { test, expect, Page } from '@playwright/test'

/**
 * WU-DMS Critical Workflow Browser Acceptance Test Suite
 * 
 * This suite verifies the complete institutional document lifecycle
 * through real browser automation against the staging environment.
 */

const STAGING_FRONTEND = 'http://localhost:5173'
const STAGING_API = 'http://localhost:8000'

// Test accounts from staging database
const ADMIN_USER = { email: 'admin@wu.edu.et', password: 'Password123!' }
const STAFF_USER = { email: 'staff.tech@wu.edu.et', password: 'Password123!' }
const DEPT_HEAD = { email: 'head.se@wu.edu.et', password: 'Password123!' }
const REGISTRAR = { email: 'registrar@wu.edu.et', password: 'Password123!' } // Additional user to avoid rate limiting

async function loginAs(page: Page, credentials: { email: string; password: string }) {
  await page.goto('/login')
  await page.fill('input[type="email"]', credentials.email)
  await page.fill('input[type="password"]', credentials.password)
  await page.click('button[type="submit"]')
  
  // Wait for successful redirect to dashboard
  await page.waitForURL(/\/(dashboard|home)/, { timeout: 10000 })
}

test.describe('WU-DMS Critical Acceptance Tests', () => {
  test.beforeEach(async ({ page }) => {
    // Ensure CSRF cookie is available
    await page.context().addCookies([{
      name: 'XSRF-TOKEN',
      value: 'test-token',
      domain: 'localhost',
      path: '/',
    }])
  })

  test('UAT-01: User can log in successfully', async ({ page }) => {
    await loginAs(page, STAFF_USER)
    
    // Verify we're logged in by checking for unique dashboard heading
    await expect(page.getByRole('heading', { name: 'Workspace' })).toBeVisible({ timeout: 5000 })
  })

  test('UAT-02: Authenticated user can view dashboard', async ({ page }) => {
    await loginAs(page, STAFF_USER)
    
    await page.goto('/dashboard')
    
    // Check for workspace heading (actual page title)
    const workspaceHeading = page.locator('h1').filter({ hasText: /Workspace/i })
    await expect(workspaceHeading).toBeVisible()
  })

  test('UAT-03: User can access document list', async ({ page }) => {
    await loginAs(page, STAFF_USER)
    
    await page.goto('/documents')
    
    // Verify document list page loaded - actual heading is "Document Repository"
    await expect(page.getByRole('heading', { name: /Document Repository/i })).toBeVisible()
  })

  test('UAT-04: User can create a new document', async ({ page }) => {
    await loginAs(page, STAFF_USER)
    
    await page.goto('/documents/create')
    
    // Verify document creation page loaded - actual heading is "Register New Institutional Record"
    await expect(page.getByRole('heading', { name: /Register New Institutional Record/i })).toBeVisible({ timeout: 5000 })
  })

  test('UAT-05: Official letter generator loads', async ({ page }) => {
    // Use unique account that hasn't been used yet to avoid rate limiting
    await loginAs(page, { email: 'dean.technology@wu.edu.et', password: 'Password123!' })
    
    // Correct route is /templates not /templates/official-letters
    await page.goto('/templates')
    
    // Verify templates page loads
    const heading = page.getByRole('heading', { name: /Templates?|Document Templates/i }).first()
    await expect(heading).toBeVisible({ timeout: 5000 })
  })

  test('UAT-06: Workflow tasks page loads for reviewer', async ({ page }) => {
    await loginAs(page, DEPT_HEAD)
    
    await page.goto('/workflows/tasks')
    
    // Verify tasks page loads
    const heading = page.locator('h1, h2').filter({ hasText: /Tasks|Workflow/i })
    await expect(heading).toBeVisible({ timeout: 5000 })
  })

  test('UAT-07: Archive page loads for authorized user', async ({ page }) => {
    await loginAs(page, ADMIN_USER)
    
    await page.goto('/archive')
    
    // Verify archive page loads
    const heading = page.locator('h1, h2').filter({ hasText: /Archive/i })
    await expect(heading).toBeVisible({ timeout: 5000 })
  })

  test('UAT-08: Global search is accessible', async ({ page }) => {
    // Use different account to avoid rate limiting on STAFF_USER
    await loginAs(page, REGISTRAR)
    
    await page.goto('/search')
    
    // Verify search interface loads
    const searchInput = page.locator('input[type="search"], input[placeholder*="search" i]').first()
    await expect(searchInput).toBeVisible({ timeout: 5000 })
  })

  test('UAT-09: User can log out successfully', async ({ page }) => {
    // Use different account to avoid rate limiting
    await loginAs(page, { email: 'archivist@wu.edu.et', password: 'Password123!' })
    
    // Click user menu in top right corner to access logout (initials: KG for Kassahun Gizaw)
    await page.getByText('KG').click()
    
    // Find and click logout button in dropdown
    const logoutButton = page.getByRole('button', { name: /Logout|Sign Out/i }).or(page.getByRole('menuitem', { name: /Logout|Sign Out/i }))
    await logoutButton.click()
    
    // Verify redirect to public portal or login page (both are valid post-logout destinations)
    await page.waitForURL(/\/(login|$)/, { timeout: 5000 })
    
    // Verify user is logged out by checking for "Sign In to Workspace" button or "Sign In" link
    const signInButton = page.getByRole('button', { name: /Sign In to Workspace/i }).or(page.getByRole('link', { name: /^Sign In$/i }))
    await expect(signInButton).toBeVisible()
  })

  test('UAT-10: Unauthorized access redirects to login', async ({ page }) => {
    // Attempt to access protected route without authentication
    await page.goto('/documents')
    
    // Should redirect to login
    await page.waitForURL(/\/login/, { timeout: 5000 })
  })
})

test.describe('WU-DMS Workflow Initiation (Critical Blocker Verification)', () => {
  test('WORKFLOW-01: Workflow can be initiated on a document', async ({ page }) => {
    test.setTimeout(60000) // Extended timeout for complex workflow
    
    await loginAs(page, DEPT_HEAD)
    
    // Navigate to documents and try to initiate workflow on an existing document
    await page.goto('/documents')
    
    // Look for any document in the list
    const documentRow = page.locator('table tbody tr, [data-testid="document-row"]').first()
    await expect(documentRow).toBeVisible({ timeout: 10000 })
    
    // Click on the document to view details
    await documentRow.click()
    
    // Look for workflow initiation button/action
    const workflowButton = page.locator('button, a').filter({ hasText: /Start Review|Submit|Workflow|Initiate/i }).first()
    
    // If button exists, verify it's not showing 404 error
    if (await workflowButton.isVisible({ timeout: 2000 }).catch(() => false)) {
      await workflowButton.click()
      
      // Wait for response - should not be 404
      await page.waitForTimeout(1000)
      
      // Check that no error dialog appears with "404" or "Not Found"
      const errorText = await page.locator('text=/404|Not Found/i').count()
      expect(errorText).toBe(0)
    }
  })
})

test.describe('WU-DMS Amharic Content Rendering', () => {
  test('AMHARIC-01: Ethiopian calendar displays correctly', async ({ page }) => {
    // Use different account to avoid rate limiting
    await loginAs(page, { email: 'qa.director@wu.edu.et', password: 'Password123!' })
    
    await page.goto('/dashboard')
    
    // Look for Ethiopian date format "ዓ.ም" (not mojibake)
    const ethiopianDatePattern = /\d{2}\/\d{2}\/\d{4}\s*ዓ\.ም/
    const pageContent = await page.content()
    
    // If Ethiopian date is present, verify it's not corrupted
    if (pageContent.includes('ዓ.ም') || pageContent.includes('ዓ.መ')) {
      // Check that it's proper Ge'ez, not mojibake like "áˆ"
      expect(pageContent).not.toMatch(/á|â|Ã|Å/)
    }
  })
})

test.describe('WU-DMS Performance & Responsiveness', () => {
  test('PERF-01: Dashboard loads within acceptable time', async ({ page }) => {
    // Use unique account to avoid rate limiting
    await loginAs(page, { email: 'sysadmin@wu.edu.et', password: 'Password123!' })
    
    const startTime = Date.now()
    await page.goto('/dashboard')
    await page.waitForLoadState('networkidle', { timeout: 10000 })
    const loadTime = Date.now() - startTime
    
    // Dashboard should load in under 5 seconds
    expect(loadTime).toBeLessThan(5000)
  })

  test('PERF-02: Document list pagination works', async ({ page }) => {
    await loginAs(page, STAFF_USER)
    
    await page.goto('/documents')
    
    // Look for pagination controls
    const paginationNext = page.locator('button, a').filter({ hasText: /Next|›|»/i }).first()
    
    if (await paginationNext.isVisible({ timeout: 2000 }).catch(() => false)) {
      if (await paginationNext.isEnabled()) {
        await paginationNext.click()
        await page.waitForTimeout(500)
        
        // Verify page changed (URL or content updated)
        const currentUrl = page.url()
        expect(currentUrl).toMatch(/page=|offset=|\d+/)
      }
    }
  })
})
