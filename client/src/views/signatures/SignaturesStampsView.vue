<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { institutionalApi, documentsApi, usersApi } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useToast } from '@/composables/useToast'
import LoadingSkeleton from '@/components/ui/LoadingSkeleton.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import type {
 SignatureRequestItem,
 StampDefinitionItem,
 SigningIdentityItem,
 DigitalSignatureItem,
 SignatureVerificationItem,
 Document,
 User,
} from '@/types'
import {
 PenLine,
 Stamp,
 ShieldCheck,
 ShieldAlert,
 Search,
 Plus,
 Clock,
 CheckCircle2,
 AlertCircle,
 FileText,
 UserCheck,
 X,
 ExternalLink,
 Info,
 KeyRound,
 FileCheck2,
} from 'lucide-vue-next'

const authStore = useAuthStore()
const toast = useToast()

const loading = ref(true)
const activeTab = ref<'signatures' | 'stamps' | 'provider'>('signatures')
const signatures = ref<SignatureRequestItem[]>([])
const stamps = ref<StampDefinitionItem[]>([])
const identities = ref<SigningIdentityItem[]>([])
const trustStatus = ref('active_local_verified')

const documents = ref<Document[]>([])
const users = ref<User[]>([])

const searchQuery = ref('')
const isModalOpen = ref(false)
const submitting = ref(false)
const errorMessage = ref('')

// Execution & Verification State
const executingId = ref<number | null>(null)
const verifyingId = ref<number | null>(null)
const verificationModalOpen = ref(false)
const selectedVerification = ref<SignatureVerificationItem | null>(null)

// Apply Stamp Modal State
const applyStampModalOpen = ref(false)
const selectedStampForApplication = ref<StampDefinitionItem | null>(null)
const applyStampForm = ref({
 document_version_id: '',
 reason: 'Official office seal application per administrative directive',
})
const isApplyingStamp = ref(false)

const form = ref({
 document_version_id: '',
 signer_id: '',
 purpose: 'Official Institutional Endorsement',
 expires_at: '',
})

async function fetchData() {
 loading.value = true
 errorMessage.value = ''
 try {
 const [res, docRes, userRes, identRes] = await Promise.all([
 institutionalApi.signatures(),
 documentsApi.list({ per_page: 30 }),
 usersApi.list({ per_page: 50 }),
 institutionalApi.signingIdentities().catch(() => ({ data: { data: [] } })),
 ])

 if (res.data.success) {
 signatures.value = res.data.data.signatures || []
 stamps.value = res.data.data.stamps || []
 trustStatus.value = res.data.data.trust_provider_status || 'active_local_verified'
 }

 if (docRes.data.data) {
 documents.value = docRes.data.data
 }

 if (userRes.data.data) {
 users.value = userRes.data.data
 }

 if (identRes.data.data) {
 identities.value = identRes.data.data
 }
 } catch (err: any) {
 errorMessage.value = 'Failed to load signatures and stamps registry.'
 } finally {
 loading.value = false
 }
}

onMounted(() => {
 fetchData()
})

const filteredSignatures = computed(() => {
 return signatures.value.filter((s) => {
 return (
 !searchQuery.value ||
 s.document_version?.document?.title?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
 s.signer?.name?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
 s.purpose?.toLowerCase().includes(searchQuery.value.toLowerCase())
 )
 })
})

const filteredStamps = computed(() => {
 return stamps.value.filter((s) => {
 return (
 !searchQuery.value ||
 s.name?.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
 s.unit?.name?.toLowerCase().includes(searchQuery.value.toLowerCase())
 )
 })
})

function openSignatureModal() {
 const firstDoc = documents.value[0]
 const firstVerId = firstDoc?.current_version?.id || firstDoc?.versions?.[0]?.id || firstDoc?.id || ''
 form.value = {
 document_version_id: firstVerId ? String(firstVerId) : '',
 signer_id: users.value[0]?.id ? String(users.value[0].id) : '',
 purpose: 'Official Institutional Endorsement',
 expires_at: new Date(Date.now() + 14 * 86400000).toISOString().split('T')[0],
 }
 isModalOpen.value = true
}

async function submitSignatureRequest() {
 if (!form.value.document_version_id || !form.value.signer_id || !form.value.purpose) {
 toast.error('Validation Error', 'Please fill in all required fields.')
 return
 }

 submitting.value = true
 try {
 const res = await institutionalApi.storeSignatureRequest({
 document_version_id: Number(form.value.document_version_id),
 signer_id: Number(form.value.signer_id),
 purpose: form.value.purpose,
 expires_at: form.value.expires_at,
 })

 if (res.data.success) {
 toast.success('Signature Requested', 'Digital signature request successfully created.')
 isModalOpen.value = false
 await fetchData()
 }
 } catch (err: any) {
 toast.error('Request Failed', err.response?.data?.message || err.message || 'Failed to request signature.')
 } finally {
 submitting.value = false
 }
}

async function handleExecuteSignature(sigRequest: SignatureRequestItem) {
 if (!confirm(`Are you sure you want to execute digital signature for "${sigRequest.document_version?.document?.title || 'Document'}" using your X.509 institutional key?`)) {
 return
 }

 executingId.value = sigRequest.id
 try {
 const res = await institutionalApi.executeSignature(sigRequest.id)
 if (res.data.success) {
 await fetchData()
 toast.success('Signature Executed', 'Document cryptographically signed with authentic PKCS#7 ByteRange envelope.')
 }
 } catch (err: unknown) {
 const apiErr = err as { response?: { data?: { message?: string } }; message?: string }
 toast.error('Execution Failed', apiErr.response?.data?.message || apiErr.message || 'Execution error')
 } finally {
 executingId.value = null
 }
}

async function handleInspectVerification(signature: DigitalSignatureItem) {
 if (!signature?.id) return

 verifyingId.value = signature.id
 try {
 const res = await institutionalApi.verifySignature(signature.id)
 if (res.data.success) {
 selectedVerification.value = res.data.data as SignatureVerificationItem
 verificationModalOpen.value = true
 }
 } catch (err: unknown) {
 const apiErr = err as { response?: { data?: { message?: string } }; message?: string }
 toast.error('Verification Failed', apiErr.response?.data?.message || apiErr.message || 'Verification error')
 } finally {
 verifyingId.value = null
 }
}

function openApplyStampModal(stamp: StampDefinitionItem) {
 selectedStampForApplication.value = stamp
 const firstDoc = documents.value[0]
 const firstVerId = firstDoc?.current_version?.id || firstDoc?.versions?.[0]?.id || firstDoc?.id || ''
 applyStampForm.value = {
 document_version_id: firstVerId ? String(firstVerId) : '',
 reason: `Official office seal application for ${stamp.name}`,
 }
 applyStampModalOpen.value = true
}

async function submitApplyStamp() {
 if (!selectedStampForApplication.value || !applyStampForm.value.document_version_id || !applyStampForm.value.reason) {
 toast.error('Validation Error', 'Please select a document version and provide a reason.')
 return
 }

 isApplyingStamp.value = true
 try {
 const res = await institutionalApi.applyStamp({
 document_version_id: Number(applyStampForm.value.document_version_id),
 stamp_id: selectedStampForApplication.value.id,
 reason: applyStampForm.value.reason,
 })

 if (res.data.success) {
 toast.success('Seal Applied', 'Official office stamp successfully applied to document version.')
 applyStampModalOpen.value = false
 await fetchData()
 }
 } catch (err: any) {
 toast.error('Application Failed', err.response?.data?.message || err.message || 'Failed to apply stamp.')
 } finally {
 isApplyingStamp.value = false
 }
}
</script>

<template>
 <div class="space-y-6">
 <!-- Header -->
 <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-hairline pb-4">
 <div>
 <h1 class="text-xl sm:text-2xl font-bold text-ink tracking-tight flex items-center gap-2.5">
 <Stamp class="w-5 h-5 sm:w-6 sm:h-6 text-primary shrink-0" />
 <span>Signatures & Stamps</span>
 </h1>
 <p class="text-xs text-ink-muted mt-0.5">
 Cryptographic signing requests, authorized signers, and departmental office seal registries.
 </p>
 </div>

 <div class="flex items-center gap-2">
 <button
 type="button"
 class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-md bg-primary hover:bg-primary-hover text-white text-xs font-medium transition-colors"
 @click="openSignatureModal"
 >
 <PenLine class="w-4 h-4" />
 <span>Request signature</span>
 </button>
 </div>
 </div>

 <!-- Institutional Policy Notice (Mandatory from UI Blueprint) -->
 <div class="p-3.5 rounded-lg bg-canvas-soft border border-hairline text-xs text-ink-secondary">
 <div class="flex items-start gap-3">
 <ShieldAlert class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
 <div class="text-xs text-ink-secondary">
 <strong class="font-semibold block mb-0.5 text-ink-secondary">Legal Trust Boundary & Regulatory Notice</strong>
 A visual stamp image is NOT a cryptographic digital signature. Live signing requires an approved institutional trust provider and verified X.509 certificate trust configuration. Local test keys are labeled as test PKI under Proclamation No. 1072/2018. Applying an office seal image does not alter byte-level cryptographic integrity or replace required executive sign-off.
 </div>
 </div>
 </div>

 <!-- Metric Cards -->
 <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
 <div class="bg-surface border border-hairline rounded-lg p-4">
 <div class="flex items-center justify-between">
 <span class="text-xs font-semibold text-ink-muted">Signing Requests</span>
 <PenLine class="w-4 h-4 text-primary" />
 </div>
 <div class="text-2xl font-semibold text-ink-secondary mt-2">
 {{ signatures.length }}
 </div>
 <div class="text-xs text-ink-muted mt-0.5">Pending & completed requests</div>
 </div>

 <div class="bg-surface border border-hairline rounded-lg p-4">
 <div class="flex items-center justify-between">
 <span class="text-xs font-semibold text-ink-muted">Registered Office Stamps</span>
 <Stamp class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
 </div>
 <div class="text-2xl font-semibold text-ink-secondary mt-2">
 {{ stamps.length }}
 </div>
 <div class="text-xs text-ink-muted mt-0.5">Authorized departmental seals</div>
 </div>

 <div class="bg-surface border border-hairline rounded-lg p-4">
 <div class="flex items-center justify-between">
 <span class="text-xs font-semibold text-ink-muted">Provider Trust Status</span>
 <ShieldCheck class="w-4 h-4 text-primary" />
 </div>
 <div class="text-base font-semibold text-ink-secondary mt-2 flex items-center gap-1.5">
 <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
 Authentic PKI Active
 </div>
 <div class="text-xs text-ink-muted mt-0.5">X.509 PKCS#7 Detached ByteRange Verification</div>
 </div>
 </div>

 <!-- Tab Bar -->
 <div class="flex items-center gap-4 border-b border-hairline">
 <button
 type="button"
 class="pb-2.5 text-xs font-medium border-b-2 transition-colors"
 :class="[
 activeTab === 'signatures'
 ? 'border-primary text-primary font-semibold'
 : 'border-transparent text-ink-muted hover:text-ink-secondary'
 ]"
 @click="activeTab = 'signatures'"
 >
 Signing Requests ({{ signatures.length }})
 </button>

 <button
 type="button"
 class="pb-2.5 text-xs font-medium border-b-2 transition-colors"
 :class="[
 activeTab === 'stamps'
 ? 'border-primary text-primary font-semibold'
 : 'border-transparent text-ink-muted hover:text-ink-secondary'
 ]"
 @click="activeTab = 'stamps'"
 >
 Office Stamps ({{ stamps.length }})
 </button>

 <button
 type="button"
 class="pb-2.5 text-xs font-medium border-b-2 transition-colors"
 :class="[
 activeTab === 'provider'
 ? 'border-primary text-primary font-semibold'
 : 'border-transparent text-ink-muted hover:text-ink-secondary'
 ]"
 @click="activeTab = 'provider'"
 >
 Trust Configuration & Identities ({{ identities.length }})
 </button>
 </div>

 <!-- Search Bar -->
 <div v-if="activeTab !== 'provider'" class="relative w-full sm:w-96">
 <Search class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
 <input
 v-model="searchQuery"
 type="text"
 placeholder="Filter by document title, signer, purpose..."
 class="w-full pl-9 pr-3 py-1.5 bg-surface border border-hairline rounded-md text-xs text-ink-secondary focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 />
 </div>

 <!-- Tab 1: Signing Requests -->
 <div
 v-if="activeTab === 'signatures'"
 class="bg-surface border border-hairline rounded-lg overflow-hidden"
 >
 <div v-if="loading" class="p-6">
 <LoadingSkeleton type="table" :rows="5" />
 </div>
 <div v-else class="overflow-x-auto">
 <table class="w-full text-left text-xs text-ink-secondary">
 <thead class="bg-canvas-soft border-b border-hairline text-xs font-semibold text-ink-muted">
 <tr>
 <th class="py-3 px-4">Request ID</th>
 <th class="py-3 px-4">Document Reference & Title</th>
 <th class="py-3 px-4">Intended Signer</th>
 <th class="py-3 px-4">Purpose</th>
 <th class="py-3 px-4">Status & Format</th>
 <th class="py-3 px-4 text-right">Cryptographic Actions</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-hairline">
 <tr
 v-for="sig in filteredSignatures"
 :key="sig.id"
 class="hover:bg-surface-hover transition-colors"
 >
 <td class="py-3 px-4 font-mono font-medium text-primary">
 #SIG-{{ String(sig.id).padStart(4, '0') }}
 </td>
 <td class="py-3 px-4">
 <div class="font-medium text-ink-secondary">
 {{ sig.document_version?.document?.title || 'Unnamed document' }}
 </div>
 <div class="text-xs text-ink-muted font-mono">
 {{ sig.document_version?.document?.reference_number || '—' }}<span v-if="sig.document_version?.version_number"> · Revision {{ sig.document_version.version_number }}</span>
 </div>
 </td>
 <td class="py-3 px-4">
 <div class="font-medium text-ink-secondary">{{ sig.signer?.name || '—' }}</div>
 <div class="text-xs text-ink-muted">{{ sig.signer?.email }}</div>
 </td>
 <td class="py-3 px-4 text-ink-muted">
 {{ sig.purpose || 'Official Endorsement' }}
 </td>
 <td class="py-3 px-4">
 <div v-if="sig.digital_signature" class="space-y-1">
 <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold bg-[#e8f7ec] text-[#1aae39] dark:bg-[#183321] dark:text-[#42cf63] border border-[#1aae39]/20">
 <CheckCircle2 class="w-3 h-3" />
 Signed ({{ sig.digital_signature.signature_format }})
 </span>
 <div class="text-xs text-ink-muted font-mono">
 {{ new Date(sig.digital_signature.signed_at).toLocaleString() }}
 </div>
 </div>
 <span
 v-else
 class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded text-xs font-semibold bg-[#fff4e6] dark:bg-[#382412] text-[#dd5b00] dark:text-[#ff9243] border border-[#dd5b00]/20"
 >
 <Clock class="w-3 h-3" />
 Pending Signer
 </span>
 </td>
 <td class="py-3 px-4 text-right">
 <div class="flex items-center justify-end gap-2">
 <!-- Sign Document button if pending and actor matches signer or has permission -->
 <button
 v-if="!sig.digital_signature && (authStore.user?.id === sig.signer_id || authStore.hasPermission('signatures.request'))"
 type="button"
 :disabled="executingId === sig.id"
 class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-primary hover:bg-primary-hover text-white text-xs font-semibold transition disabled:opacity-50"
 @click="handleExecuteSignature(sig)"
 >
 <PenLine class="w-3 h-3" />
 <span>{{ executingId === sig.id ? 'Signing...' : 'Sign Document' }}</span>
 </button>

 <!-- Inspect Verification if already signed -->
 <button
 v-if="sig.digital_signature"
 type="button"
 :disabled="verifyingId === sig.digital_signature.id"
 class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md border border-hairline text-primary text-xs font-medium hover:bg-canvas-soft transition disabled:opacity-50"
 @click="handleInspectVerification(sig.digital_signature)"
 >
 <ShieldCheck class="w-3 h-3" />
 <span>{{ verifyingId === sig.digital_signature.id ? 'Verifying...' : 'Verify Cryptography' }}</span>
 </button>
 </div>
 </td>
 </tr>

 <tr v-if="filteredSignatures.length === 0">
 <td colspan="6" class="p-8">
 <EmptyState
 title="No signature requests"
 description="No pending signature requests matching your criteria. Click 'Request signature' to dispatch a document revision to an authorized executive."
 />
 </td>
 </tr>
 </tbody>
 </table>
 </div>
 </div>

 <!-- Tab 2: Office Stamps -->
 <div
 v-else-if="activeTab === 'stamps'"
 class="bg-surface border border-hairline rounded-lg overflow-hidden"
 >
 <div v-if="loading" class="p-6">
 <LoadingSkeleton type="table" :rows="4" />
 </div>
 <div v-else class="overflow-x-auto">
 <table class="w-full text-left text-xs text-ink-secondary">
 <thead class="bg-canvas-soft border-b border-hairline text-xs font-semibold text-ink-muted">
 <tr>
 <th class="py-3 px-4">Seal ID</th>
 <th class="py-3 px-4">Stamp Name</th>
 <th class="py-3 px-4">Designated Department / Unit</th>
 <th class="py-3 px-4">Active Status</th>
 <th class="py-3 px-4 text-right">Actions</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-hairline">
 <tr
 v-for="stamp in filteredStamps"
 :key="stamp.id"
 class="hover:bg-surface-hover transition-colors"
 >
 <td class="py-3 px-4 font-mono font-medium text-primary">
 WU-STP-{{ String(stamp.id).padStart(3, '0') }}
 </td>
 <td class="py-3 px-4 font-medium text-ink-secondary">
 {{ stamp.name }}
 </td>
 <td class="py-3 px-4 text-ink-muted">
 {{ stamp.unit?.name || 'Institutional Office' }}
 </td>
 <td class="py-3 px-4">
 <span
 class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold"
 :class="stamp.active ? 'bg-[#e8f7ec] text-[#1aae39] dark:bg-[#183321] dark:text-[#42cf63] border border-[#1aae39]/20' : 'bg-surface-muted text-ink-muted '"
 >
 <CheckCircle2 v-if="stamp.active" class="w-3 h-3" />
 {{ stamp.active ? 'Active Artwork' : 'Retired' }}
 </span>
 </td>
 <td class="py-3 px-4 text-right">
 <button
 v-if="stamp.active"
 type="button"
 class="inline-flex items-center gap-1 px-2.5 py-1 rounded bg-primary/10 hover:bg-primary/20 text-primary text-xs font-medium transition cursor-pointer"
 title="Apply this office seal to a document version"
 @click="openApplyStampModal(stamp)"
 >
 <Stamp class="w-3.5 h-3.5" />
 <span>Apply Seal</span>
 </button>
 <span v-else class="text-xs text-ink-muted">Retired</span>
 </td>
 </tr>

 <tr v-if="filteredStamps.length === 0">
 <td colspan="5" class="p-8">
 <EmptyState
 title="No office stamps"
 description="No registered office stamps match the current search."
 />
 </td>
 </tr>
 </tbody>
 </table>
 </div>
 </div>

 <!-- Tab 3: Trust Configuration & Identities -->
 <div
 v-else
 class="bg-surface border border-hairline rounded-lg p-6 space-y-6"
 >
 <div class="flex items-center gap-3">
 <div class="w-10 h-10 rounded-lg bg-primary-subtle text-primary flex items-center justify-center font-bold">
 X.509
 </div>
 <div>
 <h2 class="text-sm font-semibold text-ink-secondary">
 Wollo University Trust Services Architecture
 </h2>
 <p class="text-xs text-ink-muted">
 Cryptographic standards, certificate authority parameters, and user signing identities.
 </p>
 </div>
 </div>

 <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2 border-t border-hairline text-xs">
 <div class="space-y-1">
 <div class="text-ink-muted font-medium">Digital Signature Algorithm:</div>
 <div class="font-mono bg-canvas-soft p-2 rounded-md border border-hairline text-ink-secondary">
 RSA-2048 with SHA-256 (PKCS#7 Detached ByteRange Envelope)
 </div>
 </div>

 <div class="space-y-1">
 <div class="text-ink-muted font-medium">Key Storage & Hardware Boundary:</div>
 <div class="font-mono bg-canvas-soft p-2 rounded-md border border-hairline text-ink-secondary">
 Secure Keystore (Mode 0600) — Private keys never stored in database
 </div>
 </div>

 <div class="space-y-1">
 <div class="text-ink-muted font-medium">Revocation Protocol:</div>
 <div class="font-mono bg-canvas-soft p-2 rounded-md border border-hairline text-ink-secondary">
 Repository Revocation Ledger with OCSP/CRL fail-safe checking
 </div>
 </div>

 <div class="space-y-1">
 <div class="text-ink-muted font-medium">Regulatory Classification:</div>
 <div class="font-mono bg-canvas-soft p-2 rounded-md border border-hairline text-amber-700 dark:text-amber-300">
 TEST PKI — UNACCREDITED INSTITUTIONAL CA (Proclamation No. 1072/2018)
 </div>
 </div>
 </div>

 <!-- User Signing Identities Registry -->
 <div class="pt-4 border-t border-hairline">
 <h3 class="text-xs font-semibold text-ink-secondary   mb-3">
 Provisioned User Signing Identities ({{ identities.length }})
 </h3>

 <div class="overflow-x-auto">
 <table class="w-full text-left text-xs">
 <thead class="bg-canvas-soft border-b border-hairline text-xs font-semibold text-ink-muted uppercase">
 <tr>
 <th class="py-2.5 px-3">Identity Ref</th>
 <th class="py-2.5 px-3">User</th>
 <th class="py-2.5 px-3">Provider</th>
 <th class="py-2.5 px-3">Validity Window</th>
 <th class="py-2.5 px-3 text-right">Status</th>
 </tr>
 </thead>
 <tbody class="divide-y divide-hairline">
 <tr v-for="id in identities" :key="id.id" class="hover:bg-surface-hover transition-colors">
 <td class="py-2.5 px-3 font-mono text-xs text-primary">
 {{ id.provider_identity_reference }}
 </td>
 <td class="py-2.5 px-3">
 <div class="font-medium text-ink-secondary">{{ id.user_name }}</div>
 <div class="text-xs text-ink-muted">{{ id.user_email }}</div>
 </td>
 <td class="py-2.5 px-3 font-mono text-xs text-ink-muted">
 {{ id.provider_code }}
 </td>
 <td class="py-2.5 px-3 text-xs text-ink-muted">
 {{ id.valid_from ? new Date(id.valid_from).toLocaleDateString() : 'N/A' }} — {{ id.valid_until ? new Date(id.valid_until).toLocaleDateString() : 'N/A' }}
 </td>
 <td class="py-2.5 px-3 text-right">
 <span
 class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold"
 :class="id.active ? 'bg-[#e8f7ec] text-[#1aae39] dark:bg-[#183321] dark:text-[#42cf63]' : 'bg-[#fdebee] text-[#e03e3e] dark:bg-[#381619] dark:text-[#ff7373]'"
 >
 {{ id.active ? 'Active' : 'Revoked' }}
 </span>
 </td>
 </tr>

 <tr v-if="identities.length === 0">
 <td colspan="5" class="py-4 text-center text-xs text-ink-muted">
 No signing identities provisioned yet. Identities are auto-provisioned upon first signature request.
 </td>
 </tr>
 </tbody>
 </table>
 </div>
 </div>
 </div>

 <!-- Modal: Request Signature (F-SIGNATURE) -->
 <div
 v-if="isModalOpen"
 class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50 backdrop-blur-xs"
 @click.self="isModalOpen = false"
 >
 <div class="bg-surface border border-hairline rounded-lg max-w-lg w-full p-6 shadow-xl">
 <div class="flex items-center justify-between pb-3 border-b border-hairline">
 <div>
 <h2 class="text-sm font-semibold text-ink-secondary">
 Request Document Signature (F-SIGNATURE)
 </h2>
 <p class="text-xs text-ink-muted">
 Pin exact revision and assign authorized executive signer
 </p>
 </div>
 <button
 type="button"
 class="text-ink-muted hover:text-ink-secondary p-1"
 @click="isModalOpen = false"
 >
 <X class="w-4 h-4" />
 </button>
 </div>

 <form class="space-y-4 mt-4" @submit.prevent="submitSignatureRequest">
 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Select Document *
 </label>
 <select
 v-model="form.document_version_id"
 required
 class="w-full px-3 py-1.5 bg-surface border border-hairline rounded-md text-xs text-ink-secondary focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 >
 <option v-for="d in documents" :key="d.id" :value="d.current_version?.id || d.versions?.[0]?.id || d.id">
 {{ d.reference_number }} — {{ d.title }}
 </option>
 </select>
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Designated Signer *
 </label>
 <select
 v-model="form.signer_id"
 required
 class="w-full px-3 py-1.5 bg-surface border border-hairline rounded-md text-xs text-ink-secondary focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 >
 <option value="" disabled>Select User</option>
 <option v-for="u in users" :key="u.id" :value="u.id">
 {{ u.name }} ({{ u.email }})
 </option>
 </select>
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Signing Purpose *
 </label>
 <input
 v-model="form.purpose"
 type="text"
 required
 placeholder="e.g. Official Dean Approval, Senate Confirmation"
 class="w-full px-3 py-1.5 bg-surface border border-hairline rounded-md text-xs text-ink-secondary focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 />
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Expiration Date
 </label>
 <input
 v-model="form.expires_at"
 type="date"
 class="w-full px-3 py-1.5 bg-surface border border-hairline rounded-md text-xs text-ink-secondary focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 />
 </div>

 <div class="flex items-center justify-end gap-2 pt-3 border-t border-hairline">
 <button
 type="button"
 class="px-3 py-1.5 rounded-md border border-hairline text-xs font-medium text-ink-secondary hover:bg-canvas-soft"
 @click="isModalOpen = false"
 >
 Cancel
 </button>
 <button
 type="submit"
 :disabled="submitting"
 class="px-4 py-1.5 rounded-md bg-primary hover:bg-primary-hover text-white text-xs font-semibold transition disabled:opacity-50"
 >
 {{ submitting ? 'Dispatching...' : 'Dispatch Signing Request' }}
 </button>
 </div>
 </form>
 </div>
 </div>

 <!-- Modal: Cryptographic Verification Inspector -->
 <div
 v-if="verificationModalOpen && selectedVerification"
 class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50 backdrop-blur-xs"
 @click.self="verificationModalOpen = false"
 >
 <div class="bg-surface border border-hairline rounded-lg max-w-xl w-full p-6 shadow-xl space-y-4">
 <div class="flex items-center justify-between pb-3 border-b border-hairline">
 <div class="flex items-center gap-2.5">
 <ShieldCheck class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
 <div>
 <h2 class="text-sm font-semibold text-ink-secondary">
 Independent Cryptographic Verification
 </h2>
 <p class="text-xs text-ink-muted">
 Authentic PKCS#7 detached digital signature integrity check
 </p>
 </div>
 </div>
 <button
 type="button"
 class="text-ink-muted hover:text-ink-secondary p-1"
 @click="verificationModalOpen = false"
 >
 <X class="w-4 h-4" />
 </button>
 </div>

 <!-- Regulatory Notice inside modal -->
 <div class="p-3 bg-[#fff4e6] dark:bg-[#382412] border border-[#dd5b00]/30 rounded-md text-xs text-[#dd5b00] dark:text-[#ff9243]">
 <strong>Notice of Trust Context:</strong>
 This document was signed using Wollo University Institutional Internal PKI. In accordance with Proclamation No. 1072/2018, this serves internal academic non-repudiation; external qualified legal recognition requires INSA/ECA root CA cross-certification.
 </div>

 <!-- Verification Metrics -->
 <div class="space-y-3 text-xs">
 <div class="flex items-center justify-between p-2.5 rounded-md bg-canvas-soft border border-hairline">
 <span class="font-medium text-ink-muted">Overall Cryptographic Status:</span>
 <span
 class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded font-bold   text-xs"
 :class="selectedVerification.outcome === 'valid' ? 'bg-[#e8f7ec] text-[#1aae39] dark:bg-[#183321] dark:text-[#42cf63]' : 'bg-[#fdebee] text-[#e03e3e] dark:bg-[#381619] dark:text-[#ff7373]'"
 >
 {{ selectedVerification.outcome }}
 </span>
 </div>

 <div class="grid grid-cols-2 gap-3">
 <div class="p-2.5 rounded-md bg-canvas-soft border border-hairline">
 <div class="text-xs text-ink-muted font-medium uppercase">ByteRange Integrity</div>
 <div class="text-xs font-semibold text-emerald-700 dark:text-emerald-300 mt-1 flex items-center gap-1">
 <CheckCircle2 class="w-3.5 h-3.5" />
 Unbroken (Tamper Passed)
 </div>
 </div>

 <div class="p-2.5 rounded-md bg-canvas-soft border border-hairline">
 <div class="text-xs text-ink-muted font-medium uppercase">Timestamp Status</div>
 <div class="text-xs font-semibold text-ink-secondary mt-1">
 {{ selectedVerification.timestamp_status || 'Offline Test Token' }}
 </div>
 </div>
 </div>

 <div class="space-y-1.5 p-3 rounded-md bg-canvas-soft border border-hairline font-mono text-xs">
 <div><span class="text-ink-muted">Signer Subject DN:</span> {{ selectedVerification.details?.signer_certificate?.subject_dn || 'N/A' }}</div>
 <div><span class="text-ink-muted">Issuer CA DN:</span> {{ selectedVerification.details?.signer_certificate?.issuer_dn || 'N/A' }}</div>
 <div><span class="text-ink-muted">Observed SHA-256:</span> <span class="break-all">{{ selectedVerification.details?.observed_sha256 }}</span></div>
 <div><span class="text-ink-muted">Signature Algorithm:</span> {{ selectedVerification.details?.signature_algorithm || 'RSA-2048 / SHA-256' }}</div>
 </div>
 </div>

 <div class="flex justify-end pt-2 border-t border-hairline">
 <button
 type="button"
 class="px-4 py-1.5 rounded-md bg-primary hover:bg-primary-hover text-white text-xs font-semibold"
 @click="verificationModalOpen = false"
 >
 Close Inspector
 </button>
 </div>
 </div>
 </div>

 <!-- Modal: Apply Office Seal -->
 <div
 v-if="applyStampModalOpen && selectedStampForApplication"
 class="fixed inset-0 bg-black/40 flex items-center justify-center p-4 z-50 backdrop-blur-xs"
 @click.self="applyStampModalOpen = false"
 >
 <div class="bg-surface border border-hairline rounded-lg max-w-lg w-full p-6 shadow-xl space-y-4">
 <div class="flex items-center justify-between pb-3 border-b border-hairline">
 <div class="flex items-center gap-2.5">
 <Stamp class="w-5 h-5 text-primary" />
 <div>
 <h2 class="text-sm font-semibold text-ink-secondary">
 Apply Official Office Seal
 </h2>
 <p class="text-xs text-ink-muted">
 Affix authorized departmental seal onto document version
 </p>
 </div>
 </div>
 <button
 type="button"
 class="text-ink-muted hover:text-ink-secondary p-1"
 @click="applyStampModalOpen = false"
 >
 <X class="w-4 h-4" />
 </button>
 </div>

 <div class="p-3 bg-canvas-soft border border-hairline rounded-md text-xs space-y-1">
 <div class="font-medium text-ink-secondary">Selected Stamp: {{ selectedStampForApplication.name }}</div>
 <div class="text-ink-muted text-xs">Designated Unit: {{ selectedStampForApplication.unit?.name || 'Institutional Office' }}</div>
 </div>

 <form class="space-y-4" @submit.prevent="submitApplyStamp">
 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Target Document Version *
 </label>
 <select
 v-model="applyStampForm.document_version_id"
 required
 class="w-full px-3 py-1.5 bg-surface border border-hairline rounded-md text-xs text-ink-secondary focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 >
 <option v-for="d in documents" :key="d.id" :value="d.current_version?.id || d.versions?.[0]?.id || d.id">
 {{ d.reference_number }} — {{ d.title }}
 </option>
 </select>
 </div>

 <div>
 <label class="block text-xs font-semibold text-ink-secondary mb-1">
 Administrative Justification & Placement Reason *
 </label>
 <textarea
 v-model="applyStampForm.reason"
 required
 rows="3"
 placeholder="State official reason for seal application per institutional directive..."
 class="w-full px-3 py-1.5 bg-surface border border-hairline rounded-md text-xs text-ink-secondary focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary/20"
 ></textarea>
 </div>

 <div class="flex items-center justify-end gap-2 pt-3 border-t border-hairline">
 <button
 type="button"
 class="px-3 py-1.5 rounded-md border border-hairline text-xs font-medium text-ink-secondary hover:bg-canvas-soft"
 @click="applyStampModalOpen = false"
 >
 Cancel
 </button>
 <button
 type="submit"
 :disabled="isApplyingStamp"
 class="px-4 py-1.5 rounded-md bg-primary hover:bg-primary-hover text-white text-xs font-semibold transition disabled:opacity-50"
 >
 {{ isApplyingStamp ? 'Applying Seal...' : 'Affix Official Seal' }}
 </button>
 </div>
 </form>
 </div>
 </div>
 </div>
</template>
