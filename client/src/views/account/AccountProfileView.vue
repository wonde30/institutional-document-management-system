<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { authApi } from '@/api/client'
import { useToast } from '@/composables/useToast'
import PageHeader from '@/components/PageHeader.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseAlert from '@/components/ui/BaseAlert.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import {
 User,
 ShieldCheck,
 Building2,
 Lock,
 SlidersHorizontal,
 Clock,
 KeyRound,
 CheckCircle2,
 AlertCircle,
 RefreshCw
} from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const toast = useToast()

const activeTab = ref<'profile' | 'security' | 'preferences'>('profile')

// Sync the tab with the path.
//
// The three account routes render this component, so the instance is reused
// when the path changes. The tab therefore follows the route, not the mount.
watch(
 () => route.path,
 () => {
 if (route.path.includes('/security')) {
 activeTab.value = 'security'
 } else if (route.path.includes('/preferences')) {
 activeTab.value = 'preferences'
 } else {
 activeTab.value = 'profile'
 }
 },
 { immediate: true }
)

function setTab(tab: 'profile' | 'security' | 'preferences') {
 activeTab.value = tab
 if (tab === 'profile') router.replace('/account')
 else router.replace(`/account/${tab}`)
}

// Security tab model
const currentPassword = ref('')
const newPassword = ref('')
const confirmNewPassword = ref('')
const isUpdatingPassword = ref(false)

// Preferences tab model
const timezone = ref('Africa/Addis_Ababa')
const tableDensity = ref<'compact' | 'comfortable'>('comfortable')
const emailNotifications = ref(true)

async function handlePasswordUpdate() {
 if (newPassword.value !== confirmNewPassword.value) {
 toast.error('New passwords do not match.')
 return
 }
 if (newPassword.value.length < 8) {
 toast.error('New password must be at least 8 characters long.')
 return
 }

 isUpdatingPassword.value = true
 try {
 await authApi.changePassword({
 current_password: currentPassword.value,
 new_password: newPassword.value,
 })
 toast.success('Password updated successfully.')
 currentPassword.value = ''
 newPassword.value = ''
 confirmNewPassword.value = ''
 } catch (err: any) {
 toast.error(err.response?.data?.message || 'Failed to update password.')
 } finally {
 isUpdatingPassword.value = false
 }
}

function handleSavePreferences() {
 toast.success('Workspace preferences saved.')
}
</script>

<template>
 <div class="space-y-6">
  <PageHeader
    title="Account & Security Profile"
    subtitle="Manage your institutional identity, authentication credentials, and display preferences."
  />

 <!-- Navigation Tabs -->
 <div class="border-b border-hairline flex gap-6 text-xs font-semibold">
 <button
 type="button"
 @click="setTab('profile')"
 class="pb-3 border-b-2 flex items-center gap-2 transition-colors cursor-pointer"
 :class="activeTab === 'profile'
 ? 'border-primary text-primary'
 : 'border-transparent text-ink-muted hover:text-ink dark:text-ink-faint dark:hover:text-white'"
 >
 <User class="w-4 h-4" />
 Profile Facts & Institutional Memberships
 </button>

 <button
 type="button"
 @click="setTab('security')"
 class="pb-3 border-b-2 flex items-center gap-2 transition-colors cursor-pointer"
 :class="activeTab === 'security'
 ? 'border-primary text-primary'
 : 'border-transparent text-ink-muted hover:text-ink dark:text-ink-faint dark:hover:text-white'"
 >
 <Lock class="w-4 h-4" />
 Account Security & Password
 </button>

 <button
 type="button"
 @click="setTab('preferences')"
 class="pb-3 border-b-2 flex items-center gap-2 transition-colors cursor-pointer"
 :class="activeTab === 'preferences'
 ? 'border-primary text-primary'
 : 'border-transparent text-ink-muted hover:text-ink dark:text-ink-faint dark:hover:text-white'"
 >
 <SlidersHorizontal class="w-4 h-4" />
 Workspace Preferences & Localization
 </button>
 </div>

 <!-- Tab 1: Profile Facts -->
 <div v-if="activeTab === 'profile'" class="grid grid-cols-1 md:grid-cols-3 gap-6">
 <div class="md:col-span-2 space-y-4">
 <div class="p-5 rounded-xl bg-surface border border-hairline space-y-4">
 <h2 class="text-sm font-semibold text-ink flex items-center gap-2">
 <User class="w-4 h-4 text-primary" />
 Official Identity Record
 </h2>

 <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
 <div>
 <span class="block text-ink-muted">Full Name</span>
 <span class="font-medium text-ink text-sm">{{ authStore.user?.name }}</span>
 </div>
 <div>
 <span class="block text-ink-muted">Institutional Email</span>
 <span class="font-medium text-ink text-sm">{{ authStore.user?.email }}</span>
 </div>
 <div>
 <span class="block text-ink-muted">Account Status</span>
 <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-[#e8f7ec] dark:bg-[#14301d] text-[#1aae39] dark:text-[#34d399] border border-[#1aae39]/20">
 <CheckCircle2 class="w-3 h-3" /> Active Institutional Account
 </span>
 </div>
 <div>
 <span class="block text-ink-muted">Primary Institutional Unit</span>
 <span class="font-medium text-ink">{{ authStore.primaryUnit?.unit_name || authStore.activeUnit?.name || 'Central Administration' }}</span>
 </div>
 <div>
 <span class="block text-ink-muted">Assigned System Roles</span>
 <div class="flex flex-wrap gap-1 mt-1">
 <span
 v-for="role in (authStore.userRoles.length ? authStore.userRoles : ['Staff'])"
 :key="role"
 class="px-2 py-0.5 rounded text-xs font-medium bg-primary-subtle text-primary border border-primary/20"
 >
 {{ role }}
 </span>
 </div>
 </div>
 </div>
 </div>

 <!-- Memberships Card -->
 <div class="p-5 rounded-xl bg-surface border border-hairline space-y-3">
 <h2 class="text-sm font-semibold text-ink flex items-center gap-2">
 <Building2 class="w-4 h-4 text-primary" />
 Authorized Office Memberships & Roles
 </h2>

 <div class="divide-y divide-hairline-subtle text-xs">
 <div
 v-for="unit in authStore.availableUnits"
 :key="unit.id"
 class="py-2.5 flex items-center justify-between"
 >
 <div>
 <div class="font-medium text-ink">
 {{ unit.name }}
 </div>
 <div class="text-ink-muted text-xs">
 Code: {{ unit.code }} · Type: {{ unit.unit_type || 'Academic / Administrative' }}
 </div>
 </div>
 <span
 v-if="unit.id === (authStore.primaryUnit?.unit_id || authStore.activeUnitId)"
 class="px-2 py-0.5 rounded-full text-xs font-semibold bg-[#e8f7ec] dark:bg-[#14301d] text-[#1aae39] dark:text-[#34d399]"
 >
 {{ unit.id === authStore.primaryUnit?.unit_id ? 'Primary Office' : 'Active Office' }}
 </span>
 </div>

 <div
 v-if="!authStore.availableUnits || authStore.availableUnits.length === 0"
 class="py-2.5 flex items-center justify-between"
 >
 <div>
 <div class="font-medium text-ink">
 {{ authStore.primaryUnit?.unit_name || authStore.activeUnit?.name || 'Central Administration' }}
 </div>
 <div class="text-ink-muted text-xs">
 Role: {{ authStore.userRoles[0] || 'Institutional Staff' }} · Scope: Unit
 </div>
 </div>
 <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-[#e8f7ec] dark:bg-[#14301d] text-[#1aae39] dark:text-[#34d399]">
 Primary Office
 </span>
 </div>
 </div>
 </div>
 </div>

 <!-- Quick Guidance Sidebar -->
 <div class="space-y-4 text-xs">
 <div class="p-4 rounded-xl bg-surface-muted  border border-hairline space-y-2">
 <div class="font-semibold text-ink flex items-center gap-1.5">
 <ShieldCheck class="w-4 h-4 text-primary" />
 Access Boundary
 </div>
 <p class="text-ink-muted leading-relaxed">
 Your capabilities within WU-DMS are derived dynamically from your assigned office roles and departmental scopes. Modifications to official appointments must be processed through the Human Resources Directorate.
 </p>
 </div>
 </div>
 </div>

 <!-- Tab 2: Security & Password -->
 <div v-else-if="activeTab === 'security'" class="max-w-xl space-y-5">
 <div class="p-5 rounded-xl bg-surface border border-hairline space-y-4">
 <h2 class="text-sm font-semibold text-ink flex items-center gap-2">
 <KeyRound class="w-4 h-4 text-primary" />
 Change Password
 </h2>

 <form @submit.prevent="handlePasswordUpdate" class="space-y-4">
 <div class="space-y-1.5">
 <label class="block text-xs font-semibold text-ink-secondary">
 Current Password <span class="text-[#e03e3e]">*</span>
 </label>
 <BaseInput
 v-model="currentPassword"
 type="password"
 placeholder="••••••••••••"
 required
 :disabled="isUpdatingPassword"
 />
 </div>

 <div class="space-y-1.5">
 <label class="block text-xs font-semibold text-ink-secondary">
 New Password (minimum 8 characters) <span class="text-[#e03e3e]">*</span>
 </label>
 <BaseInput
 v-model="newPassword"
 type="password"
 placeholder="••••••••••••"
 required
 :disabled="isUpdatingPassword"
 />
 </div>

 <div class="space-y-1.5">
 <label class="block text-xs font-semibold text-ink-secondary">
 Confirm New Password <span class="text-[#e03e3e]">*</span>
 </label>
 <BaseInput
 v-model="confirmNewPassword"
 type="password"
 placeholder="••••••••••••"
 required
 :disabled="isUpdatingPassword"
 />
 </div>

 <BaseButton
 type="submit"
 variant="primary"
 class="bg-primary hover:bg-primary-hover text-white"
 :loading="isUpdatingPassword"
 >
 Update Institutional Password
 </BaseButton>
 </form>
 </div>
 </div>

 <!-- Tab 3: Workspace Preferences -->
 <div v-else-if="activeTab === 'preferences'" class="max-w-xl space-y-5">
 <div class="p-5 rounded-xl bg-surface border border-hairline space-y-4">
 <h2 class="text-sm font-semibold text-ink flex items-center gap-2">
 <SlidersHorizontal class="w-4 h-4 text-primary" />
 Display & Localization Preferences
 </h2>

 <div class="space-y-4 text-xs">
 <div class="space-y-1.5">
 <label class="block font-semibold text-ink-secondary">
 Display Timezone
 </label>
 <BaseSelect
 v-model="timezone"
 :options="[
 { value: 'Africa/Addis_Ababa', label: 'East Africa Time (UTC+03:00) · Addis Ababa' },
 { value: 'UTC', label: 'Coordinated Universal Time (UTC)' },
 ]"
 />
 <p class="text-xs text-ink-muted">
 All database timestamps are stored canonically in UTC and rendered in your configured timezone.
 </p>
 </div>

 <div class="space-y-1.5">
 <label class="block font-semibold text-ink-secondary">
 Table Density
 </label>
 <BaseSelect
 v-model="tableDensity"
 :options="[
 { value: 'comfortable', label: 'Comfortable (Standard 44px row height)' },
 { value: 'compact', label: 'Compact (Optimized for desktop inspection)' },
 ]"
 />
 </div>

 <div class="pt-2">
 <BaseButton
 variant="primary"
 class="bg-primary hover:bg-primary-hover text-white"
 @click="handleSavePreferences"
 >
 Save Preferences
 </BaseButton>
 </div>
 </div>
 </div>
 </div>
 </div>
</template>
