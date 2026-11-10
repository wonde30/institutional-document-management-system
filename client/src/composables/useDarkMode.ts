import { computed } from 'vue'
import { useThemeStore, type ThemeMode } from '@/stores/theme'

export function useDarkMode() {
  const themeStore = useThemeStore()

  const isDark = computed(() => themeStore.isDark)
  const mode = computed(() => themeStore.mode)

  function applyTheme(dark: boolean) {
    themeStore.setMode(dark ? 'dark' : 'light')
  }

  function toggle() {
    themeStore.toggle()
  }

  function init() {
    themeStore.init()
  }

  function setMode(newMode: ThemeMode) {
    themeStore.setMode(newMode)
  }

  return {
    isDark,
    mode,
    toggle,
    init,
    applyTheme,
    setMode,
  }
}
