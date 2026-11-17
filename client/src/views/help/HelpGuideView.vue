<script setup lang="ts">
import { ref, computed } from 'vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import {
 HelpCircle,
 Search,
 BookOpen,
 Mail,
 Files,
 Archive,
 Users,
} from 'lucide-vue-next'

const searchQuery = ref('')

const guides = [
 {
 module: 'Documents & Versioning',
 icon: Files,
 summary: 'Centralized document repository with 3NF immutable revision history.',
 instructions: [
 'Documents are registered with a university reference number, owner unit, and confidentiality level.',
 'Revising an approved or registered document requires creating a new sequential version with a change note.',
 'Previous versions and their exact byte contents remain immutable and auditable.',
 ],
 },
 {
 module: 'Correspondence & Letters',
 icon: Mail,
 summary: 'Inbound, outbound, and inter-office memoranda tracking.',
 instructions: [
 'Inbound external letters can enter directly at an authorized department or via Central Registry.',
 'Outgoing official letters require procedural review. Dispatch is bound to the exact approved document version.',
 'Inter-office memos support multiple destination offices with independent acknowledgment tracking.',
 ],
 },
 {
 module: 'Finalized Research',
 icon: BookOpen,
 summary: 'Departmental repository for completed, externally evaluated research.',
 instructions: [
 'Research proposal screening, reviewer scoring, and funding selection occur strictly outside the DMS.',
 'Authorized departments upload finalized research monographs and manage repository publication.',
 'Publishing authority is department-scoped: one department cannot alter another department’s research.',
 ],
 },
 {
 module: 'Staff Records & Custody',
 icon: Users,
 summary: 'Personnel dossiers and physical paper original custody.',
 instructions: [
 'Every scanned paper original must record physical retention requirement (required, not required, or unknown).',
 'Crucial institutional rule: uploading or verifying a scan NEVER authorizes destruction of the physical original.',
 'Physical disposal requires a distinct authorized action backed by an official Senate or Legal Counsel order.',
 ],
 },
 {
 module: 'Digital Archive & Holds',
 icon: Archive,
 summary: 'Preservation management and 100-year retention schedules.',
 instructions: [
 'Permanent institutional records follow the INST_PERM schedule (1200 months / 100 years).',
 'Active legal holds suspend retention expiration and prevent disposal.',
 'Disposal execution strictly enforces multi-party approval before purge.',
 ],
 },
]

const filteredGuides = computed(() => {
 if (!searchQuery.value.trim()) return guides
 const q = searchQuery.value.toLowerCase()
 return guides.filter(
 (g) =>
 g.module.toLowerCase().includes(q) ||
 g.summary.toLowerCase().includes(q) ||
 g.instructions.some((i) => i.toLowerCase().includes(q))
 )
})
</script>

<template>
 <div class="space-y-6 max-w-4xl">
 <div class="border-b border-hairline pb-4">
 <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-ink flex items-center gap-2.5">
 <HelpCircle class="w-5 h-5 sm:w-6 sm:h-6 text-primary shrink-0" />
 <span>Wollo University DMS Help Guide</span>
 </h1>
 <p class="text-xs text-ink-muted mt-0.5">
 Operational guidelines, workflow rules, and institutional access policies by functional module.
 </p>
 </div>

 <div class="max-w-md pt-1">
 <BaseInput
 v-model="searchQuery"
 type="search"
 placeholder="Search guidance by module or keyword..."
 >
 <template #prefix>
 <Search class="w-4 h-4 text-ink-muted" />
 </template>
 </BaseInput>
 </div>

 <!-- Guides List -->
 <div class="space-y-4">
 <div
 v-for="guide in filteredGuides"
 :key="guide.module"
 class="p-5 rounded-xl bg-surface border border-hairline space-y-3"
 >
 <div class="flex items-center gap-2.5">
 <div class="w-8 h-8 rounded-lg bg-primary-subtle text-primary flex items-center justify-center shrink-0">
 <component :is="guide.icon" class="w-4 h-4" />
 </div>
 <div>
 <h2 class="text-sm font-semibold text-ink-secondary">{{ guide.module }}</h2>
 <p class="text-xs text-ink-muted">{{ guide.summary }}</p>
 </div>
 </div>

 <ul class="space-y-1.5 pl-10 text-xs text-ink-secondary list-disc marker:text-primary">
 <li v-for="(inst, i) in guide.instructions" :key="i" class="leading-relaxed">
 {{ inst }}
 </li>
 </ul>
 </div>
 </div>

 <!-- Where to go for help. The contact route is described, because
 the address itself is not published by the server. -->
 <div class="p-5 rounded-xl bg-canvas-soft border border-hairline space-y-2">
 <div class="text-xs font-semibold text-ink-secondary flex items-center gap-2">
 <HelpCircle class="w-4 h-4 text-primary" />
 Support and access delegation
 </div>
 <p class="text-xs text-ink-muted leading-relaxed">
 For technical assistance, unit scope adjustments, or role delegation requests,
 contact the ICT Directorate through your unit's administrative officer. The
 support address for the deployment is published on the institution's public
 portal.
 </p>
 </div>
 </div>
</template>
