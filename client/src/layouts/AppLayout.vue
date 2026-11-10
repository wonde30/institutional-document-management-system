<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notifications'
import { useMetadataStore } from '@/stores/metadata'
import { useThemeStore } from '@/stores/theme'
import AppSidebar from '@/components/navigation/AppSidebar.vue'
import ToastContainer from '@/components/ui/ToastContainer.vue'
import {
 Menu,
 X,
 Bell,
 Sun,
 Moon,
 Laptop,
 ChevronDown,
 LogOut,
 Building2,
 ShieldCheck,
 CheckCheck,
 Compass,
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

// Institutional screen navigation selector
const screenOptions = [
 { label: 'Workspace Dashboard', path: '/dashboard' },
 { label: 'My Assigned Tasks', path: '/tasks' },
 { label: 'Notifications Registry', path: '/notifications' },
 { label: 'Document Repository', path: '/documents' },
 { label: 'Register Official Document', path: '/documents/create' },
 { label: 'Authorized Institutional Search', path: '/search' },
 { label: 'Incoming Letters Registry', path: '/letters/incoming' },
 { label: 'Outgoing Letters Registry', path: '/letters/outgoing' },
 { label: 'Internal Memoranda Registry', path: '/memoranda' },
 { label: 'Office Routing & Movement Tracking', path: '/routing' },
 { label: 'Finalized Research Monographs', path: '/research' },
 { label: 'Staff Records & Physical Dossiers', path: '/staff' },
 { label: 'Digital Archive & Retention Schedule', path: '/archive' },
 { label: 'Accreditation Evidence Repository', path: '/accreditation' },
 { label: 'Projects & Institutional Initiatives', path: '/projects' },
 { label: 'Cryptographic Signatures & Official Stamps', path: '/signatures' },
 { label: 'Operational Reports & Analytics', path: '/reports' },
 { label: 'Authorized Data Imports & Exports', path: '/exports' },
 { label: 'User Accounts & Directory', path: '/admin/users' },
 { label: 'Roles & Security Permissions', path: '/admin/roles' },
 { label: 'Organizational Structure & Units', path: '/admin/organization' },
 { label: 'Standard Office Procedures & Workflows', path: '/admin/office-procedures' },
 { label: 'Reference Data Catalogs', path: '/admin/reference-data' },
 { label: 'Institutional Audit Trail', path: '/admin/audit' },
 { label: 'System Operations & Service Health', path: '/admin/operations' },
 { label: 'System Settings & Configuration', path: '/admin/settings' },
 { label: 'Help & User Guidance Manual', path: '/help' },
 { label: 'My Account Profile', path: '/account' },
 { label: 'Account Security & Password', path: '/account/security' },
 { label: 'User Preferences & Timezone', path: '/account/preferences' },
 { label: 'Sign In to Portal', path: '/login' },
 { label: 'Access Forbidden (403)', path: '/forbidden' },
 { label: 'Page Not Found (404)', path: '/not-found' },
]

const visibleScreenOptions = computed(() => {
 return screenOptions.filter((opt) => {
 if (['/login', '/forbidden', '/not-found'].includes(opt.path)) return false
 const targetRoute = router.getRoutes().find((r) => r.path === opt.path)
 if (!targetRoute) return true
 const requiredPerm = targetRoute.meta?.permission as string | undefined
 if (requiredPerm) {
 return authStore.hasPermission(requiredPerm)
 }
 return true
 })
})

const currentScreenPath = computed({
 get: () => {
 const match = visibleScreenOptions.value.find((opt) => opt.path === route.path)
 return match ? match.path : '/dashboard'
 },
 set: (val: string) => {
 router.push(val)
 },
})

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

const themeTooltip = computed(() => {
 if (themeStore.mode === 'system') return 'Theme: System (matches OS) — click to change'
 if (themeStore.mode === 'dark') return 'Theme: Dark — click to change'
 return 'Theme: Light — click to change'
})

onMounted(() => {
 themeStore.init()
 if (authStore.isAuthenticated) {
 notifStore.fetchNotifications()
 metaStore.loadAll()
 authStore.fetchNavigation()
 }
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
 <div class="h-screen w-screen overflow-hidden flex flex-col bg-canvas-soft text-ink font-sans antialiased">
 <!-- Notion Paper Top Bar: 56px height, pure white surface, hairline border -->
 <header class="h-14 shrink-0 bg-surface border-b border-hairline px-4 sm:px-6 flex items-center justify-between gap-4 z-30 sticky top-0">
 <!-- Left: Mobile Menu & Brand -->
 <div class="flex items-center gap-3">
 <button
 type="button"
 class="lg:hidden p-1.5 rounded-[5px] text-ink-muted hover:text-ink hover:bg-canvas-soft transition-colors"
 @click="mobileMenuOpen = !mobileMenuOpen"
 aria-label="Toggle navigation menu"
 >
 <Menu class="w-5 h-5" />
 </button>

 <router-link to="/dashboard" class="flex items-center gap-2.5 group">
 <span class="w-8 h-8 rounded-[6px] bg-primary text-white font-bold text-xs flex items-center justify-center tracking-wider shadow-xs">
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

 <!-- Right: Global Search, Workspace Scope, Theme, Notifications, Avatar -->
 <div class="flex items-center gap-2 sm:gap-3">
 <!-- Authorized Global Search (Notion-styled search box) -->
 <router-link
 to="/search"
 class="flex items-center gap-2 px-2.5 py-1.5 rounded-[6px] text-xs bg-canvas-soft text-ink-muted hover:text-ink border border-hairline transition-colors"
 title="Search documents, letters, research"
 >
 <Search class="w-3.5 h-3.5" />
 <span class="hidden md:inline text-[11px]">Search records...</span>
 <kbd class="hidden md:inline text-[10px] bg-surface px-1.5 py-0.5 rounded-[4px] border border-hairline font-mono text-ink-muted">⌘K</kbd>
 </router-link>

 <!-- Scope Badge / Filter -->
 <div class="hidden lg:flex items-center gap-2 px-3 py-1 rounded-full text-xs bg-canvas-soft text-ink-secondary border border-hairline">
 <Building2 class="w-3.5 h-3.5 text-primary" />
 <span class="font-medium">{{ authStore.primaryUnit?.unit_name || 'My work' }}</span>
 <span class="text-ink-faint">·</span>
 <span class="text-ink-muted">{{ authStore.primaryUnit?.position_title || 'Active Session' }}</span>
 </div>

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
 <div class="relative">
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
 <div class="relative">
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
 <main class="flex-1 flex flex-col min-w-0 h-full overflow-y-auto p-4 sm:p-6 lg:p-8 scrollbar-thin bg-canvas-soft">
 <!-- Breadcrumb & Context Header Bar -->
 <div class="flex items-center justify-between gap-3 pb-4 mb-5 border-b border-hairline text-xs text-ink-muted flex-wrap">
 <div class="flex items-center gap-2 font-medium">
 <span class="uppercase tracking-wider text-[11px] font-semibold text-ink-muted">{{ currentModuleGroup }}</span>
 <span aria-hidden="true" class="text-ink-faint">/</span>
 <span class="text-ink font-semibold text-[13px]">{{ currentPageTitle }}</span>
 </div>

 <!-- Institutional View Switcher -->
 <div class="flex items-center gap-2">
 <span class="hidden sm:inline text-[11px] text-ink-muted font-medium">Institutional view:</span>
 <select
 v-model="currentScreenPath"
 class="text-xs bg-surface border border-hairline text-ink-secondary rounded-[4px] px-2.5 py-1 focus:ring-1 focus:ring-primary focus:outline-hidden max-w-[240px] cursor-pointer shadow-xs"
 aria-label="Choose an institutional view to display"
 >
 <option v-for="opt in visibleScreenOptions" :key="opt.path" :value="opt.path">
 {{ opt.label }}
 </option>
 </select>
 </div>
 </div>

 <!-- View Viewport Slot -->
 <div class="flex-1">
 <router-view />
 </div>

 <!-- Notion Minimal Footer -->
 <footer class="mt-12 pt-4 border-t border-hairline text-[11px] text-ink-faint flex flex-col sm:flex-row items-center justify-between gap-2">
 <span>Wollo University Administrative and Document Management System</span>
 <span>Exact revision control · Scoped access boundaries · Production baseline</span>
 </footer>
 </main>
 </div>

 <!-- Global Toast Container -->
 <ToastContainer />
 </div>
</template>
