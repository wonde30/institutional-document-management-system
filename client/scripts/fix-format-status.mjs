#!/usr/bin/env node
/**
 * Fix DocumentDetailView to use formatStatus for retention_requirement and decisionType
 * Fix AppLayout to keep initials uppercase
 */

import { readFileSync, openSync, writeSync, closeSync } from 'fs'

function safeWrite(file, content) {
  const buffer = Buffer.from(content, 'utf8')
  const fd = openSync(file, 'w')
  writeSync(fd, buffer)
  closeSync(fd)
}

console.log('FIXING FORMAT STATUS CALLS')
console.log('===========================\n')

// 1. DocumentDetailView: retention_requirement → formatStatus
{
  const file = 'client/src/views/documents/DocumentDetailView.vue'
  console.log(`1. ${file} - retention_requirement`)
  let content = readFileSync(file, 'utf8')
  
  // Replace raw retention_requirement with formatStatus call
  content = content.replace(
    /Retention: \{\{ orig\.retention_requirement \}\}/,
    'Retention: {{ formatStatus(orig.retention_requirement) }}'
  )
  
  safeWrite(file, content)
  console.log('  ✓ Changed to formatStatus(orig.retention_requirement)')
}

// 2. DocumentDetailView: decisionType → formatStatus
{
  const file = 'client/src/views/documents/DocumentDetailView.vue'
  console.log(`\n2. ${file} - decisionType`)
  let content = readFileSync(file, 'utf8')
  
  // Find decisionType usages
  const matches = content.match(/decisionType(?!\.)/g) || []
  console.log(`  Found ${matches.length} raw decisionType usages`)
  
  // Replace in title
  content = content.replace(
    /:title="`Execute Decision: \$\{decisionType\}`"/,
    ':title="`Execute Decision: ${formatStatus(decisionType)}`"'
  )
  
  // Replace in button text
  content = content.replace(
    /Confirm \{\{ decisionType \}\}/g,
    'Confirm {{ formatStatus(decisionType) }}'
  )
  
  safeWrite(file, content)
  console.log('  ✓ Changed to formatStatus(decisionType) in title and button')
}

// 3. AppLayout: Keep initials uppercase (add CSS class)
{
  const file = 'client/src/layouts/AppLayout.vue'
  console.log(`\n3. ${file} - user initials`)
  let content = readFileSync(file, 'utf8')
  
  // Find the avatar initials div
  if (content.includes('{{ userInitials }}')) {
    // Add uppercase class to the div
    content = content.replace(
      /(<div[^>]*class="[^"]*)(h-9 w-9[^"]*"[^>]*>\s*\{\{ userInitials \}\})/,
      '$1h-9 w-9$2'
    )
    
    // Better approach: add text-transform to the specific element
    // Find: <div class="...">{{ userInitials }}</div>
    const avatarPattern = /(<div[^>]*class="[^"]*flex items-center justify-center[^"]*)"([^>]*>\s*\{\{ userInitials \}\})/
    if (avatarPattern.test(content)) {
      content = content.replace(
        avatarPattern,
        '$1 uppercase"$2'
      )
      console.log('  ✓ Added uppercase class to avatar initials')
    } else {
      console.log('  ⚠ Could not find avatar pattern, checking alternative...')
      
      // Alternative: Add uppercase to any div with userInitials
      content = content.replace(
        /(<div[^>]*class="[^"]*)"([^>]*>\s*\{\{ userInitials \}\})/,
        (match, before, after) => {
          if (!before.includes('uppercase')) {
            return `${before} uppercase"${after}`
          }
          return match
        }
      )
      console.log('  ✓ Added uppercase class via alternative pattern')
    }
    
    safeWrite(file, content)
  } else {
    console.log('  ℹ userInitials not found in template')
  }
}

console.log('\n✅ All fixes applied')
