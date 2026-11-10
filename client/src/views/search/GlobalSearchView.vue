<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { documentsApi, correspondenceApi, institutionalApi, searchApi } from '@/api/client'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import LoadingSkeleton from '@/components/ui/LoadingSkeleton.vue'
import {
 Search,
 Filter,
 FileText,
 Mail,
 BookOpen,
 Archive,
 ArrowRight,
 Shield,
 Building2,
 Calendar,
} from 'lucide-vue-next'

const router = useRouter()
const route = useRoute()

const query = ref((route.query.q as string) || '')
const selectedDomain = ref<'all' | 'documents' | 'correspondence' | 'research'>('all')
const isLoading = ref(false)

interface SearchResult {
 id: number
 title: string
 reference: string
 domain: 'document' | 'correspondence' | 'research'
 office: string
 date: string
 snippet: string
 route: string
}

const results = ref<SearchResult[]>([])

async function executeSearch() {
 if (!query.value.trim()) {
 results.value = []
 return
 }

 isLoading.value = true
 try {
 const q = query.value.trim().toLowerCase()
 const collected: SearchResult[] = []

 // 1. Documents search (Bilingual OCR + Metadata)
 if (selectedDomain.value === 'all' || selectedDomain.value === 'documents') {
 try {
 const docRes = await searchApi.searchDocuments({ q, per_page: 15 })
 const docs = docRes.data.data || []
 docs.forEach((d: any) => {
 collected.push({
 id: d.id,
 title: d.title,
 reference: d.reference_number,
 domain: 'document',
 office: d.owner_unit?.name || 'Academic Unit',
 date: d.registered_at ? d.registered_at.split('T')[0] : (d.created_at ? d.created_at.split('T')[0] : '—'),
 snippet: d.summary || 'Official registered institutional document with bilingual OCR indexing.',
 route: `/documents/${d.id}`,
 })
 })
 } catch {}
 }

 // 2. Correspondence search
 if (selectedDomain.value === 'all' || selectedDomain.value === 'correspondence') {
 try {
 const corrRes = await correspondenceApi.list({ search: q, per_page: 10 })
 const corrs = (corrRes.data as any).data || []
 corrs.forEach((c: any) => {
 collected.push({
 id: c.id,
 title: c.document?.title || c.title || 'Official Correspondence',
 reference: c.document?.reference_number || c.external_reference || 'REF-CORR',
 domain: 'correspondence',
 office: c.first_receiving_unit?.name || 'Registrar / Incoming Office',
 date: c.correspondence_date || (c.created_at ? c.created_at.split('T')[0] : '—'),
 snippet: c.summary || 'Inter-office or external correspondence logged in central ledger.',
 route: `/correspondence`,
 })
 })
 } catch {}
 }

 // 3. Research search
 if (selectedDomain.value === 'all' || selectedDomain.value === 'research') {
 try {
 const resRes = await institutionalApi.research()
 const researches = resRes.data.data || []
 researches.forEach((r: any) => {
 const title = r.document?.title || r.title || ''
 if (title.toLowerCase().includes(q) || (r.document?.reference_number || '').toLowerCase().includes(q)) {
 collected.push({
 id: r.id,
 title: title,
 reference: r.document?.reference_number || `WU/RES/2026/${r.id}`,
 domain: 'research',
 office: r.lead_office?.name || 'Academic Department',
 date: r.completed_at ? r.completed_at.split('T')[0] : (r.created_at ? r.created_at.split('T')[0] : '—'),
 snippet: 'Finalized departmental research monograph evaluated and published.',
 route: `/research`,
 })
 }
 })
 } catch {}
 }

 results.value = collected
 } finally {
 isLoading.value = false
 }
}

watch(
 () => route.query.q,
 (newQ) => {
 query.value = (newQ as string) || ''
 executeSearch()
 }
)

onMounted(() => {
 if (query.value) executeSearch()
})
</script>

<template>
 <div class="space-y-6">
 <!-- Search Header -->
 <div class="max-w-3xl space-y-3">
 <div class="notion-eyebrow text-primary">
 RECORDS & ARCHIVES / AUTHORIZED SEARCH
 </div>
 <h1 class="notion-heading-2 text-ink">
 Repository & Registry Search Portal
 </h1>
 <p class="notion-body-sm text-ink-muted">
 Query official documents, incoming/outgoing letters, and finalized research within your authorized scope.
 </p>

 <form @submit.prevent="executeSearch" class="flex gap-2 pt-2">
 <BaseInput
 v-model="query"
 type="search"
 placeholder="Search by title, reference number, external sender, or keywords..."
 class="flex-1"
 >
 <template #prefix>
 <Search class="w-4 h-4 text-ink-faint" />
 </template>
 </BaseInput>

 <BaseButton
 type="submit"
 variant="primary"
 class="bg-primary hover:bg-primary-hover text-white shrink-0 shadow-xs"
 :loading="isLoading"
 >
 Search
 </BaseButton>
 </form>

 <!-- Domain Filters -->
 <div class="flex items-center gap-2 pt-1 flex-wrap text-xs">
 <span class="text-ink-muted font-medium text-[11px]">Domain:</span>
 <button
 type="button"
 @click="selectedDomain = 'all'; executeSearch()"
 class="px-2.5 py-1 rounded-lg text-xs font-medium transition cursor-pointer"
 :class="selectedDomain === 'all'
 ? 'bg-primary text-white shadow-xs'
 : 'bg-surface border border-hairline text-ink-muted hover:text-ink dark:text-ink-faint dark:hover:text-white'"
 >
 All Domains
 </button>
 <button
 type="button"
 @click="selectedDomain = 'documents'; executeSearch()"
 class="px-2.5 py-1 rounded-lg text-xs font-medium transition cursor-pointer"
 :class="selectedDomain === 'documents'
 ? 'bg-primary text-white shadow-xs'
 : 'bg-surface border border-hairline text-ink-muted hover:text-ink dark:text-ink-faint dark:hover:text-white'"
 >
 Documents
 </button>
 <button
 type="button"
 @click="selectedDomain = 'correspondence'; executeSearch()"
 class="px-2.5 py-1 rounded-lg text-xs font-medium transition cursor-pointer"
 :class="selectedDomain === 'correspondence'
 ? 'bg-primary text-white shadow-xs'
 : 'bg-surface border border-hairline text-ink-muted hover:text-ink dark:text-ink-faint dark:hover:text-white'"
 >
 Correspondence
 </button>
 <button
 type="button"
 @click="selectedDomain = 'research'; executeSearch()"
 class="px-2.5 py-1 rounded-lg text-xs font-medium transition cursor-pointer"
 :class="selectedDomain === 'research'
 ? 'bg-primary text-white shadow-xs'
 : 'bg-surface border border-hairline text-ink-muted hover:text-ink dark:text-ink-faint dark:hover:text-white'"
 >
 Research
 </button>
 </div>
 </div>

 <!-- Loading Skeleton -->
 <LoadingSkeleton v-if="isLoading" :count="4" class="max-w-3xl" />

 <!-- Results List -->
 <div v-else class="max-w-3xl space-y-3">
 <div v-if="results.length > 0" class="text-xs font-semibold text-ink-muted">
 Found {{ results.length }} authorized records matching query
 </div>

 <div
 v-for="item in results"
 :key="item.domain + '-' + item.id"
 @click="router.push(item.route)"
 class="p-4 rounded-xl bg-surface border border-hairline hover:border-primary dark:hover:border-[#62aef0] transition shadow-xs cursor-pointer space-y-2 group"
 >
 <div class="flex items-start justify-between gap-3">
 <div class="space-y-1">
 <div class="flex items-center gap-2 flex-wrap">
 <span class="font-mono text-xs font-semibold text-primary">
 {{ item.reference }}
 </span>
 <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wider bg-canvas-soft text-ink-muted">
 {{ item.domain }}
 </span>
 </div>
 <h2 class="text-sm font-semibold text-ink group-hover:text-primary transition">
 {{ item.title }}
 </h2>
 </div>

 <ArrowRight class="w-4 h-4 text-ink-faint group-hover:text-primary transition shrink-0 mt-1" />
 </div>

 <p class="text-xs text-ink-muted line-clamp-2 leading-relaxed">
 {{ item.snippet }}
 </p>

 <div class="flex items-center gap-4 text-[11px] text-ink-muted pt-1">
 <span class="flex items-center gap-1">
 <Building2 class="w-3.5 h-3.5" />
 {{ item.office }}
 </span>
 <span class="flex items-center gap-1">
 <Calendar class="w-3.5 h-3.5" />
 {{ item.date }}
 </span>
 </div>
 </div>

 <!-- Empty State -->
 <div v-if="!isLoading && results.length === 0 && query" class="p-8 text-center rounded-xl bg-surface border border-hairline space-y-2">
 <div class="text-sm font-semibold text-ink-secondary">No matching records found</div>
 <p class="text-xs text-ink-muted">
 No records matching "{{ query }}" were found within your current authorized unit scope.
 </p>
 </div>

 <div v-if="!isLoading && !query" class="p-8 text-center rounded-xl bg-surface border border-hairline text-xs text-ink-muted">
 Enter search keywords or reference serial numbers above to inspect authorized university records.
 </div>
 </div>
 </div>
</template>
