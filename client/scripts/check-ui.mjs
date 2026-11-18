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

const routes = []
let match
while ((match = routeRegex.exec(routerContent)) !== null) {
  routes.push({
    route: match[1],
    name: match[2],
    viewPath: `src/views/${match[3]}`
  })
}

console.log('UI COMPLIANCE MATRIX')
console.log('====================\n')
console.log(`Routes found: ${routes.length}\n`)

const results = []

for (const route of routes) {
  try {
    const content = readFileSync(route.viewPath, 'utf8')
    
    // Check 1: Uses <PageHeader>
    const usesPageHeader = content.includes('<PageHeader') || content.includes('PageHeader')
    
    // Check 2: Inline <h1> classes
    const h1Match = content.match(/<h1([^>]*)class="([^"]+)"/)
    const h1Classes = h1Match ? h1Match[2] : ''
    
    // Check 3: h1 has font-semibold
    const hasFontSemibold = h1Classes.includes('font-semibold')
    
    // Check 4: Subtitle class
    const subtitleMatch = content.match(/<p[^>]*class="([^"]*text-sm[^"]*text-ink-muted[^"]*)"/)
    const subtitleClass = subtitleMatch ? subtitleMatch[1].substring(0, 40) : ''
    
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
    const tableMatches = (content.match(/<table/g) || []).length
    const overflowMatches = (content.match(/overflow-x-auto/g) || []).length
    const tablesWrapped = tableMatches > 0 ? (overflowMatches >= tableMatches ? 'y' : 'n') : 'n/a'
    
    // Check 9: Banned classes count
    const bannedPatterns = [
      /text-\[(?:9|10|11|13)px\]/g,
      /rounded-2xl/g,
      /shadow-(?:sm|md)(?!:)/g,
      /uppercase(?! tabular-nums| font-mono)/g,
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
      route: route.route,
      usesPageHeader: usesPageHeader ? 'y' : 'n',
      h1Classes: h1Classes.substring(0, 40),
      hasFontSemibold: hasFontSemibold ? 'y' : 'n',
      subtitleClass: subtitleClass,
      hasLoadingState: hasLoadingState ? 'y' : 'n',
      hasEmptyState: hasEmptyState ? 'y' : 'n',
      hasErrorRetry: hasErrorRetry ? 'y' : 'n',
      tablesWrapped: tablesWrapped,
      bannedCount: bannedCount
    })
    
  } catch (err) {
    results.push({
      view: route.viewPath.split('/').pop(),
      route: route.route,
      usesPageHeader: 'ERR',
      h1Classes: 'FILE_NOT_FOUND',
      hasFontSemibold: 'n',
      subtitleClass: '',
      hasLoadingState: 'n',
      hasEmptyState: 'n',
      hasErrorRetry: 'n',
      tablesWrapped: 'n/a',
      bannedCount: 0
    })
  }
}

// Generate table
const header = 'view | route | PageHeader | h1 classes | font-semibold | subtitle | loading | empty | error+retry | tables wrapped | banned count'
const separator = '-'.repeat(header.length)

let table = `${header}\n${separator}\n`

for (const r of results) {
  const row = [
    r.view.padEnd(35),
    r.route.padEnd(30),
    r.usesPageHeader.padEnd(10),
    r.h1Classes.padEnd(40),
    r.hasFontSemibold.padEnd(13),
    r.subtitleClass.padEnd(30),
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
  r.hasFontSemibold === 'y' && 
  r.hasLoadingState === 'y' && 
  r.hasEmptyState === 'y' && 
  r.hasErrorRetry === 'y' &&
  r.bannedCount === 0 &&
  (r.tablesWrapped === 'y' || r.tablesWrapped === 'n/a')
).length

console.log(`\nCompliant: ${compliant}/${results.length}`)
console.log(`Needs work: ${results.length - compliant}`)
