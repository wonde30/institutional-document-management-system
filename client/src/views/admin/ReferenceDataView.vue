<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { metadataApi } from '@/api/client'
import LoadingSkeleton from '@/components/ui/LoadingSkeleton.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { Database, ShieldCheck, Tag, Flag, Send, RefreshCw } from 'lucide-vue-next'

const loading = ref(true)
const activeTab = ref<'categories' | 'confidentiality' | 'types' | 'priorities' | 'dispatch'>('categories')

const categories = ref<any[]>([])
const confidentiality = ref<any[]>([])
const types = ref<any[]>([])
const priorities = ref<any[]>([])
const dispatchMethods = ref<any[]>([])

async function loadData() {
 loading.value = true
 try {
 const res = await metadataApi.getAll()
 const d = res.data.data
 categories.value = d.document_categories || []
 confidentiality.value = d.confidentiality_levels || []
 types.value = d.correspondence_types || []
 priorities.value = d.priorities || []
 dispatchMethods.value = d.dispatch_methods || []
 } catch {
 } finally {
 loading.value = false
 }
}

onMounted(() => {
 loadData()
})
</script>

<template>
 <div class="space-y-6">
 <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
 <div>
 <div class="text-[11px] font-bold tracking-widest text-primary uppercase mb-1">
 ADMINISTRATION / REFERENCE DATA CATALOGS
 </div>
 <h1 class="text-2xl font-bold tracking-tight text-ink-secondary">
 Institutional Vocabulary & Reference Catalogs
 </h1>
 <p class="text-xs sm:text-sm text-ink-muted mt-0.5">
 Standardized system taxonomy: categories, confidentiality classifications, priorities, and dispatch methods.
 </p>
 </div>

 <BaseButton variant="outline" size="sm" :loading="loading" @click="loadData">
 <RefreshCw class="w-4 h-4 mr-1.5" />
 Refresh
 </BaseButton>
 </div>

 <!-- Navigation Tabs -->
 <div class="border-b border-hairline flex gap-4 text-xs font-semibold overflow-x-auto">
 <button
 type="button"
 @click="activeTab = 'categories'"
 class="pb-3 border-b-2 flex items-center gap-1.5 whitespace-nowrap transition-colors cursor-pointer"
 :class="activeTab === 'categories'
 ? 'border-primary text-primary'
 : 'border-transparent text-ink-muted hover:text-ink-secondary '"
 >
 <Tag class="w-3.5 h-3.5" />
 Categories ({{ categories.length }})
 </button>

 <button
 type="button"
 @click="activeTab = 'confidentiality'"
 class="pb-3 border-b-2 flex items-center gap-1.5 whitespace-nowrap transition-colors cursor-pointer"
 :class="activeTab === 'confidentiality'
 ? 'border-primary text-primary'
 : 'border-transparent text-ink-muted hover:text-ink-secondary '"
 >
 <ShieldCheck class="w-3.5 h-3.5" />
 Confidentiality Levels ({{ confidentiality.length }})
 </button>

 <button
 type="button"
 @click="activeTab = 'types'"
 class="pb-3 border-b-2 flex items-center gap-1.5 whitespace-nowrap transition-colors cursor-pointer"
 :class="activeTab === 'types'
 ? 'border-primary text-primary'
 : 'border-transparent text-ink-muted hover:text-ink-secondary '"
 >
 <Database class="w-3.5 h-3.5" />
 Correspondence Types ({{ types.length }})
 </button>

 <button
 type="button"
 @click="activeTab = 'priorities'"
 class="pb-3 border-b-2 flex items-center gap-1.5 whitespace-nowrap transition-colors cursor-pointer"
 :class="activeTab === 'priorities'
 ? 'border-primary text-primary'
 : 'border-transparent text-ink-muted hover:text-ink-secondary '"
 >
 <Flag class="w-3.5 h-3.5" />
 Priorities ({{ priorities.length }})
 </button>

 <button
 type="button"
 @click="activeTab = 'dispatch'"
 class="pb-3 border-b-2 flex items-center gap-1.5 whitespace-nowrap transition-colors cursor-pointer"
 :class="activeTab === 'dispatch'
 ? 'border-primary text-primary'
 : 'border-transparent text-ink-muted hover:text-ink-secondary '"
 >
 <Send class="w-3.5 h-3.5" />
 Dispatch Channels ({{ dispatchMethods.length }})
 </button>
 </div>

 <LoadingSkeleton v-if="loading" :count="4" />

 <!-- Table of Catalog Data -->
 <div v-else class="bg-surface border border-hairline rounded-xl shadow-xs overflow-hidden">
 <!-- Categories -->
 <table v-if="activeTab === 'categories'" class="w-full text-xs text-left">
 <thead class="bg-canvas-soft border-b border-hairline text-ink-muted font-semibold uppercase tracking-wider">
 <tr>
 <th class="p-3.5">Code</th>
 <th class="p-3.5">Category Name</th>
 <th class="p-3.5">Retention Trigger</th>
 <th class="p-3.5">Status</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-hairline text-ink-secondary">
 <tr v-for="cat in categories" :key="cat.id" class="hover:bg-surface-hover transition">
 <td class="p-3.5 font-mono font-bold text-primary">{{ cat.code }}</td>
 <td class="p-3.5 font-medium">{{ cat.name }}</td>
 <td class="p-3.5 text-ink-muted">{{ cat.retention_trigger || 'Registration Date' }}</td>
 <td class="p-3.5">
 <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300">Active</span>
 </td>
 </tr>
 </tbody>
 </table>

 <!-- Confidentiality -->
 <table v-else-if="activeTab === 'confidentiality'" class="w-full text-xs text-left">
 <thead class="bg-canvas-soft border-b border-hairline text-ink-muted font-semibold uppercase tracking-wider">
 <tr>
 <th class="p-3.5">Rank</th>
 <th class="p-3.5">Code</th>
 <th class="p-3.5">Classification Label</th>
 <th class="p-3.5">Clearance Barrier</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-hairline text-ink-secondary">
 <tr v-for="c in confidentiality" :key="c.id" class="hover:bg-surface-hover transition">
 <td class="p-3.5 font-mono">{{ c.rank_order || c.id }}</td>
 <td class="p-3.5 font-mono font-bold text-primary">{{ c.code }}</td>
 <td class="p-3.5 font-medium">{{ c.name }}</td>
 <td class="p-3.5 text-ink-muted">Requires explicit clearance or owning unit membership</td>
 </tr>
 </tbody>
 </table>

 <!-- Correspondence Types -->
 <table v-else-if="activeTab === 'types'" class="w-full text-xs text-left">
 <thead class="bg-canvas-soft border-b border-hairline text-ink-muted font-semibold uppercase tracking-wider">
 <tr>
 <th class="p-3.5">Code</th>
 <th class="p-3.5">Type Name</th>
 <th class="p-3.5">Routing Mode</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-hairline text-ink-secondary">
 <tr v-for="t in types" :key="t.id" class="hover:bg-surface-hover transition">
 <td class="p-3.5 font-mono font-bold text-primary">{{ t.code }}</td>
 <td class="p-3.5 font-medium">{{ t.name }}</td>
 <td class="p-3.5 text-ink-muted">{{ t.direction || 'Inbound / Outbound / Internal' }}</td>
 </tr>
 </tbody>
 </table>

 <!-- Priorities -->
 <table v-else-if="activeTab === 'priorities'" class="w-full text-xs text-left">
 <thead class="bg-canvas-soft border-b border-hairline text-ink-muted font-semibold uppercase tracking-wider">
 <tr>
 <th class="p-3.5">Weight</th>
 <th class="p-3.5">Priority Label</th>
 <th class="p-3.5">Response SLA</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-hairline text-ink-secondary">
 <tr v-for="p in priorities" :key="p.id" class="hover:bg-surface-hover transition">
 <td class="p-3.5 font-mono font-bold">{{ p.weight || p.id }}</td>
 <td class="p-3.5 font-medium">{{ p.name }}</td>
 <td class="p-3.5 text-ink-muted">{{ p.sla_hours ? p.sla_hours + ' hours' : 'Standard institutional queue' }}</td>
 </tr>
 </tbody>
 </table>

 <!-- Dispatch Methods -->
 <table v-else-if="activeTab === 'dispatch'" class="w-full text-xs text-left">
 <thead class="bg-canvas-soft border-b border-hairline text-ink-muted font-semibold uppercase tracking-wider">
 <tr>
 <th class="p-3.5">Code</th>
 <th class="p-3.5">Method Name</th>
 <th class="p-3.5">Tracking Requirement</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-hairline text-ink-secondary">
 <tr v-for="d in dispatchMethods" :key="d.id" class="hover:bg-surface-hover transition">
 <td class="p-3.5 font-mono font-bold text-primary">{{ d.code }}</td>
 <td class="p-3.5 font-medium">{{ d.name }}</td>
 <td class="p-3.5 text-ink-muted">Waybill / Courier tracking / Hand delivery signoff</td>
 </tr>
 </tbody>
 </table>
 </div>
 </div>
</template>
