<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notifications'
import { useMetadataStore } from '@/stores/metadata'
import { useThemeStore } from '@/stores/theme'
import AppSidebar from '@/components/navigation/AppSidebar.vue'
import ToastContainer from '@/components/ui/ToastContainer.vue'
import {
 Menu,
 Bell,
 Sun,
 Moon,
 Laptop,
 ChevronDown,
 LogOut,
 Search,
 User as UserIcon,
 Lock,
 SlidersHorizontal,
} from 'lucide-vue-next'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const notifStore = useNotificationStore()
const metaStore = useMetadataStore()
const themeStore = useThemeStore()

const mobileMenuOpen = ref(false)
const showNotifDropdown = ref(false)
const showUserDropdown = ref(false)

// Template refs for click-outside handling
const notifDropdownRef = ref<HTMLElement | null>(null)
const userDropdownRef = ref<HTMLElement | null>(null)

const currentModuleGroup = computed(() => {
 return (route.meta?.group as string) || 'Workspace'
})

const currentPageTitle = computed(() => {
 return (route.meta?.title as string) || 'Dashboard'
})

const userInitials = computed(() => {
 const name = authStore.user?.name || 'Staff Officer'
 const parts = name.split(' ')
 if (parts.length >= 2) {
 return `${parts[0][0]}${parts[1][0]}`.toUpperCase()
 }
 return name.slice(0, 2).toUpperCase()
})

/**
 * Whether the platform uses the Command key for shortcuts.
 */
const isMac = computed(() =>
 typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform)
)

/**
 * The advertised search shortcut.
 */
function handleGlobalShortcut(event: KeyboardEvent) {
 if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
  event.preventDefault()
  router.push('/search')
 }
}

/**
 * Close dropdowns when clicking outside their containers.
 */
function handleClickOutside(event: MouseEvent) {
 const target = event.target as Node
 if (showNotifDropdown.value && notifDropdownRef.value && !notifDropdownRef.value.contains(target)) {
  showNotifDropdown.value = false
 }
 if (showUserDropdown.value && userDropdownRef.value && !userDropdownRef.value.contains(target)) {
  showUserDropdown.value = false
 }
}

const themeTooltip = computed(() => {
 if (themeStore.mode === 'system') return 'Theme: System (matches OS) — click to change'
 if (themeStore.mode === 'dark') return 'Theme: Dark — click to change'
 return 'Theme: Light — click to change'
})

onMounted(() => {
 themeStore.init()
 window.addEventListener('keydown', handleGlobalShortcut)
 document.addEventListener('click', handleClickOutside)
 if (authStore.isAuthenticated) {
 notifStore.fetchNotifications()
 metaStore.loadAll()
 }
})

onUnmounted(() => {
 window.removeEventListener('keydown', handleGlobalShortcut)
 document.removeEventListener('click', handleClickOutside)
})

// Auto-close mobile drawer and dropdowns on route changes
watch(
 () => route.path,
 () => {
 mobileMenuOpen.value = false
 showNotifDropdown.value = false
 showUserDropdown.value = false
 }
)

async function handleLogout() {
 await authStore.logout()
 router.push('/')
}
</script>

<template>
  <a
    href="#main-content"
    class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-[100] focus:px-4 focus:py-2 focus:bg-primary focus:text-white focus:rounded-lg focus:text-sm focus:font-medium focus:shadow-lg focus:outline-none"
  >Skip to main content</a>
  <div class="h-screen w-screen overflow-hidden flex flex-col bg-canvas-soft text-ink font-sans antialiased">
 <!-- Notion Paper Top Bar: 56px height, pure white surface, hairline border -->
 <header class="h-14 shrink-0 bg-surface border-b border-hairline px-4 sm:px-6 flex items-center justify-between gap-4 z-30 sticky top-0">
 <!-- Left: Mobile/Drawer Menu & Brand -->
 <div class="flex items-center gap-3">
 <button
 type="button"
 class="xl:hidden p-1.5 rounded-[5px] text-ink-muted hover:text-ink hover:bg-canvas-soft transition-colors"
 @click="mobileMenuOpen = !mobileMenuOpen"
 aria-label="Toggle navigation menu"
 >
 <Menu class="w-5 h-5" />
 </button>

 <router-link to="/dashboard" class="xl:hidden flex items-center gap-2.5 group">
 <span class="w-8 h-8 rounded-[6px] bg-[#213183] text-white font-bold text-xs flex items-center justify-center tracking-wider shadow-xs">
 WU
 </span>
 <div class="flex flex-col">
 <span class="text-sm font-semibold tracking-tight leading-tight text-ink">
 Wollo University
 </span>
 <small class="text-[11px] font-normal text-ink-muted">
 Administrative DMS
 </small>
 </div>
 </router-link>
 </div>

 <!-- Right: Global Search, Theme, Notifications, Avatar -->
 <div class="flex items-center gap-2 sm:gap-3">
 <!-- Authorized Global Search (Notion-styled search box) -->
 <router-link
 to="/search"
 class="flex items-center gap-2 px-2.5 py-1.5 rounded-[6px] text-xs bg-canvas-soft text-ink-muted hover:text-ink border border-hairline transition-colors"
 title="Search documents, letters, research"
 >
 <Search class="w-3.5 h-3.5" />
 <span class="hidden md:inline text-[11px]">Search records...</span>
 <kbd class="hidden md:inline text-[10px] bg-surface px-1.5 py-0.5 rounded-[4px] border border-hairline font-mono text-ink-muted">{{ isMac ? '⌘K' : 'Ctrl K' }}</kbd>
 </router-link>

 <!-- Tri-Mode Theme Toggle (Light / Dark / System) -->
 <button
 type="button"
 class="p-2 rounded-[6px] text-ink-muted hover:text-ink hover:bg-canvas-soft transition-colors"
 @click="themeStore.toggle"
 :title="themeTooltip"
 aria-label="Toggle light, dark, or system theme mode"
 >
 <Sun v-if="themeStore.mode === 'light'" class="w-4 h-4 text-amber-500" />
 <Moon v-else-if="themeStore.mode === 'dark'" class="w-4 h-4 text-primary" />
 <Laptop v-else class="w-4 h-4 text-ink-muted" />
 </button>

 <!-- Notification Bell Dropdown -->
 <div ref="notifDropdownRef" class="relative">
 <button
 type="button"
 class="relative p-2 rounded-[6px] text-ink-muted hover:text-ink hover:bg-canvas-soft transition-colors"
 @click="showNotifDropdown = !showNotifDropdown"
 aria-label="View notifications"
 >
 <Bell class="w-4 h-4" />
 <span
 v-if="notifStore.unreadCount > 0"
 class="absolute top-1 right-1 w-2 h-2 rounded-full bg-[#e03e3e] ring-2 ring-surface"
 />
 </button>

 <div
 v-if="showNotifDropdown"
 class="absolute right-0 mt-2 w-80 sm:w-88 rounded-xl bg-surface border border-hairline shadow-lg py-2.5 z-40"
 >
 <div class="px-4 py-2 border-b border-hairline flex items-center justify-between">
 <span class="text-xs font-semibold text-ink">
 Notifications ({{ notifStore.unreadCount }})
 </span>
 <router-link
 to="/notifications"
 class="text-[11px] text-primary font-medium hover:underline"
 @click="showNotifDropdown = false"
 >
 View all
 </router-link>
 </div>

 <div class="max-h-64 overflow-y-auto divide-y divide-hairline-subtle">
 <div
 v-for="notif in notifStore.notifications.slice(0, 5)"
 :key="notif.id"
 class="p-3 text-xs hover:bg-canvas-soft transition cursor-pointer"
 @click="router.push('/notifications')"
 >
 <div class="font-medium text-ink">
 {{ notif.event?.type_code || 'Security notice' }}
 </div>
 <div class="text-[11px] text-ink-muted mt-0.5">
 {{ notif.created_at ? new Date(notif.created_at).toLocaleTimeString() : 'Recent' }}
 </div>
 </div>

 <div v-if="notifStore.notifications.length === 0" class="py-6 text-center text-xs text-ink-faint">
 No pending notifications
 </div>
 </div>
 </div>
 </div>

 <!-- User Profile Dropdown -->
 <div ref="userDropdownRef" class="relative">
 <button
 type="button"
 class="flex items-center gap-2 p-1 pl-1.5 rounded-[6px] hover:bg-canvas-soft transition"
 @click="showUserDropdown = !showUserDropdown"
 >
 <span class="w-7 h-7 rounded-full bg-primary-subtle text-primary font-bold text-xs flex items-center justify-center border border-primary/20">
 {{ userInitials }}
 </span>
 <ChevronDown class="w-3.5 h-3.5 text-ink-muted" />
 </button>

 <div
 v-if="showUserDropdown"
 class="absolute right-0 mt-2 w-60 rounded-xl bg-surface border border-hairline shadow-lg py-1.5 z-40 text-xs divide-y divide-hairline-subtle"
 >
 <div class="px-4 py-2.5">
 <div class="font-semibold text-ink truncate">{{ authStore.user?.name }}</div>
 <div class="text-[11px] text-ink-muted truncate">{{ authStore.user?.email }}</div>
  <div class="text-[11px] text-ink-muted truncate mt-0.5" v-if="authStore.primaryUnit?.position_title">{{ authStore.primaryUnit?.position_title }}</div>
 <div class="text-[11px] text-ink-muted truncate mt-0.5" v-if="authStore.activeUnit?.name || authStore.primaryUnit?.unit_name">{{ authStore.activeUnit?.name || authStore.primaryUnit?.unit_name }}</div>
 </div>

 <div class="py-1">
 <router-link
 to="/account"
 class="w-full px-4 py-2 text-left text-ink-secondary hover:bg-canvas-soft flex items-center gap-2 transition-colors"
 @click="showUserDropdown = false"
 >
 <UserIcon class="w-3.5 h-3.5 text-ink-muted" />
 My Profile & Details
 </router-link>

 <router-link
 to="/account/security"
 class="w-full px-4 py-2 text-left text-ink-secondary hover:bg-canvas-soft flex items-center gap-2 transition-colors"
 @click="showUserDropdown = false"
 >
 <Lock class="w-3.5 h-3.5 text-ink-muted" />
 Account Security & Password
 </router-link>

 <router-link
 to="/account/preferences"
 class="w-full px-4 py-2 text-left text-ink-secondary hover:bg-canvas-soft flex items-center gap-2 transition-colors"
 @click="showUserDropdown = false"
 >
 <SlidersHorizontal class="w-3.5 h-3.5 text-ink-muted" />
 Preferences & Timezone
 </router-link>
 </div>

 <div class="py-1">
 <button
 type="button"
 class="w-full px-4 py-2 text-left text-[#e03e3e] dark:text-[#f87171] hover:bg-[#fde8e8]/50 dark:hover:bg-[#371717]/50 flex items-center gap-2 transition-colors cursor-pointer"
 @click="handleLogout"
 >
 <LogOut class="w-4 h-4" />
 Sign Out of Session
 </button>
 </div>
 </div>
 </div>
 </div>
 </header>

 <!-- Content Layout Grid: Fixed Viewport Container -->
 <div class="flex-1 flex min-h-0 overflow-hidden relative">
 <!-- Notion Daylight Sidebar Navigation -->
 <AppSidebar
 v-model:mobile-open="mobileMenuOpen"
 @close="mobileMenuOpen = false"
 />

 <!-- Main Worksurface: Warm Paper Canvas (#f6f5f4) Dedicated Viewport -->
 <main id="main-content" tabindex="-1" class="flex-1 flex flex-col min-w-0 h-full overflow-y-auto p-4 sm:p-6 lg:p-6 scrollbar-thin bg-canvas-soft focus:outline-none">
        <!-- Routed page with subtle transition -->
        <Transition name="page" mode="out-in">
          <div class="flex-1 min-h-0" :key="$route.path">
            <slot />
          </div>
        </Transition>

 </main>
 </div>

 <!-- Global Toast Container -->
 <ToastContainer />
 </div>
</template>


