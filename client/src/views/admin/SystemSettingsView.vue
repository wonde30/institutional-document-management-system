<script setup lang="ts">
import { ref } from 'vue'
import { useToast } from '@/composables/useToast'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import { Settings, ShieldCheck, Database, Lock, Save, AlertCircle } from 'lucide-vue-next'

const toast = useToast()
const isSaving = ref(false)

const settings = ref({
 institution_name: 'Wollo University (ወሎ ዩኒቨርሲቲ)',
 academic_year: '2019 E.C. / 2026-2027 G.C.',
 storage_driver: 'local_encrypted_array',
 default_retention_code: 'INST_PERM (1200 months)',
 pki_status: 'Internal SHA-256 Digest Engine (National CA Pending)',
 session_timeout_minutes: '60',
 max_upload_size_mb: '100',
})

async function handleSaveSettings() {
 isSaving.value = true
 setTimeout(() => {
 isSaving.value = false
 toast.success('System settings updated and synchronized.')
 }, 500)
}
</script>

<template>
 <div class="space-y-6 max-w-4xl">
 <div>
 <div class="text-[11px] font-bold tracking-widest text-primary uppercase mb-1">
 ADMINISTRATION / SYSTEM CONFIGURATION
 </div>
 <h1 class="text-2xl font-bold tracking-tight text-ink-secondary">
 Institutional Settings & Environment Boundaries
 </h1>
 <p class="text-xs sm:text-sm text-ink-muted mt-0.5">
 Configure institutional metadata, default retention profiles, and storage drivers. Secrets and private keys remain masked.
 </p>
 </div>

 <form @submit.prevent="handleSaveSettings" class="space-y-6">
 <!-- General Institutional Profile -->
 <div class="p-5 rounded-xl bg-surface border border-hairline shadow-xs space-y-4">
 <h2 class="text-sm font-semibold text-ink-secondary flex items-center gap-2">
 <Settings class="w-4 h-4 text-primary" />
 General System Profile
 </h2>

 <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
 <div class="space-y-1.5">
 <label class="block font-semibold text-ink-secondary">
 Institution Legal Name
 </label>
 <BaseInput v-model="settings.institution_name" required />
 </div>

 <div class="space-y-1.5">
 <label class="block font-semibold text-ink-secondary">
 Academic & Fiscal Period
 </label>
 <BaseInput v-model="settings.academic_year" required />
 </div>
 </div>
 </div>

 <!-- Preservation & Archival Standards -->
 <div class="p-5 rounded-xl bg-surface border border-hairline shadow-xs space-y-4">
 <h2 class="text-sm font-semibold text-ink-secondary flex items-center gap-2">
 <Database class="w-4 h-4 text-primary" />
 Storage & Retention Baseline
 </h2>

 <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
 <div class="space-y-1.5">
 <label class="block font-semibold text-ink-secondary">
 Storage Driver
 </label>
 <BaseInput v-model="settings.storage_driver" disabled />
 <p class="text-[11px] text-ink-muted">
 Configured via environment boundary (`STORAGE_DISK=local`).
 </p>
 </div>

 <div class="space-y-1.5">
 <label class="block font-semibold text-ink-secondary">
 Default Retention Rule
 </label>
 <BaseInput v-model="settings.default_retention_code" disabled />
 <p class="text-[11px] text-ink-muted">
 Permanent institutional retention schedule (100 years).
 </p>
 </div>
 </div>
 </div>

 <!-- Security & Trust Providers -->
 <div class="p-5 rounded-xl bg-surface border border-hairline shadow-xs space-y-4">
 <h2 class="text-sm font-semibold text-ink-secondary flex items-center gap-2">
 <ShieldCheck class="w-4 h-4 text-primary" />
 Digital Trust & Security Boundaries
 </h2>

 <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
 <div class="space-y-1.5">
 <label class="block font-semibold text-ink-secondary">
 Digital Signature Trust Provider
 </label>
 <BaseInput v-model="settings.pki_status" disabled />
 </div>

 <div class="space-y-1.5">
 <label class="block font-semibold text-ink-secondary">
 Session Idle Timeout (Minutes)
 </label>
 <BaseInput v-model="settings.session_timeout_minutes" type="number" required />
 </div>
 </div>

 <div class="p-3.5 rounded-lg bg-canvas-soft border border-hairline text-xs text-ink-muted flex items-start gap-2.5">
 <Lock class="w-4 h-4 shrink-0 mt-0.5 text-primary" />
 <span>
 Database credentials, encryption keys (`APP_KEY`), and signing certificates are strictly managed by system environment configuration and never exposed to the browser interface.
 </span>
 </div>
 </div>

 <div class="flex justify-end">
 <BaseButton
 type="submit"
 variant="primary"
 :loading="isSaving"
 >
 <Save class="w-4 h-4 mr-1.5" />
 Save Configuration
 </BaseButton>
 </div>
 </form>
 </div>
</template>
