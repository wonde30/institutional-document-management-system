#!/usr/bin/env node
/**
 * Apply ALL Phase 3 fixes to restored baseline files
 * Using UTF-8 safe operations (no BOM)
 */

import { readFileSync, openSync, writeSync, closeSync } from 'fs'

function safeWrite(file, content) {
  const buffer = Buffer.from(content, 'utf8')
  const fd = openSync(file, 'w')
  writeSync(fd, buffer)
  closeSync(fd)
}

function verifyWrite(file) {
  const bytes = readFileSync(file)
  const hasBOM = bytes.length >= 3 && bytes[0] === 0xEF && bytes[1] === 0xBB && bytes[2] === 0xBF
  const content = readFileSync(file, 'utf8')
  const fffd = (content.match(/\uFFFD/g) || []).length
  return { hasBOM, fffd }
}

console.log('APPLYING PHASE 3 FIXES')
console.log('=======================\n')

// 1. DataTable.vue: Remove uppercase and tracking from ALL headers
{
  const file = 'client/src/components/data-display/DataTable.vue'
  console.log(`1. ${file}`)
  let content = readFileSync(file, 'utf8')
  
  // Remove uppercase class
  content = content.replace(/\buppercase\b/g, '')
  // Remove tracking classes
  content = content.replace(/\btracking-wider\b/g, '')
  content = content.replace(/\btracking-wide\b/g, '')
  // Clean up extra spaces
  content = content.replace(/class="([^"]*?)  +([^"]*?)"/g, 'class="$1 $2"')
  content = content.replace(/class="  +/g, 'class="')
  content = content.replace(/  +"/g, '"')
  
  safeWrite(file, content)
  const check = verifyWrite(file)
  console.log(`  ✓ Removed uppercase/tracking from headers (BOM=${check.hasBOM}, U+FFFD=${check.fffd})`)
}

// 2. Pagination.vue: Remove shadow-xs (commit 0abe7f2)
{
  const file = 'client/src/components/Pagination.vue'
  console.log(`\n2. ${file}`)
  let content = readFileSync(file, 'utf8')
  
  // Remove shadow-xs class
  const before = content
  content = content.replace(/\bshadow-xs\b/g, '')
  // Clean up spaces
  content = content.replace(/class="([^"]*?)  +([^"]*?)"/g, 'class="$1 $2"')
  content = content.replace(/class="  +/g, 'class="')
  content = content.replace(/  +"/g, '"')
  
  if (content !== before) {
    safeWrite(file, content)
    const check = verifyWrite(file)
    console.log(`  ✓ Removed shadow-xs (BOM=${check.hasBOM}, U+FFFD=${check.fffd})`)
  } else {
    console.log('  ⚠ No shadow-xs found')
  }
}

// 3. AppLayout.vue: Keep .toUpperCase() - it's needed for initials
{
  const file = 'client/src/layouts/AppLayout.vue'
  console.log(`\n3. ${file}`)
  
  // AppLayout in baseline (7d086b3) predates the unit switcher work
  // The current version already has the correct structure from 896e4d4
  // Just verify integrity
  const check = verifyWrite(file)
  console.log(`  ✓ Verified integrity (BOM=${check.hasBOM}, U+FFFD=${check.fffd})`)
}

// 4. DashboardView.vue: font-mono → tabular-nums for correspondence_date
{
  const file = 'client/src/views/dashboard/DashboardView.vue'
  console.log(`\n4. ${file}`)
  let content = readFileSync(file, 'utf8')
  
  // Find and replace the specific line
  content = content.replace(
    /<span class="text-xs text-ink-faint shrink-0 font-mono">\{\{ corr\.correspondence_date \}\}<\/span>/,
    '<span class="text-xs text-ink-faint shrink-0 tabular-nums">{{ corr.correspondence_date }}</span>'
  )
  
  safeWrite(file, content)
  const check = verifyWrite(file)
  console.log(`  ✓ Changed font-mono to tabular-nums (BOM=${check.hasBOM}, U+FFFD=${check.fffd})`)
}

console.log('\n=== FINAL VERIFICATION ===')
const files = [
  'client/src/components/data-display/DataTable.vue',
  'client/src/components/Pagination.vue',
  'client/src/layouts/AppLayout.vue',
  'client/src/views/dashboard/DashboardView.vue'
]

let allClean = true
for (const file of files) {
  const check = verifyWrite(file)
  const status = (!check.hasBOM && check.fffd === 0) ? '✓ CLEAN' : '✗ CORRUPTED'
  const color = (!check.hasBOM && check.fffd === 0) ? '' : '\x1b[31m'
  const reset = '\x1b[0m'
  console.log(`${color}${file}: ${status}${reset}`)
  if (check.hasBOM || check.fffd > 0) {
    allClean = false
    console.log(`  BOM: ${check.hasBOM}, U+FFFD: ${check.fffd}`)
  }
}

if (allClean) {
  console.log('\n✅ All files are UTF-8 clean (no BOM, no U+FFFD)')
} else {
  console.log('\n❌ Some files still have issues')
  process.exit(1)
}
