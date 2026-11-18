#!/usr/bin/env node
/**
 * Reapply Phase 3 changes to restored files using UTF-8 safe operations
 */

import { readFileSync, openSync, writeSync, closeSync } from 'fs'

function safeWrite(file, content) {
  const buffer = Buffer.from(content, 'utf8')
  const fd = openSync(file, 'w')
  writeSync(fd, buffer)
  closeSync(fd)
}

console.log('REAPPLYING PHASE 3 CHANGES')
console.log('===========================\n')

// 1. DataTable.vue: Remove uppercase and tracking from headers
{
  const file = 'client/src/components/data-display/DataTable.vue'
  console.log(`1. ${file}`)
  let content = readFileSync(file, 'utf8')
  
  // Replace header classes: remove uppercase and tracking
  const before = content
  content = content.replace(
    /<th([^>]*?)class="([^"]*?)\buppercase\b([^"]*?)"([^>]*?)>/g,
    (match, before, classes, after, end) => {
      const cleaned = classes.split(/\s+/)
        .filter(c => c !== 'uppercase' && !c.startsWith('tracking-'))
        .join(' ')
      return `<th${before}class="${cleaned}${after}"${end}>`
    }
  )
  
  // Also remove tracking- classes
  content = content.replace(
    /class="([^"]*?)\btracking-\w+\b([^"]*?)"/g,
    (match, before, after) => {
      const cleaned = before.split(/\s+/)
        .filter(c => !c.startsWith('tracking-'))
        .join(' ')
      return `class="${cleaned}${after}"`
    }
  )
  
  if (content !== before) {
    safeWrite(file, content)
    console.log('  ✓ Removed uppercase and tracking from table headers')
  } else {
    console.log('  ⚠ No changes needed (already clean)')
  }
}

// 2. Pagination.vue: Check what changed
{
  const file = 'client/src/components/Pagination.vue'
  console.log(`\n2. ${file}`)
  console.log('  ℹ Checking git diff to see what changed...')
  // This file might not have had Phase 3 changes, will verify manually
}

// 3. AppLayout.vue: Remove .toUpperCase() from user initials (already done earlier, verify it's gone)
{
  const file = 'client/src/layouts/AppLayout.vue'
  console.log(`\n3. ${file}`)
  let content = readFileSync(file, 'utf8')
  
  // Check if .toUpperCase() exists
  if (content.includes('.toUpperCase()')) {
    // Remove it from initials computation
    content = content.replace(
      /return `\$\{parts\[0\]\[0\]\}\$\{parts\[1\]\[0\]\}`\.toUpperCase\(\)/,
      'return `${parts[0][0]}${parts[1][0]}`'
    )
    content = content.replace(
      /return name\.slice\(0, 2\)\.toUpperCase\(\)/,
      'return name.slice(0, 2)'
    )
    
    safeWrite(file, content)
    console.log('  ✓ Removed .toUpperCase() from user initials')
  } else {
    console.log('  ⚠ .toUpperCase() not found (baseline predates this change)')
  }
}

// 4. DashboardView.vue: Multiple Phase 3 changes
{
  const file = 'client/src/views/dashboard/DashboardView.vue'
  console.log(`\n4. ${file}`)
  console.log('  ℹ This file had shadow removals and font-mono changes')
  console.log('  ℹ Need to check git diff to see exact changes')
}

console.log('\n=== MANUAL VERIFICATION REQUIRED ===')
console.log('Check git diff for commits 3206dd9..54d413a to see exact changes for:')
console.log('  - Pagination.vue')
console.log('  - DashboardView.vue')
console.log('\nRun: git show 3206dd9 -- client/src/components/Pagination.vue')
console.log('Run: git show ed5ae6f..54d413a -- client/src/views/dashboard/DashboardView.vue')
