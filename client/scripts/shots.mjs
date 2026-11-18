#!/usr/bin/env node
/**
 * Visual Capture Script
 * 
 * Logs in to WU-DMS and captures screenshots of key pages at specified resolutions.
 * 
 * Usage: node scripts/shots.mjs
 * 
 * Output: artifacts/ui-polish/*.png
 */

import { chromium } from 'playwright'
import { mkdir } from 'fs/promises'
import { join, dirname } from 'path'
import { fileURLToPath } from 'url'

const __filename = fileURLToPath(import.meta.url)
const __dirname = dirname(__filename)
const projectRoot = join(__dirname, '..')
const artifactsDir = join(projectRoot, 'artifacts', 'ui-polish')

// Configuration
const BASE_URL = process.env.VITE_API_URL?.replace('/api', '') || 'http://localhost:5173'
const LOGIN_EMAIL = process.env.SHOT_EMAIL
const LOGIN_PASSWORD = process.env.SHOT_PASSWORD

if (!LOGIN_EMAIL || !LOGIN_PASSWORD) {
  console.error('❌ Missing credentials: Set SHOT_EMAIL and SHOT_PASSWORD environment variables')
  process.exit(1)
}

const DESKTOP_LARGE = { width: 1440, height: 900 }
const DESKTOP_SMALL = { width: 1024, height: 768 }
const MOBILE = { width: 390, height: 844 }

const PAGES = [
  { path: '/dashboard', name: 'dashboard', resolutions: ['desktop-lg', 'desktop-sm', 'mobile'] },
  { path: '/documents', name: 'documents', resolutions: ['desktop-lg', 'desktop-sm'] },
  { path: '/documents/create', name: 'documents-create', resolutions: ['desktop-lg', 'desktop-sm'] },
  { path: '/admin/users', name: 'admin-users', resolutions: ['desktop-lg', 'desktop-sm'] },
  { path: '/admin/permissions', name: 'admin-permissions', resolutions: ['desktop-lg', 'desktop-sm'] },
]

/**
 * Get viewport dimensions for resolution key
 */
function getViewport(resolution) {
  switch (resolution) {
    case 'desktop-lg': return DESKTOP_LARGE
    case 'desktop-sm': return DESKTOP_SMALL
    case 'mobile': return MOBILE
    default: throw new Error(`Unknown resolution: ${resolution}`)
  }
}

/**
 * Main execution
 */
async function main() {
  console.log('🎭 Starting Playwright visual capture...')
  console.log(`📁 Output directory: ${artifactsDir}`)
  
  // Ensure output directory exists
  await mkdir(artifactsDir, { recursive: true })
  
  // Launch browser
  const browser = await chromium.launch({ headless: true })
  
  try {
    // Create initial context and page for login
    const context = await browser.newContext({ viewport: DESKTOP_LARGE })
    const page = await context.newPage()
    
    // Login
    console.log(`🔐 Logging in at ${BASE_URL}/login...`)
    await page.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' })
    await page.fill('input[type="email"]', LOGIN_EMAIL)
    await page.fill('input[type="password"]', LOGIN_PASSWORD)
    await page.click('button[type="submit"]')
    
    // Wait for navigation to dashboard
    await page.waitForURL(/\/dashboard/, { timeout: 10000 })
    console.log('✅ Login successful')
    
    // Capture screenshots for each page and resolution
    for (const { path, name, resolutions } of PAGES) {
      for (const resolution of resolutions) {
        const viewport = getViewport(resolution)
        const filename = `${name}-${resolution}.png`
        const filepath = join(artifactsDir, filename)
        
        console.log(`📸 Capturing ${path} at ${resolution} (${viewport.width}x${viewport.height})...`)
        
        // Set viewport
        await page.setViewportSize(viewport)
        
        // Navigate to page
        await page.goto(`${BASE_URL}${path}`, { waitUntil: 'networkidle', timeout: 15000 })
        
        // Wait for page to settle
        await page.waitForTimeout(1000)
        
        // Take screenshot
        await page.screenshot({
          path: filepath,
          fullPage: false, // viewport only
        })
        
        console.log(`  ✓ Saved: ${filename}`)
      }
    }
    
    console.log('✅ All screenshots captured successfully!')
    
  } catch (error) {
    console.error('❌ Error during capture:', error.message)
    throw error
  } finally {
    await browser.close()
  }
}

main().catch((error) => {
  console.error('Fatal error:', error)
  process.exit(1)
})
