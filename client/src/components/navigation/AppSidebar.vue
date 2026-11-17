<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotificationStore } from '@/stores/notifications'
import { navigationConfig, resolveNavIcon, type NavigationSection, type NavigationItem } from '@/config/navigation'
import {
 ChevronDown,
 X,
 Bell,
} from 'lucide-vue-next'

interface Props {
 mobileOpen?: boolean
}

const props = withDefaults(defineProps<Props>(), {
 mobileOpen: false,
})

const emit = defineEmits<{
 (e: 'update:mobileOpen', value: boolean): void
 (e: 'close'): void
}>()

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const notifStore = useNotificationStore()

/**
 * Apply an organizational unit selection.
 *
 * The server validates the selection and recalculates the capability set. A
 * refused switch leaves the previous context untouched.
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

// Normalize sections from either backend dynamic navigation or baseline configuration
const rawSections = computed<NavigationSection[]>(() => {
 if (authStore.dynamicNavigation && Array.isArray(authStore.dynamicNavigation) && authStore.dynamicNavigation.length > 0) {
 return authStore.dynamicNavigation.map((section: any) => ({
 ...section,
 icon: resolveNavIcon(section.icon),
 items: (section.items || []).map((item: any) => ({
 ...item,
 icon: resolveNavIcon(item.icon),
 })),
 }))
 }

 return navigationConfig.map((section: NavigationSection) => ({
 ...section,
 icon: resolveNavIcon(section.icon),
 items: section.items.map((item: NavigationItem) => ({
 ...item,
 icon: resolveNavIcon(item.icon),
 })),
 }))
})

// Filter navigation sections based on dynamic RBAC permissions
const visibleNavSections = computed<NavigationSection[]>(() => {
 return rawSections.value
 .map((section: NavigationSection) => {
 const filteredItems = section.items.filter((item) => {
 if (!item.permission) return true
 if (Array.isArray(item.permission)) {
 return item.permission.some((p: string) => authStore.hasPermission(p))
 }
 return authStore.hasPermission(item.permission)
 })
 return {
 ...section,
 items: filteredItems,
 }
 })
 .filter((section) => section.items.length > 0)
})

/**
 * Checks whether an item corresponds to the current route.
 */
function isItemActive(item: NavigationItem): boolean {
 if (route.path === item.to) return true

 // Handle nested/sub-paths (e.g., /documents/create or /documents/42 matches /documents)
 if (item.to !== '/' && item.to !== '/dashboard' && route.path.startsWith(item.to + '/')) {
 return true
 }

 // Handle named route matches for document subviews
 if (item.to === '/documents' && typeof route.name === 'string' && route.name.startsWith('documents.')) {
 return true
 }

 return false
}

/**
 * Checks whether any item in a group is active.
 */
function isGroupActive(section: NavigationSection): boolean {
 return section.items.some((item) => isItemActive(item))
}

/**
 * Checks whether a given section is currently expanded.
 */
function isGroupExpanded(title: string): boolean {
 return expandedGroupTitle.value === title
}

/**
 * Toggles a group. Single-open accordion rule:
 * - If clicking the currently open group, it collapses.
 * - If clicking another group, it expands and collapses the previous group.
 */
function toggleGroup(title: string) {
 if (expandedGroupTitle.value === title) {
 expandedGroupTitle.value = null
 } else {
 expandedGroupTitle.value = title
 }
}

/**
 * Determines which navigation section contains the current route.
 * Prioritizes route.meta.group, then item path matching, then path prefix fallbacks.
 */
function findGroupForRoute(): string | null {
 // 1. Explicit route.meta.group match
 const metaGroup = route.meta?.group as string | undefined
 if (metaGroup) {
 const matchedSection = visibleNavSections.value.find(
 (s) => s.title.toLowerCase() === metaGroup.toLowerCase()
 )
 if (matchedSection) return matchedSection.title
 }

 // 2. Direct item URL matching
 for (const section of visibleNavSections.value) {
 for (const item of section.items) {
 if (isItemActive(item)) {
 return section.title
 }
 }
 }

 // 3. Path prefix heuristics
 const path = route.path
 if (path.startsWith('/admin') || path === '/help') return 'Administration'
 if (path.startsWith('/letters') || path.startsWith('/memoranda') || path.startsWith('/routing') || path.startsWith('/documents') || path.startsWith('/correspondence')) {
 return 'Records'
 }
 if (path.startsWith('/research') || path.startsWith('/staff') || path.startsWith('/archive') || path.startsWith('/accreditation') || path.startsWith('/projects')) {
 return 'Institutional records'
 }
 if (path.startsWith('/signatures') || path.startsWith('/reports') || path.startsWith('/exports') || path.startsWith('/imports')) {
 return 'Trust & reporting'
 }
 if (path === '/dashboard' || path === '/tasks' || path === '/notifications' || path.startsWith('/account')) {
 return 'Workspace'
 }

 // Default to first section if available
 return visibleNavSections.value[0]?.title || null
}

// Single-open accordion state: initialized immediately on setup
const expandedGroupTitle = ref<string | null>(findGroupForRoute())

/**
 * Synchronizes the expanded accordion group with the active route.
 */
function syncGroupWithRoute() {
 const target = findGroupForRoute()
 if (target) {
 expandedGroupTitle.value = target
 }
}

// Initial expansion on mount and dynamic navigation synchronization
onMounted(() => {
 if (authStore.isAuthenticated && !authStore.dynamicNavigation) {
 authStore.fetchNavigation().catch(() => {})
 }
 syncGroupWithRoute()
 window.addEventListener('keydown', handleKeyDown)
})

onUnmounted(() => {
 window.removeEventListener('keydown', handleKeyDown)
})

// Auto-expand destination module's group when route changes or dynamic permissions update
watch(
 () => route.fullPath,
 () => {
 syncGroupWithRoute()
 closeMobileDrawer()
 }
)

watch(
 () => authStore.dynamicNavigation,
 () => {
 syncGroupWithRoute()
 }
)

function closeMobileDrawer() {
 emit('update:mobileOpen', false)
 emit('close')
}

function handleKeyDown(e: KeyboardEvent) {
 if (e.key === 'Escape' && props.mobileOpen) {
 closeMobileDrawer()
 }
}

function handleLinkClick() {
 closeMobileDrawer()
}
</script>

<template>
 <div class="h-full shrink-0">
 <!-- Mobile Backdrop -->
 <div
 v-if="mobileOpen"
 class="fixed inset-0 bg-black/40 backdrop-blur-xs z-30 lg:hidden transition-opacity"
 @click="closeMobileDrawer"
 aria-hidden="true"
 />

 <!-- Main Sidebar Container: 240px → 64px collapsing, or drawer below 1280px -->
 <aside
 class="bg-surface text-ink-secondary shrink-0 z-40 flex flex-col justify-between transition-transform duration-300 select-none border-r border-hairline h-full"
 :class="[
 'fixed inset-y-0 left-0 w-60 xl:static xl:translate-x-0',
 mobileOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full',
 ]"
 role="navigation"
 aria-label="Institutional Navigation"
 >
 <!-- Top Section: University Branding & Mobile Close -->
 <div class="px-4 py-3.5 border-b border-hairline flex items-center justify-between gap-2">
 <router-link to="/dashboard" class="flex items-center gap-2.5 group focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-primary rounded-[5px]" @click="handleLinkClick">
 <span class="w-8 h-8 rounded-[6px] bg-primary text-white font-bold text-xs flex items-center justify-center tracking-wider shadow-xs shrink-0">
 WU
 </span>
 <div class="flex flex-col min-w-0">
 <span class="text-xs font-semibold tracking-tight leading-tight text-ink group-hover:text-primary transition truncate">
 Wollo University
 </span>
 <small class="text-[10px] text-ink-muted truncate">
 Administrative DMS
 </small>
 </div>
 </router-link>

 <!-- Mobile Drawer Close Button -->
 <button
 type="button"
 class="lg:hidden p-1.5 rounded-[5px] text-ink-muted hover:text-ink hover:bg-canvas-soft transition focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-primary"
 @click="closeMobileDrawer"
 aria-label="Close navigation menu"
 >
 <X class="w-4 h-4" />
 </button>
 </div>

 <!-- Navigation Accordion Area (Scrollable within Viewport) -->
 <nav class="flex-1 overflow-y-auto px-2.5 py-3 space-y-1.5 scrollbar-thin">
 <div
 v-for="(section, idx) in visibleNavSections"
 :key="section.title"
 class="rounded-[6px] overflow-hidden border border-transparent transition-colors"
 :class="{
 }"
 >
 <!-- Accordion Group Heading (sentence case, no tracking-wide) -->
 <button
 type="button"
 :id="'accordion-header-' + idx"
 :aria-controls="'accordion-panel-' + idx"
 :aria-expanded="isGroupExpanded(section.title)"
 @click="toggleGroup(section.title)"
 class="w-full flex items-center justify-between gap-2 px-2.5 py-1.5 rounded-[5px] text-left transition-all text-[12px] font-semibold group focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-primary cursor-pointer"
 :class="[
 isGroupExpanded(section.title)
 ? 'text-ink'
 : 'text-ink-muted hover:text-ink hover:bg-canvas-soft',
 ]"
 >
 <div class="flex items-center gap-2 min-w-0">
 <component
 :is="section.icon"
 v-if="section.icon"
 class="w-3.5 h-3.5 shrink-0 transition-colors"
 :class="isGroupExpanded(section.title) ? 'text-primary' : 'text-ink-faint group-hover:text-ink'"
 />
 <span class="truncate">{{ section.title }}</span>

 </div>

 <div class="flex items-center gap-1.5 shrink-0">
 <!-- Active Route Indicator Dot when group is collapsed -->
 <span
 v-if="!isGroupExpanded(section.title) && isGroupActive(section)"
 class="w-2 h-2 rounded-full bg-primary ring-2 ring-primary/30"
 title="Current active page is in this module"
 aria-label="Active module"
 />

 <!-- Expansion Chevron -->
 <ChevronDown
 class="w-3.5 h-3.5 text-ink-faint group-hover:text-ink transition-transform duration-200"
 :class="isGroupExpanded(section.title) ? 'rotate-180 text-primary' : 'rotate-0'"
 />
 </div>
 </button>

 <!-- Collapsible Menu Items Panel (Smooth CSS Grid Transition, Jump-Free) -->
 <div
 :id="'accordion-panel-' + idx"
 role="region"
 :aria-labelledby="'accordion-header-' + idx"
 class="grid transition-all duration-200 ease-in-out"
 :class="[
 isGroupExpanded(section.title)
 ? 'grid-rows-[1fr] opacity-100 pt-0.5 pb-1'
 : 'grid-rows-[0fr] opacity-0 pointer-events-none',
 ]"
 >
 <div class="min-h-0 overflow-hidden space-y-0.5 px-1">
 <router-link
 v-for="item in section.items"
 :key="item.to"
 :to="item.to"
 v-slot="{ href, navigate }"
 custom
 >
 <!-- Navigation item: soft bg for active, no left border, primary blue active bg -->
 <a
 :href="href"
 :aria-current="isItemActive(item) ? 'page' : undefined"
 @click="(e) => { navigate(e); handleLinkClick(); }"
 class="flex items-center justify-between gap-2 px-2.5 py-1.5 rounded-[5px] text-xs transition-all group focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-primary cursor-pointer"
 :class="[
 isItemActive(item)
 ? 'bg-primary-subtle text-primary font-semibold'
 : 'text-ink-secondary hover:bg-canvas-soft hover:text-ink font-medium',
 ]"
 >
 <div class="flex items-center gap-2 min-w-0">
 <component
 :is="item.icon"
 class="w-3.5 h-3.5 shrink-0 transition-colors"
 :class="isItemActive(item) ? 'text-primary' : 'text-ink-faint group-hover:text-ink-secondary'"
 />
 <span class="truncate">{{ item.name }}</span>
 </div>

 <!-- Notification Count Badge -->
 <span
 v-if="item.to === '/notifications' && notifStore.unreadCount > 0"
 class="px-1.5 py-0.2 rounded-full text-[10px] font-bold bg-[#e03e3e] text-white shrink-0"
 >
 {{ notifStore.unreadCount }}
 </span>
 </a>
 </router-link>
 </div>
 </div>
 </div>
 </nav>

 <!-- Sidebar Footer -->
 <div class="p-3 border-t border-hairline text-[11px] text-ink-muted flex items-center justify-center">
 <span class="text-[10px] text-ink-faint tracking-tight">© 2026 Wollo University</span>
 </div>
 </aside>
 </div>
</template>

<style scoped>
/* Quiet hairline scrollbar for the navigation area */
.scrollbar-thin::-webkit-scrollbar {
 width: 4px;
}
.scrollbar-thin::-webkit-scrollbar-track {
 background: transparent;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
 background-color: var(--wu-line, #e6e6e6);
 border-radius: 4px;
}
.scrollbar-thin::-webkit-scrollbar-thumb:hover {
 background-color: var(--wu-muted, #615d59);
}
</style>
