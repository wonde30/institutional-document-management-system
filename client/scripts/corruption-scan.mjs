#!/usr/bin/env node
/**
 * UTF-8 / Amharic Corruption Scanner
 * Compares baseline vs current for all changed files
 */

import { execSync } from 'child_process'
import { readFileSync } from 'fs'

const BASELINE = '7d086b3' // WIP: Initial design system updates (pre-Phase 1)

// Get all changed files
const changedFiles = execSync(`git diff --name-only ${BASELINE}..HEAD`, { encoding: 'utf8' })
  .split('\n')
  .filter(f => f.trim() && (f.endsWith('.vue') || f.endsWith('.ts')))

console.log('UTF-8 / AMHARIC CORRUPTION SCAN')
console.log('================================')
console.log(`Baseline: ${BASELINE}`)
console.log(`Changed files: ${changedFiles.length}`)
console.log('')

const results = []
let corruptedCount = 0

for (const file of changedFiles) {
  try {
    // Read baseline version
    let baselineContent = ''
    try {
      baselineContent = execSync(`git show ${BASELINE}:${file}`, { encoding: 'utf8' })
    } catch (e) {
      baselineContent = '' // file didn't exist in baseline
    }

    // Read current version
    let currentContent = ''
    try {
      currentContent = readFileSync(file, 'utf8')
    } catch (e) {
      continue // file was deleted
    }

    // Check for BOM
    const bytes = readFileSync(file)
    const hasBOM = bytes.length >= 3 && bytes[0] === 0xEF && bytes[1] === 0xBB && bytes[2] === 0xBF

    // Count Ethiopic characters
    const baselineAmharic = (baselineContent.match(/[\u1200-\u137F]/g) || []).length
    const currentAmharic = (currentContent.match(/[\u1200-\u137F]/g) || []).length

    // Check for replacement characters
    const replacementChars = (currentContent.match(/\uFFFD/g) || []).length

    const isCorrupted = replacementChars > 0 || currentAmharic < baselineAmharic

    if (isCorrupted) {
      corruptedCount++
    }

    results.push({
      file,
      baselineAmharic,
      currentAmharic,
      hasBOM,
      replacementChars,
      isCorrupted
    })
  } catch (err) {
    console.error(`Error processing ${file}: ${err.message}`)
  }
}

// Print table
console.log('File                                                  | Baseline | Current | BOM | U+FFFD | Status')
console.log('---------------------------------------------------------------------------------------------------')

for (const r of results) {
  const status = r.isCorrupted ? 'CORRUPTED' : 'OK'
  const statusColor = r.isCorrupted ? '\x1b[31m' : '\x1b[32m'
  const resetColor = '\x1b[0m'
  
  const file = r.file.padEnd(52)
  const baseline = String(r.baselineAmharic).padStart(8)
  const current = String(r.currentAmharic).padStart(7)
  const bom = (r.hasBOM ? 'YES' : 'NO').padStart(3)
  const fffd = String(r.replacementChars).padStart(6)
  
  console.log(`${file} | ${baseline} | ${current} | ${bom} | ${fffd} | ${statusColor}${status}${resetColor}`)
}

console.log('')
console.log(`Total files scanned: ${results.length}`)
console.log(`Corrupted files: ${corruptedCount}`)

if (corruptedCount > 0) {
  console.log('')
  console.log('CORRUPTED FILES:')
  results.filter(r => r.isCorrupted).forEach(r => {
    console.log(`  - ${r.file} (Amharic: ${r.baselineAmharic}→${r.currentAmharic}, U+FFFD: ${r.replacementChars})`)
  })
  process.exit(1)
}
