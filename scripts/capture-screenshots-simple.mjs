/**
 * Simple screenshot capture using Playwright
 */

import { chromium } from 'playwright';
import fs from 'fs';
import path from 'path';

const BASE_URL = 'http://localhost:5173';
const SCREENSHOT_DIR = path.resolve('artifacts/design-qa-screenshots');
const TEST_EMAIL = 'amr.abdelhamed@wu.edu.et';
const TEST_PASSWORD = 'AdminPassword2026!';

const PAGES = [
  { route: '/dashboard', name: 'dashboard' },
  { route: '/documents', name: 'documents-list' },
  { route: '/documents/create', name: 'documents-create' },
  { route: '/users', name: 'admin-users' }
];

const VIEWPORTS = [
  { width: 1440, height: 900, name: '1440' },
  { width: 1024, height: 768, name: '1024' },
  { width: 390, height: 844, name: '390' }
];

fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });

console.log('=== Design QA Screenshot Capture ===');
console.log('Target:', BASE_URL);
console.log('Output:', SCREENSHOT_DIR);

async function capture() {
  const browser = await chromium.launch({ headless: true });
  
  for (const theme of ['light', 'dark']) {
    console.log(`\nTheme: ${theme}`);
    const context = await browser.newContext({
      baseURL: BASE_URL,
      colorScheme: theme
    });
    
    const page = await context.newPage();
    
    // Login
    console.log('  Logging in...');
    await page.goto('/login');
    await page.fill('input[type="email"]', TEST_EMAIL);
    await page.fill('input[type="password"]', TEST_PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);
    
    // Capture each page at each viewport
    for (const pageInfo of PAGES) {
      console.log(`  Page: ${pageInfo.name}`);
      
      await page.goto(pageInfo.route);
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(500);
      
      for (const viewport of VIEWPORTS) {
        await page.setViewportSize({ width: viewport.width, height: viewport.height });
        await page.waitForTimeout(200);
        
        const filename = `${pageInfo.name}_${viewport.name}_${theme}.png`;
        await page.screenshot({
          path: path.join(SCREENSHOT_DIR, filename),
          fullPage: false
        });
        console.log(`    ✓ ${filename}`);
      }
    }
    
    await context.close();
  }
  
  await browser.close();
  console.log('\n=== Screenshot capture complete ===');
  console.log('Screenshots saved to:', SCREENSHOT_DIR);
}

capture().catch(err => {
  console.error('Error:', err);
  process.exit(1);
});
