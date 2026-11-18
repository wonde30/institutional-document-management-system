#!/usr/bin/env node
/**
 * Restore files from 54d413a (last good commit before corruption)
 * and reapply ONLY allowed changes:
 * - Remove corrupted characters
 * - Remove .toUpperCase() from initials
 * - Add formatStatus
 * - font-mono → tabular-nums
 * - Add uppercase class to initials
 */

import { execSync } from 'child_process'
import { readFileSync, writeFileSync } from 'fs'

const COMMIT = '54d413a'

const files = [
  'client/src/layouts/AppLayout.vue',
  'client/src/views/dashboard/DashboardView.vue',
  'client/src/components/data-display/DataTable.vue',
  'client/src/components/Pagination.vue'
]

console.log('RESTORING FROM 54d413a')
console.log('======================\n')

for (const file of files) {
  console.log(`Processing: ${file}`)
  
  try {
    // Checkout from git
    execSync(`git checkout ${COMMIT} -- "${file}"`, { stdio: 'inherit' })
    console.log(`  ✓ Checked out from ${COMMIT}`)
    
    // Read file
    let content = readFileSync(file, 'utf8')
    const originalLength = content.length
    
    // Apply fixes based on file
    if (file.includes('AppLayout.vue')) {
      // 1. Remove .toUpperCase() from initials
      content = content.replace(
        /return `\$\{parts\[0\]\[0\]\}\$\{parts\[1\]\[0\]\}`\.toUpperCase\(\)/g,
        'return `${parts[0][0]}${parts[1][0]}`'
      )
      content = content.replace(
        /return name\.slice\(0, 2\)\.toUpperCase\(\)/g,
        'return name.slice(0, 2)'
      )
      
      // 2. Add uppercase class to avatar span (find the span with userInitials)
      content = content.replace(
        /(class="w-7 h-7 rounded-full bg-primary-subtle text-primary font-bold text-xs flex items-center justify-center border border-primary\/20)"([^>]*>\s*\{\{ userInitials \}\})/,
        '$1 uppercase"$2'
      )
      
      // 3. Fix corrupted em-dashes in theme tooltips
      content = content.replace(/�/g, '—')
      
      console.log('  ✓ Applied: removed .toUpperCase(), added uppercase class, fixed em-dashes')
    }
    
    if (file.includes('DashboardView.vue')) {
      // 1. Fix corrupted middle dots
      content = content.replace(/�/g, '·')
      
      // 2. Change font-mono to tabular-nums for correspondence_date
      content = content.replace(
        /<span class="text-xs text-ink-faint shrink-0 font-mono">\{\{ corr\.correspondence_date \}\}<\/span>/g,
        '<span class="text-xs text-ink-faint shrink-0 tabular-nums">{{ corr.correspondence_date }}</span>'
      )
      
      console.log('  ✓ Applied: fixed middle dots, font-mono → tabular-nums')
    }
    
    if (file.includes('DataTable.vue')) {
      // Fix corrupted em-dash in default cell value
      content = content.replace(/�/g, '—')
      
      console.log('  ✓ Applied: fixed em-dash')
    }
    
    if (file.includes('Pagination.vue')) {
      // Fix all corrupted characters
      content = content.replace(/�/g, (match, offset) => {
        // Determine what it should be based on context
        const before = content.substring(Math.max(0, offset - 10), offset)
        const after = content.substring(offset + 1, Math.min(content.length, offset + 10))
        
        // En-dash between numbers
        if (/\d/.test(before) && /\d/.test(after)) return '–'
        // Left angle bracket before "Prev"
        if (after.includes('Prev')) return '‹'
        // Right angle bracket after "Next"
        if (before.includes('Next')) return '›'
        // Ellipsis in pagination
        if (before.includes('aria-hidden')) return '…'
        
        return '—' // default to em-dash
      })
      
      console.log('  ✓ Applied: fixed all corrupted characters')
    }
    
    // Write back
    writeFileSync(file, content, { encoding: 'utf8' })
    
    console.log(`  ✓ Saved (${originalLength} → ${content.length} bytes)\n`)
    
  } catch (err) {
    console.error(`  ✗ Error: ${err.message}\n`)
  }
}

console.log('✅ Restoration complete')
console.log('\nNow verify the files have correct content...')
