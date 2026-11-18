#!/usr/bin/env node
/**
 * Restore corrupted files WITHOUT BOM
 */

import { execSync } from 'child_process'
import { readFileSync, writeFileSync, openSync, writeSync, closeSync } from 'fs'

const BASELINE = '7d086b3'

const corruptedFiles = [
  'client/src/components/Pagination.vue',
  'client/src/components/data-display/DataTable.vue',
  'client/src/layouts/AppLayout.vue',
  'client/src/views/dashboard/DashboardView.vue',
]

console.log('RESTORING CORRUPTED FILES (NO BOM)')
console.log('====================================\n')

for (const file of corruptedFiles) {
  console.log(`Processing: ${file}`)
  
  try {
    // Get baseline content
    const baselineContent = execSync(`git show ${BASELINE}:${file}`, { encoding: 'utf8' })
    
    // Write UTF-8 without BOM using low-level API
    const buffer = Buffer.from(baselineContent, 'utf8')
    const fd = openSync(file, 'w')
    writeSync(fd, buffer)
    closeSync(fd)
    
    console.log(`  ✓ Restored from baseline ${BASELINE}`)
    
    // Verify restoration
    const bytes = readFileSync(file)
    const hasBOM = bytes.length >= 3 && bytes[0] === 0xEF && bytes[1] === 0xBB && bytes[2] === 0xBF
    const content = readFileSync(file, 'utf8')
    const fffdCount = (content.match(/\uFFFD/g) || []).length
    
    if (hasBOM) {
      console.error(`  ✗ BOM still present! Trying alternative...`)
      // Strip BOM if present
      const stripped = bytes.slice(hasBOM ? 3 : 0)
      const fd2 = openSync(file, 'w')
      writeSync(fd2, stripped)
      closeSync(fd2)
      
      const verify = readFileSync(file)
      const verifyBOM = verify.length >= 3 && verify[0] === 0xEF && verify[1] === 0xBB && verify[2] === 0xBF
      console.log(`  ✓ BOM removed: ${verifyBOM ? 'FAILED' : 'SUCCESS'}`)
    } else {
      console.log(`  ✓ Verified: U+FFFD=${fffdCount}, BOM=NO`)
    }
    
  } catch (err) {
    console.error(`  ✗ Error: ${err.message}`)
  }
  
  console.log('')
}

console.log('Restoration complete.')
