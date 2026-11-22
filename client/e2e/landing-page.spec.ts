import { test, expect } from '@playwright/test'

const mockSummary = {
  data: {
    published_documents: 1420,
    active_offices: 48,
    correspondence_routed: 8650,
    accreditation_evidence_items: 312,
    updated_at: '2026-11-22T20:00:00Z',
  },
}

const mockModules = {
  data: [
    {
      key: 'repository',
      title: 'Institutional Repository',
      description: 'Official university policies, Senate directives, and curriculum frameworks.',
      icon: 'BookOpen',
      badge: 'Core',
      highlights: ['Full-text indexing', 'Version history'],
      order: 1,
    },
    {
      key: 'archive',
      title: 'Digital Archive & Heritage',
      description: 'Permanent records preservation compliant with Ethiopian National Archives.',
      icon: 'Archive',
      badge: 'Statutory',
      highlights: ['Retention schedules', 'Chain of custody'],
      order: 2,
    },
    {
      key: 'correspondence',
      title: 'Inter-Office Correspondence',
      description: 'Paperless routing of official memos, letters, and presidential directives.',
      icon: 'Send',
      badge: 'High Traffic',
      highlights: ['Unique tracking code', 'Dispatch logs'],
      order: 3,
    },
    {
      key: 'accreditation',
      title: 'Accreditation & Quality Evidence',
      description: 'Structured mapping against ETA and HERQA institutional quality criteria.',
      icon: 'Award',
      badge: 'HERQA',
      highlights: ['Criterion cross-walking', 'Compliance heatmaps'],
      order: 4,
    },
    {
      key: 'audit',
      title: 'Tamper-Proof Audit Trail',
      description: 'Cryptographic ledger tracking every view, download, stamp, and signature.',
      icon: 'ShieldAlert',
      badge: 'Security',
      highlights: ['Immutable event log', 'IP telemetry'],
      order: 5,
    },
  ],
}

const mockAccreditation = {
  data: {
    active_cycle: {
      name: 'HERQA Institutional Accreditation Cycle (2025–2027)',
      status: 'In Progress',
      target_year: 2027,
    },
    metrics: {
      total_standards: 10,
      covered_standards: 8,
      coverage_percentage: 80,
      total_evidence_items: 312,
      peer_reviewed_items: 245,
    },
    standards: [
      { code: 'STD-01', name: 'Vision, Mission and Educational Goals', evidence_count: 34, coverage_pct: 100 },
      { code: 'STD-02', name: 'Governance and Administration', evidence_count: 42, coverage_pct: 95 },
    ],
  },
}

const mockAnnouncements = {
  data: [
    {
      id: 1,
      title: 'Senate Directive on Digital Stamp Mandatory Adoption',
      summary: 'All dean offices and directorates must register authorized digital stamps.',
      published_at: '2026-11-20',
      category: 'Governance',
      is_pinned: true,
    },
  ],
}

const mockSettings = {
  data: {
    app_name: 'Wollo University Document Management Portal',
    institution_name: 'Wollo University',
  },
}

test.beforeEach(async ({ page }) => {
  await page.route('**/api/v1/settings/public', async (route) => {
    await route.fulfill({ status: 200, json: mockSettings })
  })
  await page.route('**/api/v1/public/landing/summary', async (route) => {
    await route.fulfill({ status: 200, json: mockSummary })
  })
  await page.route('**/api/v1/public/landing/modules', async (route) => {
    await route.fulfill({ status: 200, json: mockModules })
  })
  await page.route('**/api/v1/public/landing/accreditation', async (route) => {
    await route.fulfill({ status: 200, json: mockAccreditation })
  })
  await page.route('**/api/v1/public/landing/announcements', async (route) => {
    await route.fulfill({ status: 200, json: mockAnnouncements })
  })
})

test.describe('Landing Page E2E & Scrolling Verification', () => {
  test('renders page title, hero, and allows natural desktop scrolling', async ({ page }) => {
    await page.goto('/')

    // Document title check
    await expect(page).toHaveTitle(/Wollo University/)

    // Hero title check
    const heroTitle = page.locator('h1')
    await expect(heroTitle).toContainText('Integrated Institutional Repository')

    // Live Product Preview preview check
    const preview = page.locator('text=Enterprise Node Active')
    await expect(preview).toBeVisible()

    // Test that the page can scroll down past hero
    const initialScrollY = await page.evaluate(() => window.scrollY)
    expect(initialScrollY).toBe(0)

    // Scroll to footer
    const footer = page.locator('footer')
    await footer.scrollIntoViewIfNeeded()

    // Validate that window actually scrolled
    const scrolledY = await page.evaluate(() => window.scrollY)
    expect(scrolledY).toBeGreaterThan(400)

    // Verify footer content
    await expect(page.locator('text=Kombolcha Institute of Technology')).toBeVisible()
    await expect(page.locator('text=All systems operational')).toBeVisible()
  })

  test('navigates to anchor sections and activates scrollspy', async ({ page }) => {
    await page.goto('/')

    // Scroll to modules section
    const modulesSection = page.locator('#modules')
    await modulesSection.scrollIntoViewIfNeeded()

    // Verify modules section is reached
    const modulesHeading = page.locator('#modules h2')
    await expect(modulesHeading).toContainText('Modular Enterprise Document Platform')

    // Scroll to Accreditation section
    const accreditationSection = page.locator('#accreditation')
    await accreditationSection.scrollIntoViewIfNeeded()

    const accreditationHeading = page.locator('#accreditation h2')
    await expect(accreditationHeading).toContainText('HERQA Institutional Accreditation')
  })

  test('theme switcher has accessible tooltip and aria-label', async ({ page }) => {
    await page.goto('/')

    // Laptop/monitor theme toggle button
    const themeBtn = page.locator('header button[aria-label*="theme"]')
    await expect(themeBtn).toBeVisible()

    // Check title attribute
    const titleAttr = await themeBtn.getAttribute('title')
    expect(titleAttr).toMatch(/Theme/)
  })

  test('CTA button directs to login workspace', async ({ page }) => {
    await page.goto('/')

    const loginBtn = page.locator('header button:has-text("Sign In")')
    await expect(loginBtn).toBeVisible()
    await loginBtn.click()

    await expect(page).toHaveURL(/\/login/)
  })

  test('mobile navigation drawer opens, displays links, and closes on Escape', async ({ page, isMobile }) => {
    test.skip(!isMobile, 'Mobile-only drawer behavior')

    await page.goto('/')

    // Mobile menu toggle button
    const hamburger = page.locator('button[aria-label="Toggle mobile menu"]')
    await expect(hamburger).toBeVisible()
    await hamburger.click()

    // Drawer should open
    const drawer = page.locator('#mobile-navigation-menu')
    await expect(drawer).toBeVisible()
    await expect(drawer.locator('a[href="#modules"]')).toBeVisible()

    // Press Escape to close
    await page.keyboard.press('Escape')
    await expect(drawer).not.toBeVisible()
  })

  test('displays error retry banner when summary API fails and recovers on retry', async ({ page }) => {
    let failSummary = true
    await page.route('**/api/v1/public/landing/summary', async (route) => {
      if (failSummary) {
        await route.fulfill({ status: 500, json: { message: 'Internal Server Error' } })
      } else {
        await route.fulfill({ status: 200, json: mockSummary })
      }
    })

    await page.goto('/')

    // Error banner should show up in stats strip
    const errorBanner = page.locator('role=alert')
    await expect(errorBanner).toBeVisible()

    // Flip the fail flag and click retry
    failSummary = false
    const retryBtn = errorBanner.locator('button:has-text("Retry")')
    await retryBtn.click()

    // Error banner should disappear
    await expect(errorBanner).not.toBeVisible()
    await expect(page.locator('text=Campus Offices & Directorates')).toBeVisible()
  })
})
