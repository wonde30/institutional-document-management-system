#!/usr/bin/env node
/**
 * Restore corrupted files from git baseline and reapply class changes
 */

import { execSync } from 'child_process'
import { readFileSync, writeFileSync } from 'fs'

const BASELINE = '7d086b3'

const corruptedFiles = [
  'client/src/components/Pagination.vue',
  'client/src/components/data-display/DataTable.vue',
  'client/src/layouts/AppLayout.vue',
  'client/src/views/dashboard/DashboardView.vue',
]

console.log('RESTORING CORRUPTED FILES')
console.log('=========================\n')

for (const file of corruptedFiles) {
  console.log(`Processing: ${file}`)
  
  try {
    // Get baseline content
    const baselineContent = execSync(`git show ${BASELINE}:${file}`, { encoding: 'utf8' })
    
    // Write back as UTF-8 without BOM
    writeFileSync(file, baselineContent, { encoding: 'utf8' })
    
    console.log(`  ✓ Restored from baseline ${BASELINE}`)
    
    // Verify restoration
    const restored = readFileSync(file, 'utf8')
    const fffdCount = (restored.match(/\uFFFD/g) || []).length
    const bytes = readFileSync(file)
    const hasBOM = bytes.length >= 3 && bytes[0] === 0xEF && bytes[1] === 0xBB && bytes[2] === 0xBF
    
    console.log(`  ✓ Verified: U+FFFD=${fffdCount}, BOM=${hasBOM ? 'YES (ERROR)' : 'NO'}`)
    
  } catch (err) {
    console.error(`  ✗ Error: ${err.message}`)
  }
  
  console.log('')
}

console.log('Restoration complete. Files are now at baseline state.')
console.log('Phase 3 class changes must be reapplied manually or via new commits.')
