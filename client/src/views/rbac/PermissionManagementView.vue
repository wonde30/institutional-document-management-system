<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { permissionsApi } from '@/api/client'
import type { PermissionItem } from '@/types'
import { useToast } from '@/composables/useToast'
import BaseButton from '@/components/ui/BaseButton.vue'
import LoadingSkeleton from '@/components/ui/LoadingSkeleton.vue'
import {
 Key,
 Shield,
 Search,
 RefreshCw,
 CheckCircle2,
 Layers,
 FileText,
 Mail,
 GitBranch,
 Archive,
 Building2,
 Users,
 Eye,
 BarChart3,
} from 'lucide-vue-next'

const toast = useToast()
const loading = ref(true)
const permissions = ref<PermissionItem[]>([])
const searchQuery = ref('')
const selectedModule = ref<string>('all')

const moduleIcons: Record<string, any> = {
 documents: FileText,
 correspondence: Mail,
 workflow: GitBranch,
 archive: Archive,
 organization: Building2,
 users: Users,
 roles: Shield,
 permissions: Key,
 audit: Eye,
 reports: BarChart3,
}

const groupedPermissions = computed(() => {
 const groups: Record<string, PermissionItem[]> = {}
 permissions.value.forEach((perm) => {
 const parts = perm.code.split('.')
 const mod = parts.length > 1 ? parts[0] : 'general'
 if (!groups[mod]) {
 groups[mod] = []
 }
 groups[mod].push(perm)
 })
 return groups
})

const availableModules = computed(() => {
 return Object.keys(groupedPermissions.value).sort()
})

const filteredGroups = computed(() => {
 const q = searchQuery.value.toLowerCase().trim()
 const result: Record<string, PermissionItem[]> = {}

 for (const [mod, perms] of Object.entries(groupedPermissions.value)) {
 if (selectedModule.value !== 'all' && selectedModule.value !== mod) {
 continue
 }

 const matched = perms.filter(
 (p) =>
 !q ||
 p.code.toLowerCase().includes(q) ||
 p.name.toLowerCase().includes(q) ||
 (p.description && p.description.toLowerCase().includes(q))
 )

 if (matched.length > 0) {
 result[mod] = matched
 }
 }

 return result
})

const totalFilteredCount = computed(() => {
 return Object.values(filteredGroups.value).reduce((sum, list) => sum + list.length, 0)
})

async function loadPermissions() {
 loading.value = true
 try {
 const res = await permissionsApi.list()
 permissions.value = res.data.data
 } catch (err: any) {
 toast.error('Load Error', err.response?.data?.message || 'Failed to load permission catalog.')
 } finally {
 loading.value = false
 }
}

onMounted(() => {
 loadPermissions()
})
</script>

<template>
 <div class="space-y-6">
 <!-- Header -->
 <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
 <div>
 <div class="text-[11px] font-bold tracking-widest text-primary uppercase mb-1">
 ADMINISTRATION / PERMISSION REGISTRY
 </div>
 <h1 class="text-2xl font-bold tracking-tight text-ink-secondary flex items-center gap-2.5">
 <Key class="w-7 h-7 text-primary" />
 Institutional Permission Registry
 </h1>
 <p class="text-sm text-ink-muted mt-1">
 Catalog of atomic security capabilities enforcing server-side authorization boundaries across all 12 baseline domains.
 </p>
 </div>

 <div class="flex items-center gap-2.5">
 <BaseButton variant="outline" size="sm" :loading="loading" @click="loadPermissions">
 <RefreshCw class="w-4 h-4 mr-1.5" />
 Refresh
 </BaseButton>
 </div>
 </div>

 <!-- Stats & Filters -->
 <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
 <div class="bg-surface p-4 rounded-xl border border-hairline shadow-xs flex items-center gap-4">
 <div class="p-3 bg-primary-subtle text-primary rounded-lg">
 <Key class="w-6 h-6" />
 </div>
 <div>
 <div class="text-2xl font-bold text-ink-secondary">{{ permissions.length }}</div>
 <div class="text-xs text-ink-muted font-medium">Atomic Baseline Permissions</div>
 </div>
 </div>

 <div class="bg-surface p-4 rounded-xl border border-hairline shadow-xs flex items-center gap-4">
 <div class="p-3 bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400 rounded-lg">
 <Layers class="w-6 h-6" />
 </div>
 <div>
 <div class="text-2xl font-bold text-ink-secondary">{{ availableModules.length }}</div>
 <div class="text-xs text-ink-muted font-medium">Functional System Domains</div>
 </div>
 </div>

 <div class="bg-surface p-4 rounded-xl border border-hairline shadow-xs flex items-center gap-4">
 <div class="p-3 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 rounded-lg">
 <CheckCircle2 class="w-6 h-6" />
 </div>
 <div>
 <div class="text-2xl font-bold text-ink-secondary">Active</div>
 <div class="text-xs text-ink-muted font-medium">Strict Enforced State</div>
 </div>
 </div>
 </div>

 <!-- Filter Bar -->
 <div class="bg-surface rounded-xl border border-hairline p-4 shadow-xs flex flex-col sm:flex-row items-center justify-between gap-4">
 <div class="relative w-full sm:w-80">
 <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
 <input
 v-model="searchQuery"
 type="text"
 placeholder="Filter permissions by code or description..."
 class="w-full pl-9 pr-3 py-1.5 text-xs rounded-lg border border-hairline bg-surface text-ink-secondary focus:outline-none focus:ring-1 focus:ring-primary focus:border-primary"
 />
 </div>

 <div class="flex items-center gap-2 overflow-x-auto w-full sm:w-auto pb-1 sm:pb-0">
 <button
 type="button"
 class="px-3 py-1 rounded-lg text-xs font-semibold whitespace-nowrap transition cursor-pointer"
 :class="selectedModule === 'all' ? 'bg-primary text-white shadow-xs' : 'bg-canvas-soft text-ink-muted hover:bg-surface-hover'"
 @click="selectedModule = 'all'"
 >
 All Domains
 </button>
 <button
 v-for="mod in availableModules"
 :key="mod"
 type="button"
 class="px-2.5 py-1 rounded-lg text-xs font-semibold whitespace-nowrap uppercase tracking-wider transition cursor-pointer"
 :class="selectedModule === mod ? 'bg-primary text-white shadow-xs' : 'bg-canvas-soft text-ink-muted hover:bg-surface-hover'"
 @click="selectedModule = mod"
 >
 {{ mod }} ({{ groupedPermissions[mod]?.length || 0 }})
 </button>
 </div>
 </div>

 <!-- Loading Skeleton -->
 <LoadingSkeleton v-if="loading" :count="4" class="my-4" />

 <!-- Grouped Permission Catalog -->
 <div v-else class="space-y-6">
 <div
 v-for="(perms, mod) in filteredGroups"
 :key="mod"
 class="bg-surface rounded-xl border border-hairline p-5 shadow-xs space-y-4"
 >
 <div class="flex items-center justify-between pb-3 border-b border-hairline">
 <div class="flex items-center gap-2.5">
 <div class="p-2 rounded-lg bg-primary-subtle text-primary">
 <component :is="moduleIcons[mod] || Shield" class="w-5 h-5" />
 </div>
 <div>
 <h2 class="text-base font-bold text-ink-secondary uppercase tracking-wider">
 {{ mod }} Domain
 </h2>
 <div class="text-xs text-ink-muted">
 {{ perms.length }} atomic capabilities
 </div>
 </div>
 </div>

 <span class="text-xs font-semibold px-2.5 py-0.5 rounded-md bg-canvas-soft text-ink-secondary">
 Enforced
 </span>
 </div>

 <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
 <div
 v-for="perm in perms"
 :key="perm.id"
 class="p-3 rounded-lg border border-hairline bg-canvas-soft hover:border-primary/40 transition"
 >
 <div class="flex items-center justify-between gap-2 mb-1">
 <span class="font-mono text-xs font-bold text-primary">
 {{ perm.code }}
 </span>
 <span class="w-2 h-2 rounded-full bg-emerald-500" title="Active" />
 </div>

 <div class="text-xs font-semibold text-ink-secondary">
 {{ perm.name }}
 </div>

 <p class="text-[11px] text-ink-muted mt-1 line-clamp-2">
 {{ perm.description || 'System-enforced RBAC capability.' }}
 </p>
 </div>
 </div>
 </div>

 <div v-if="totalFilteredCount === 0" class="py-12 text-center text-ink-muted text-sm">
 No permissions matched your query.
 </div>
 </div>
 </div>
</template>
