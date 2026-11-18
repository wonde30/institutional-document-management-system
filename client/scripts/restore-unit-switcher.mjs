#!/usr/bin/env node
/**
 * Restore unit switcher to AppLayout
 */

import { readFileSync, openSync, writeSync, closeSync } from 'fs'

const file = 'client/src/layouts/AppLayout.vue'
let content = readFileSync(file, 'utf8')

// 1. Add handleUnitChange function after handleClickOutside
const handleUnitChangeFunc = `
/**
 * Handle organizational unit switch.
 * The server validates the selection and recalculates the capability set.
 */
async function handleUnitChange(event: Event) {
  const target = event.target as HTMLSelectElement
  const unitId = Number(target.value)

  if (!Number.isFinite(unitId) || unitId === authStore.activeUnitId) {
    return
  }

  const switched = await authStore.switchUnit(unitId)

  if (!switched) {
    // Restore the control to the unit that is actually active.
    target.value = authStore.activeUnitId !== null ? String(authStore.activeUnitId) : ''
  }
}
`

// Insert before themeTooltip
if (!content.includes('async function handleUnitChange')) {
  content = content.replace(
    /const themeTooltip = computed/,
    handleUnitChangeFunc + '\nconst themeTooltip = computed'
  )
  console.log('✓ Added handleUnitChange function')
}

// 2. Replace the single unit display line with conditional unit switcher
const oldUnitDisplay = /<div class="text-xs text-ink-muted truncate mt-0\.5" v-if="authStore\.activeUnit\?\.name \|\| authStore\.primaryUnit\?\.unit_name">{{ authStore\.activeUnit\?\.name \|\| authStore\.primaryUnit\?\.unit_name }}<\/div>/

const newUnitSwitcher = `
          <!-- Unit switcher: show when user has multiple units -->
          <div v-if="authStore.availableUnits.length > 1" class="mt-2 pt-2 border-t border-hairline">
            <label class="block text-xs font-medium text-ink-muted mb-1">
              Organizational unit
            </label>
            <select
              class="w-full rounded-md border border-hairline bg-surface px-2 py-1.5 text-xs text-ink focus:outline-hidden focus:ring-2 focus:ring-primary disabled:opacity-60"
              :value="authStore.activeUnitId !== null ? String(authStore.activeUnitId) : ''"
              :disabled="authStore.isSwitchingUnit"
              @change="handleUnitChange"
            >
              <option v-for="unit in authStore.availableUnits" :key="unit.id" :value="unit.id">
                {{ unit.name }}{{ unit.is_primary ? ' (primary)' : '' }}
              </option>
            </select>
          </div>
          <!-- Display-only unit when user has only one unit -->
          <div v-else class="text-xs text-ink-muted truncate mt-0.5">{{ authStore.activeUnit?.name || authStore.primaryUnit?.unit_name }}</div>`

if (oldUnitDisplay.test(content)) {
  content = content.replace(oldUnitDisplay, newUnitSwitcher)
  console.log('✓ Replaced unit display with conditional switcher')
} else {
  console.log('⚠ Could not find old unit display pattern - checking alternative')
  // Try finding just the div with activeUnit
  if (content.includes('authStore.activeUnit?.name || authStore.primaryUnit?.unit_name')) {
    // Find the line and replace it
    const lines = content.split('\n')
    let found = false
    for (let i = 0; i < lines.length; i++) {
      if (lines[i].includes('authStore.activeUnit?.name || authStore.primaryUnit?.unit_name') && 
          lines[i].includes('<div') && 
          !lines[i].includes('v-else')) {
        lines[i] = newUnitSwitcher
        content = lines.join('\n')
        found = true
        console.log('✓ Replaced unit display via line-by-line search')
        break
      }
    }
    if (!found) {
      console.log('✗ Could not find unit display to replace')
    }
  }
}

// Write file
const buffer = Buffer.from(content, 'utf8')
const fd = openSync(file, 'w')
writeSync(fd, buffer)
closeSync(fd)

console.log('✓ Unit switcher restoration complete')
