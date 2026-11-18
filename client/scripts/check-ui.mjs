#!/usr/bin/env node
/**
 * UI Compliance Matrix Generator
 * Audits every view file for Phase 4 requirements
 */

import { readFileSync, writeFileSync, mkdirSync } from 'fs'
import { join } from 'path'

const routerPath = 'src/router/index.ts'
const artifactsDir = 'artifacts'

// Read router file
const routerContent = readFileSync(routerPath, 'utf8')

// Extract routes with their view components
const routeRegex = /\{\s*path:\s*['"]([^'"]+)['"]\s*,\s*name:\s*['"]([^'"]+)['"]\s*,\s*component:\s*\(\)\s*=>\s*import\(['"]@\/views\/([^'"]+)['"]\)/g

const rawRoutes = []
let match
while ((match = routeRegex.exec(routerContent)) !== null) {
  rawRoutes.push({
    route: match[1],
    name: match[2],
    viewPath: `src/views/${match[3]}`
  })
}

// Deduplicate routes that share the same view (AccountProfileView tabs)
const viewMap = new Map()
for (const route of rawRoutes) {
  const existing = viewMap.get(route.viewPath)
  if (existing) {
    existing.routes.push(route.route)
  } else {
    viewMap.set(route.viewPath, {
      ...route,
      routes: [route.route]
    })
  }
}

const routes = Array.from(viewMap.values())

console.log('UI COMPLIANCE MATRIX')
console.log('====================\n')
console.log(`Routes found: ${routes.length}\n`)

const results = []

for (const route of routes) {
  try {
    const content = readFileSync(route.viewPath, 'utf8')
    
    // Check 1: Uses <PageHeader>
    const usesPageHeader = /<PageHeader/.test(content)
    
    // Check 2: Inline <h1> classes
    const h1Match = content.match(/<h1([^>]*)class="([^"]+)"/g)
    const h1Count = h1Match ? h1Match.length : 0
    const h1Classes = h1Match && h1Match[0] ? h1Match[0].match(/class="([^"]+)"/)?.[1] || '' : ''
    
    // Check 2b: h1 has text-xl (target text-2xl)
    const hasTextXlH1 = h1Classes.includes('text-xl')
    
    // Check 3: h1 has font-semibold
    const hasFontSemibold = h1Classes.includes('font-semibold')
    
    // Check 4: Subtitle class
    const subtitleMatch = content.match(/<p[^>]*class="([^"]*text-sm[^"]*text-ink-muted[^"]*)"/)
    const subtitleClass = subtitleMatch ? subtitleMatch[1].substring(0, 40) : ''
    
    // Check 4b: text-xs subtitle (target text-sm)
    const hasXsSubtitle = /<p[^>]*class="[^"]*text-xs[^"]*text-ink-muted/.test(content)
    
    // Check 4c: Inline subtitle leftover (when PageHeader is used)
    const hasInlineSubtitle = usesPageHeader && (/<p[^>]*class="[^"]*text-(?:xs|sm)[^"]*text-ink-muted/.test(content) && !content.includes('subtitle='))
    const inlineSubtitleLeftover = hasInlineSubtitle ? 'y' : 'n'
    
    // Check 5: Loading state
    const hasLoadingState = 
      content.includes('LoadingSkeleton') || 
      content.includes('BaseSkeleton') ||
      content.includes('skeleton') ||
      /isLoading|loading/.test(content)
    
    // Check 6: Empty state
    const hasEmptyState = 
      content.includes('<EmptyState') ||
      content.includes('EmptyState') ||
      /No (pending|documents|records|tasks|users|items) found|empty/i.test(content)
    
    // Check 7: Error state with retry
    const hasErrorRetry = 
      /retry|Try again|@click.*load|errorMessage.*BaseAlert/i.test(content)
    
    // Check 8: Tables in overflow-x-auto
    const hasTable = /<table/i.test(content) || /<DataTable/i.test(content) || /component.*Table/i.test(content)
    const hasOverflow = /overflow-x-auto/.test(content)
    const tablesWrapped = hasTable ? (hasOverflow ? 'y' : 'n') : 'none'
    
    // Check 9: Banned classes count
    const bannedPatterns = [
      /text-\[(?:[1-9]|1[01])px\]/g, // text-[1-11px] - arbitrary sizes below 12px
      /rounded-2xl/g,
      /shadow-(?:sm|md)(?!:)/g,
      /uppercase(?! tabular-nums| font-mono|[^"]*(?:font-mono|placeholder|input))/g, // whitelist uppercase for code/placeholder/input
      /tracking-(?:wide|wider|widest)/g,
      /border-l-4/g
    ]
    
    let bannedCount = 0
    for (const pattern of bannedPatterns) {
      const matches = content.match(pattern)
      if (matches) bannedCount += matches.length
    }
    
    results.push({
      view: route.viewPath.split('/').pop(),
      route: route.routes.length > 1 ? `${route.routes[0]} +${route.routes.length - 1} tabs` : route.route,
      usesPageHeader: usesPageHeader ? 'y' : 'n',
      h1Count: h1Count,
      h1Classes: h1Classes.substring(0, 40),
      hasFontSemibold: hasFontSemibold ? 'y' : 'n',
      hasTextXlH1: hasTextXlH1 ? 'y' : 'n',
      subtitleClass: subtitleClass,
      hasXsSubtitle: hasXsSubtitle ? 'y' : 'n',
      inlineSubtitleLeftover: inlineSubtitleLeftover,
      hasLoadingState: hasLoadingState ? 'y' : 'n',
      hasEmptyState: hasEmptyState ? 'y' : 'n',
      hasErrorRetry: hasErrorRetry ? 'y' : 'n',
      tablesWrapped: tablesWrapped,
      bannedCount: bannedCount
    })
    
  } catch (err) {
    results.push({
      view: route.viewPath.split('/').pop(),
      route: route.routes.length > 1 ? `${route.routes[0]} +${route.routes.length - 1} tabs` : route.route,
      usesPageHeader: 'ERR',
      h1Count: 0,
      h1Classes: 'FILE_NOT_FOUND',
      hasFontSemibold: 'n',
      hasTextXlH1: 'n',
      subtitleClass: '',
      hasXsSubtitle: 'n',
      inlineSubtitleLeftover: 'n',
      hasLoadingState: 'n',
      hasEmptyState: 'n',
      hasErrorRetry: 'n',
      tablesWrapped: 'none',
      bannedCount: 0
    })
  }
}

// Generate table
const header = 'view | route | PageHeader | h1 count | h1 classes | font-semibold | h1 text-xl | subtitle | subtitle text-xs | inline subtitle leftover | loading | empty | error+retry | tables wrapped | banned count'
const separator = '-'.repeat(header.length)

let table = `${header}\n${separator}\n`

for (const r of results) {
  const row = [
    r.view.padEnd(35),
    r.route.padEnd(30),
    r.usesPageHeader.padEnd(10),
    String(r.h1Count).padEnd(8),
    r.h1Classes.padEnd(40),
    r.hasFontSemibold.padEnd(13),
    r.hasTextXlH1.padEnd(10),
    r.subtitleClass.padEnd(30),
    r.hasXsSubtitle.padEnd(16),
    r.inlineSubtitleLeftover.padEnd(24),
    r.hasLoadingState.padEnd(7),
    r.hasEmptyState.padEnd(5),
    r.hasErrorRetry.padEnd(11),
    r.tablesWrapped.padEnd(14),
    String(r.bannedCount).padEnd(12)
  ].join(' | ')
  
  table += row + '\n'
}

console.log(table)

// Save to artifacts
mkdirSync(artifactsDir, { recursive: true })
const matrixPath = join(artifactsDir, 'ui-matrix.md')
const markdown = `# UI Compliance Matrix\n\nGenerated: ${new Date().toISOString()}\n\n\`\`\`\n${table}\`\`\`\n`
writeFileSync(matrixPath, markdown, 'utf8')

console.log(`\n✓ Matrix saved to ${matrixPath}`)

// Summary
const compliant = results.filter(r => 
  r.usesPageHeader === 'y' &&
  r.h1Count === 1 &&
  r.hasFontSemibold === 'y' && 
  r.hasTextXlH1 === 'n' &&
  r.hasXsSubtitle === 'n' &&
  r.inlineSubtitleLeftover === 'n' &&
  r.hasLoadingState === 'y' && 
  r.hasEmptyState === 'y' && 
  r.hasErrorRetry === 'y' &&
  r.bannedCount === 0 &&
  (r.tablesWrapped === 'y' || r.tablesWrapped === 'none')
).length

console.log(`\nCompliant: ${compliant}/${results.length}`)
console.log(`Needs work: ${results.length - compliant}`)
