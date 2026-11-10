import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export type ThemeMode = 'light' | 'dark' | 'system'

export const useThemeStore = defineStore('theme', () => {
  // Theme mode: 'light' (default per DESIGN.md), 'dark', or 'system'
  const mode = ref<ThemeMode>((localStorage.getItem('wu-theme-mode') as ThemeMode) || 'light')
  const systemPrefersDark = ref<boolean>(false)

  // Initialize system preference listener
  if (typeof window !== 'undefined' && window.matchMedia) {
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
    systemPrefersDark.value = mediaQuery.matches

    mediaQuery.addEventListener('change', (e) => {
      systemPrefersDark.value = e.matches
      if (mode.value === 'system') {
        applyToDom()
      }
    })
  }

  // Effective resolved theme: either 'light' or 'dark'
  const resolvedTheme = computed<'light' | 'dark'>(() => {
    if (mode.value === 'system') {
      return systemPrefersDark.value ? 'dark' : 'light'
    }
    return mode.value
  })

  const isDark = computed(() => resolvedTheme.value === 'dark')

  function applyToDom() {
    if (typeof document === 'undefined') return
    const dark = resolvedTheme.value === 'dark'
    if (dark) {
      document.documentElement.classList.add('dark')
    } else {
      document.documentElement.classList.remove('dark')
    }
    localStorage.setItem('wu-theme', resolvedTheme.value)
    localStorage.setItem('wu-theme-mode', mode.value)
  }

  function setMode(newMode: ThemeMode) {
    mode.value = newMode
    applyToDom()
  }

  function toggle() {
    if (mode.value === 'light') {
      setMode('dark')
    } else if (mode.value === 'dark') {
      setMode('system')
    } else {
      setMode('light')
    }
  }

  function init() {
    const savedMode = localStorage.getItem('wu-theme-mode') as ThemeMode | null
    if (savedMode && ['light', 'dark', 'system'].includes(savedMode)) {
      mode.value = savedMode
    } else {
      // Legacy fallback
      const savedTheme = localStorage.getItem('wu-theme')
      if (savedTheme === 'dark') {
        mode.value = 'dark'
      } else if (savedTheme === 'light') {
        mode.value = 'light'
      } else {
        mode.value = 'light' // Default to light per DESIGN.md
      }
    }
    applyToDom()
  }

  return {
    mode,
    resolvedTheme,
    isDark,
    setMode,
    toggle,
    init,
  }
})
