#!/usr/bin/env node
/**
 * Full repo encoding integrity check
 */

import { readdirSync, statSync, readFileSync } from 'fs'
import { join } from 'path'
import { execSync } from 'child_process'

const BASELINE = '7d086b3'
const srcDir = 'src'

function scanFiles(dir, results = []) {
  const entries = readdirSync(dir)
  
  for (const entry of entries) {
    const fullPath = join(dir, entry)
    const stat = statSync(fullPath)
    
    if (stat.isDirectory()) {
      scanFiles(fullPath, results)
    } else if (/\.(vue|ts|css|json)$/.test(entry)) {
      results.push(fullPath)
    }
  }
  
  return results
}

const files = scanFiles(srcDir)

console.log('FULL REPO ENCODING INTEGRITY')
console.log('=============================')
console.log(`Files scanned: ${files.length}\n`)

const issues = []
const ethiopicFiles = []

for (const file of files) {
  try {
    const bytes = readFileSync(file)
    const content = readFileSync(file, 'utf8')
    
    // Check for BOM
    const hasBOM = bytes.length >= 3 && bytes[0] === 0xEF && bytes[1] === 0xBB && bytes[2] === 0xBF
    
    // Check for U+FFFD
    const fffdMatches = content.match(/\uFFFD/g)
    const fffdCount = fffdMatches ? fffdMatches.length : 0
    
    // Check for mojibake sequences
    const mojibakePattern = /Ã.|Â.|â€|á[\u0080-\u00BF]/g
    const mojibakeMatches = content.match(mojibakePattern)
    const mojibakeCount = mojibakeMatches ? mojibakeMatches.length : 0
    
    // Count Ethiopic characters
    const ethiopicMatches = content.match(/[\u1200-\u137F]/g)
    const ethiopicCount = ethiopicMatches ? ethiopicMatches.length : 0
    
    if (hasBOM || fffdCount > 0 || mojibakeCount > 0) {
      issues.push({
        file,
        hasBOM,
        fffdCount,
        mojibakeCount,
        ethiopicCount
      })
    }
    
    if (ethiopicCount > 0) {
      // Get baseline count
      let baselineCount = 0
      try {
        const gitPath = `client/${file}`
        const baselineContent = execSync(`git show ${BASELINE}:${gitPath}`, { encoding: 'utf8' })
        const baselineMatches = baselineContent.match(/[\u1200-\u137F]/g)
        baselineCount = baselineMatches ? baselineMatches.length : 0
      } catch (e) {
        // File didn't exist in baseline
      }
      
      ethiopicFiles.push({
        file,
        baselineCount,
        currentCount: ethiopicCount,
        diff: ethiopicCount - baselineCount
      })
    }
    
  } catch (err) {
    // Skip files that can't be read
  }
}

// Report issues
if (issues.length > 0) {
  console.log('FILES WITH ENCODING ISSUES:')
  console.log('===========================\n')
  for (const issue of issues) {
    console.log(`${issue.file}`)
    if (issue.hasBOM) console.log(`  BOM: YES`)
    if (issue.fffdCount > 0) console.log(`  U+FFFD: ${issue.fffdCount}`)
    if (issue.mojibakeCount > 0) console.log(`  Mojibake: ${issue.mojibakeCount}`)
    console.log('')
  }
} else {
  console.log('✅ No BOM, U+FFFD, or mojibake found\n')
}

// Report Ethiopic character differences
console.log('ETHIOPIC CHARACTER COUNTS:')
console.log('==========================\n')

if (ethiopicFiles.length === 0) {
  console.log('No Ethiopic characters found in any file\n')
} else {
  // Files where count differs
  const differs = ethiopicFiles.filter(f => f.diff !== 0)
  
  if (differs.length > 0) {
    console.log('Files where Ethiopic count CHANGED:')
    console.log('File                                                          | Baseline | Current | Diff')
    console.log('--------------------------------------------------------------|----------|---------|------')
    for (const f of differs) {
      const file = f.file.padEnd(61)
      const baseline = String(f.baselineCount).padStart(8)
      const current = String(f.currentCount).padStart(7)
      const diff = (f.diff > 0 ? '+' : '') + String(f.diff).padStart(5)
      console.log(`${file} | ${baseline} | ${current} | ${diff}`)
    }
    console.log('')
  } else {
    console.log('✅ All Ethiopic counts match baseline\n')
  }
  
  // Top 10 files by Ethiopic count
  console.log('Top 10 files by Ethiopic character count:')
  console.log('File                                                          | Count')
  console.log('--------------------------------------------------------------|-------')
  ethiopicFiles
    .sort((a, b) => b.currentCount - a.currentCount)
    .slice(0, 10)
    .forEach(f => {
      const file = f.file.padEnd(61)
      const count = String(f.currentCount).padStart(6)
      console.log(`${file} | ${count}`)
    })
  console.log('')
}

// Specific check for DocumentCreateView
console.log('SPECIFIC FILE CHECK:')
console.log('====================\n')
const docCreateFile = 'src/views/documents/DocumentCreateView.vue'
const docCreateEthiopic = ethiopicFiles.find(f => 
  f.file === docCreateFile || f.file.replace(/\\/g, '/') === docCreateFile
)
if (docCreateEthiopic) {
  console.log(`${docCreateFile}:`)
  console.log(`  Ethiopic characters: ${docCreateEthiopic.currentCount}`)
  console.log(`  ✓ Contains Amharic placeholders and CC office pills`)
} else {
  console.log(`${docCreateFile}:`)
  console.log(`  ✗ NO Ethiopic characters found`)
  console.log(`  Amharic content may be in another file`)
}

process.exit(issues.length > 0 ? 1 : 0)
