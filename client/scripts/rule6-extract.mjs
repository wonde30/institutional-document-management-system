#!/usr/bin/env node
/**
 * Extract Rule 6 data from actual router and component files
 */

import { readFileSync } from 'fs'
import { resolve } from 'path'

// Read router file
const routerPath = 'client/src/router/index.ts'
const routerContent = readFileSync(routerPath, 'utf8')

// Extract routes (simplified parser)
const routeRegex = /\{\s*path:\s*['"]([^'"]+)['"]\s*,\s*name:\s*['"]([^'"]+)['"]\s*,\s*component:\s*\(\)\s*=>\s*import\(['"]([^'"]+)['"]\)/g

const routes = []
let match
while ((match = routeRegex.exec(routerContent)) !== null) {
  routes.push({
    path: match[1],
    name: match[2],
    componentPath: match[3]
  })
}

console.log('RULE 6: PAGE STRUCTURE FROM ACTUAL SOURCE')
console.log('==========================================\n')
console.log('Route                           | View File                        | H1 / Title                              | Breadcrumb | Eyebrow')
console.log('--------------------------------|----------------------------------|----------------------------------------|------------|----------')

for (const route of routes) {
  try {
    // Resolve component path
    const viewPath = route.componentPath.replace('@/views/', 'client/src/views/').replace('@/', 'client/src/')
    let content
    
    try {
      content = readFileSync(viewPath, 'utf8')
    } catch (e) {
      // File might not exist or path wrong
      console.log(`${route.path.padEnd(31)} | ${route.name.padEnd(32)} | FILE NOT FOUND`)
      continue
    }
    
    // Extract h1
    const h1Match = content.match(/<h1[^>]*>([^<]+)</i)
    const h1 = h1Match ? h1Match[1].trim() : ''
    
    // Extract PageHeader title (alternative)
    const titleMatch = content.match(/title="([^"]+)"/i)
    const title = titleMatch ? titleMatch[1].trim() : ''
    
    // Extract eyebrow (text-xs...text-primary or notion-eyebrow)
    const eyebrowMatch = content.match(/<[^>]*class="[^"]*text-xs[^"]*text-primary[^"]*"[^>]*>([^<]+)</i)
    const eyebrow = eyebrowMatch ? eyebrowMatch[1].trim() : ''
    
    // Breadcrumb is usually the route name or meta title
    const breadcrumb = route.name
    
    const displayH1 = (h1 || title || '—').substring(0, 39).padEnd(39)
    const displayBreadcrumb = breadcrumb.substring(0, 10)
    const displayEyebrow = eyebrow.substring(0, 9)
    
    console.log(`${route.path.padEnd(31)} | ${viewPath.split('/').pop().substring(0, 32).padEnd(32)} | ${displayH1} | ${displayBreadcrumb.padEnd(10)} | ${displayEyebrow}`)
    
  } catch (err) {
    console.log(`${route.path.padEnd(31)} | ERROR: ${err.message}`)
  }
}

console.log('\n' + routes.length + ' routes processed\n')
