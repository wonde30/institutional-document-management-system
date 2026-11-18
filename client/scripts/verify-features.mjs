#!/usr/bin/env node
/**
 * Verify key features exist in restored files
 */

import { readFileSync } from 'fs'

console.log('FEATURE VERIFICATION')
console.log('====================\n')

// AppLayout checks
{
  const file = 'client/src/layouts/AppLayout.vue'
  const content = readFileSync(file, 'utf8')
  const lines = content.split('\n')
  
  console.log('AppLayout.vue:')
  
  // 1. No org pill
  const hasOrgPill = content.includes('organization-pill') || /class="[^"]*pill[^"]*org/i.test(content)
  console.log(`  1. No org pill: ${hasOrgPill ? '✗ FOUND (bad)' : '✓ OK'}`)
  
  // 2. Menu toggle uses xl:hidden
  const menuToggleLine = lines.findIndex(l => l.includes('MenuIcon') || (l.includes('button') && l.includes('sidebar')))
  const hasXlHidden = menuToggleLine >= 0 && lines[menuToggleLine].includes('xl:hidden')
  console.log(`  2. Menu toggle xl:hidden: ${hasXlHidden ? '✓ OK' : '✗ MISSING'}`)
  
  // 3. Avatar menu has working unit select
  const hasUnitSelect = content.includes('<select') && content.includes('handleUnitChange') && content.includes('availableUnits')
  console.log(`  3. Unit select in avatar: ${hasUnitSelect ? '✓ OK' : '✗ MISSING'}`)
  
  // 4. Initials uppercase
  const initialsLine = lines.findIndex(l => l.includes('userInitials') && l.includes('span'))
  const hasUppercase = initialsLine >= 0 && lines[initialsLine].includes('uppercase')
  console.log(`  4. Initials uppercase class: ${hasUppercase ? '✓ OK' : '✗ MISSING'}`)
  
  console.log('')
}

// DashboardView checks
{
  const file = 'client/src/views/dashboard/DashboardView.vue'
  const content = readFileSync(file, 'utf8')
  const lines = content.split('\n')
  
  console.log('DashboardView.vue:')
  
  // 1. h1 "Workspace" font-semibold
  const h1Line = lines.findIndex(l => l.includes('<h1') && l.includes('Workspace'))
  const h1HasSemibold = h1Line >= 0 && lines[h1Line].includes('font-semibold')
  const h1HasBold = h1Line >= 0 && lines[h1Line].includes('font-bold')
  console.log(`  1. h1 "Workspace" font-semibold: ${h1HasSemibold ? '✓ OK' : h1HasBold ? '✗ font-bold (wrong)' : '✗ MISSING'}`)
  if (h1Line >= 0) {
    console.log(`     Line ${h1Line + 1}: ${lines[h1Line].trim().substring(0, 80)}`)
  }
  
  // 2. 4 KPI cards without icons
  const kpiSection = content.substring(content.indexOf('Core KPI Grid') || content.indexOf('grid grid-cols-2'), content.indexOf('Office overview'))
  const kpiHasIcons = /<svg|<FileText|<Mail|<Archive|<CheckCircle/i.test(kpiSection)
  console.log(`  2. 4 KPI cards without icons: ${kpiHasIcons ? '✗ HAS ICONS (wrong)' : '✓ OK'}`)
  
  // 3. ONE "Office overview" card
  const officeOverviewCount = (content.match(/Office overview/g) || []).length
  console.log(`  3. ONE "Office overview" card: ${officeOverviewCount === 1 ? '✓ OK' : `✗ ${officeOverviewCount} found`}`)
  
  // 4. Quick actions use <router-link>
  const quickActionsStart = content.indexOf('Quick actions')
  const quickActionsSection = content.substring(quickActionsStart, quickActionsStart + 2000)
  const hasRouterLink = quickActionsSection.includes('<router-link')
  const hasButtonWithClick = quickActionsSection.includes('<button') && quickActionsSection.includes('@click=')
  console.log(`  4. Quick actions <router-link>: ${hasRouterLink ? '✓ OK' : hasButtonWithClick ? '✗ Uses buttons (wrong)' : '? NOT FOUND'}`)
  
  // 5. Empty state "No pending tasks" without filled circle or "All clear"
  const emptyStateSection = content.substring(content.indexOf('Empty state') || content.indexOf('tasks.length === 0'), content.indexOf('Recent approved'))
  const hasAllClear = emptyStateSection.includes('All clear')
  const hasFilledCircle = emptyStateSection.includes('fill=') && !emptyStateSection.includes('fill="none"')
  const hasNoPendingTasks = emptyStateSection.includes('No pending tasks')
  console.log(`  5. Empty state: ${hasNoPendingTasks && !hasAllClear && !hasFilledCircle ? '✓ OK' : '✗ WRONG'}`)
  console.log(`     - "No pending tasks": ${hasNoPendingTasks ? '✓' : '✗'}`)
  console.log(`     - No "All clear": ${!hasAllClear ? '✓' : '✗'}`)
  console.log(`     - No filled circle: ${!hasFilledCircle ? '✓' : '✗'}`)
  
  console.log('')
}

// DataTable checks
{
  const file = 'client/src/components/data-display/DataTable.vue'
  const content = readFileSync(file, 'utf8')
  const lines = content.split('\n')
  
  console.log('DataTable.vue:')
  
  // Find thead th class
  const thLine = lines.findIndex(l => l.includes('<th') && l.includes('col') && !l.includes('v-if') && l.includes('px-3.5'))
  if (thLine >= 0) {
    const thClass = lines[thLine].match(/class="([^"]+)"/)?.[1] || ''
    const hasTextXs = thClass.includes('text-xs')
    const hasFontMedium = thClass.includes('font-medium')
    const hasTextInkMuted = thClass.includes('text-ink-muted')
    const hasUppercase = thClass.includes('uppercase')
    const hasTracking = thClass.includes('tracking-')
    
    console.log(`  th classes: ${thClass}`)
    console.log(`    - text-xs: ${hasTextXs ? '✓' : '✗'}`)
    console.log(`    - font-medium: ${hasFontMedium ? '✓' : '✗'}`)
    console.log(`    - text-ink-muted: ${hasTextInkMuted ? '✓' : '✗'}`)
    console.log(`    - No uppercase: ${!hasUppercase ? '✓' : '✗'}`)
    console.log(`    - No tracking: ${!hasTracking ? '✓' : '✗'}`)
  } else {
    console.log('  ✗ Could not find th element')
  }
  
  console.log('')
}

console.log('✅ Verification complete')
