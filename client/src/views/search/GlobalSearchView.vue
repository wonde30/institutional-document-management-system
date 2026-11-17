<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { documentsApi, correspondenceApi, institutionalApi, searchApi } from '@/api/client'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import LoadingSkeleton from '@/components/ui/LoadingSkeleton.vue'
import {
 Search,
 ArrowRight,
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

/**
 * The domains that could not be searched, with the reason.
 *
 * A domain that fails is reported rather than silently omitted, so an
 * operator can tell the difference between "no matching records" and "this
 * registry is not available within your scope".
 */
const domainErrors = ref<Record<string, string>>({})

/**
 * A readable reason for a failed domain.
 */
function domainErrorMessage(err: unknown, domain: string): string {
 const apiErr = err as { response?: { status?: number; data?: { message?: string } } }
 const status = apiErr.response?.status

 if (status === 403) {
  return `The ${domain} registry is not available within your authorized scope.`
 }
 if (status === 401) {
  return 'Your session has expired. Sign in again to continue searching.'
 }
 return apiErr.response?.data?.message || `The ${domain} registry could not be searched.`
}

async function executeSearch() {
 if (!query.value.trim()) {
  results.value = []
  domainErrors.value = {}
  return
 }

 isLoading.value = true
 domainErrors.value = {}

 try {
  const q = query.value.trim()
  const collected: SearchResult[] = []
  const errors: Record<string, string> = {}

  // The document repository, searched through the bilingual OCR index.
  if (selectedDomain.value === 'all' || selectedDomain.value === 'documents') {
  try {
   const res = await searchApi.searchDocuments({ q, per_page: 15 })
   const documents = res.data.data || []
   documents.forEach((d) => {
   collected.push({
    id: d.id,
    title: d.title,
    reference: d.reference_number ?? '',
    domain: 'document',
    office: d.owner_unit?.name ?? '',
    date: d.registered_at ? d.registered_at.slice(0, 10) : '',
    snippet: d.summary ?? '',
    route: `/documents/${d.id}`,
   })
   })
  } catch (err) {
   errors.documents = domainErrorMessage(err, 'document')
  }
  }

  // The correspondence registry.
  if (selectedDomain.value === 'all' || selectedDomain.value === 'correspondence') {
  try {
   const res = await correspondenceApi.list({ search: q, per_page: 10 })
   const records = res.data.data || []
   records.forEach((c) => {
   collected.push({
    id: c.id,
    title: c.document?.title ?? c.external_reference ?? '',
    reference: c.document?.reference_number ?? c.external_reference ?? '',
    domain: 'correspondence',
    office: c.first_receiving_unit?.name ?? '',
    date: c.correspondence_date ? c.correspondence_date.slice(0, 10) : '',
    snippet: c.document?.summary ?? '',
    route: `/correspondence`,
   })
   })
  } catch (err) {
   errors.correspondence = domainErrorMessage(err, 'correspondence')
  }
  }

  // The finalized research repository.
  if (selectedDomain.value === 'all' || selectedDomain.value === 'research') {
  try {
   const res = await institutionalApi.research()
   const records = res.data.data || []
   const needle = q.toLowerCase()
   records.forEach((r) => {
   const title = r.document?.title ?? ''
   const reference = r.document?.reference_number ?? ''
   if (title.toLowerCase().includes(needle) || reference.toLowerCase().includes(needle)) {
    collected.push({
     id: r.id,
     title,
     reference,
     domain: 'research',
     office: r.document?.ownerUnit?.name ?? '',
     date: r.completed_on ?? '',
     snippet: r.document?.summary ?? '',
     route: `/research`,
    })
   }
   })
  } catch (err) {
   errors.research = domainErrorMessage(err, 'research')
  }
  }

  results.value = collected
  domainErrors.value = errors
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
 <div class="max-w-3xl space-y-3 border-b border-hairline pb-4">
 <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-ink flex items-center gap-2.5">
 <Search class="w-5 h-5 sm:w-6 sm:h-6 text-primary shrink-0" />
 <span>Repository & Registry Search Portal</span>
 </h1>
 <p class="text-xs text-ink-muted mt-0.5">
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
 class="shrink-0"
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

 <!-- A registry that could not be searched is reported, not hidden. -->
 <div
 v-if="Object.keys(domainErrors).length > 0"
 class="p-3 rounded-lg bg-[#fff4e6] dark:bg-[#3b220c] border border-[#e0a03a]/40 text-xs text-[#dd5b00] dark:text-[#f59e0b] space-y-1"
 >
 <div v-for="(message, domain) in domainErrors" :key="domain">
 {{ message }}
 </div>
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
 {{ item.reference || '—' }}
 </span>
 <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wider bg-canvas-soft text-ink-muted">
 {{ item.domain }}
 </span>
 </div>
 <h2 class="text-sm font-semibold text-ink group-hover:text-primary transition">
 {{ item.title || 'Untitled record' }}
 </h2>
 </div>

 <ArrowRight class="w-4 h-4 text-ink-faint group-hover:text-primary transition shrink-0 mt-1" />
 </div>

 <p class="text-xs text-ink-muted line-clamp-2 leading-relaxed">
 {{ item.snippet || 'No summary is recorded for this record.' }}
 </p>

 <div class="flex items-center gap-4 text-[11px] text-ink-muted pt-1">
 <span class="flex items-center gap-1">
 <Building2 class="w-3.5 h-3.5" />
 {{ item.office || '—' }}
 </span>
 <span class="flex items-center gap-1">
 <Calendar class="w-3.5 h-3.5" />
 {{ item.date || '—' }}
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
