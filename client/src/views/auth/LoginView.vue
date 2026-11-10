<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import {
 Mail,
 Eye,
 EyeOff,
 AlertCircle,
 ShieldCheck,
 Building2,
 Lock,
} from 'lucide-vue-next'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const email = ref('admin@wu.edu.et')
const password = ref('Password123!')
const showPassword = ref(false)
const errorMessage = ref<string | null>(null)
const isLoading = ref(false)

function sanitizeRedirect(target: unknown): string {
 if (typeof target !== 'string' || !target.startsWith('/')) {
 return '/dashboard'
 }
 // Reject protocol-relative URLs (//example.com), malformed paths, or loops back to login
 if (target.startsWith('//') || target.startsWith('/\\') || target.includes('/login')) {
 return '/dashboard'
 }
 return target
}

async function handleLogin() {
 errorMessage.value = null
 isLoading.value = true
 try {
 await authStore.login({
 email: email.value,
 password: password.value,
 })
 const redirect = sanitizeRedirect(route.query.redirect)
 router.push(redirect)
 } catch (err: any) {
 errorMessage.value =
 err.response?.data?.message ||
 'Authentication failed. Please verify your institutional email and password.'
 } finally {
 isLoading.value = false
 }
}

function setDemoCredentials(demoEmail: string, demoPass: string) {
 email.value = demoEmail
 password.value = demoPass
 handleLogin()
}
</script>

<template>
 <div class="min-h-screen bg-canvas-soft flex items-center justify-center p-4 select-none">
 <div class="w-full max-w-md">
 <!-- Main Card -->
 <div class="bg-surface border border-hairline rounded-xl shadow-xs p-8 sm:p-10 space-y-6">
 <!-- University Branding -->
 <div class="text-center space-y-2">
 <div class="inline-flex w-12 h-12 rounded-xl bg-primary text-white items-center justify-center font-bold text-lg shadow-xs">
 WU
 </div>
 <div>
 <h1 class="text-2xl font-bold text-ink tracking-tight">
 Wollo University
 </h1>
 <p class="text-xs font-semibold text-ink-muted uppercase tracking-wider mt-0.5">
 Integrated Document Repository & Archive
 </p>
 </div>
 </div>

 <!-- Error Notification -->
 <div
 v-if="errorMessage"
 class="flex items-start gap-3 p-3.5 rounded-xl bg-[#fde8e8] dark:bg-[#371717] border border-[#f87171]/40 text-[#e03e3e] dark:text-[#f87171] text-xs"
 >
 <AlertCircle class="w-4 h-4 shrink-0 mt-0.5 text-[#e03e3e] dark:text-[#f87171]" />
 <div class="flex-1 font-medium leading-relaxed">{{ errorMessage }}</div>
 </div>

 <!-- Login Form -->
 <form @submit.prevent="handleLogin" class="space-y-4">
 <div class="space-y-1.5">
 <label class="block text-xs font-semibold text-ink-secondary">
 Institutional Email
 </label>
 <BaseInput
 v-model="email"
 type="email"
 placeholder="name@wu.edu.et"
 required
 :disabled="isLoading"
 >
 <template #prefix>
 <Mail class="w-4 h-4 text-ink-faint" />
 </template>
 </BaseInput>
 </div>

 <div class="space-y-1.5">
 <div class="flex items-center justify-between">
 <label class="block text-xs font-semibold text-ink-secondary">
 Password
 </label>
 <router-link
 to="/forgot-password"
 class="text-xs text-primary hover:underline font-medium"
 >
 Forgot password?
 </router-link>
 </div>
 <BaseInput
 v-model="password"
 :type="showPassword ? 'text' : 'password'"
 placeholder="••••••••••••"
 required
 :disabled="isLoading"
 >
 <template #prefix>
 <Lock class="w-4 h-4 text-ink-faint" />
 </template>
 <template #suffix>
 <button
 type="button"
 @click="showPassword = !showPassword"
 class="text-ink-faint hover:text-ink transition-colors p-1"
 aria-label="Toggle password visibility"
 >
 <EyeOff v-if="showPassword" class="w-4 h-4" />
 <Eye v-else class="w-4 h-4" />
 </button>
 </template>
 </BaseInput>
 </div>

 <div class="pt-2">
 <BaseButton
 type="submit"
 variant="primary"
 class="w-full justify-center py-2.5 text-sm font-semibold bg-primary hover:bg-primary-hover text-white rounded-lg transition-colors shadow-xs"
 :loading="isLoading"
 >
 Sign In to Workspace
 </BaseButton>
 </div>
 </form>

 <!-- Quick Demo Profiles -->
 <div class="pt-4 border-t border-hairline space-y-2.5">
 <div class="text-[11px] font-semibold uppercase tracking-wider text-ink-muted text-center">
 Institutional Demo Roles
 </div>
 <div class="grid grid-cols-2 gap-2">
 <button
 type="button"
 @click="setDemoCredentials('admin@wu.edu.et', 'Password123!')"
 class="p-2.5 rounded-lg border border-hairline hover:bg-canvas-soft text-left transition-colors group"
 >
 <div class="flex items-center gap-1.5 text-xs font-semibold text-ink group-hover:text-primary">
 <ShieldCheck class="w-3.5 h-3.5 text-primary" /> System Administrator
 </div>
 <div class="text-[10px] text-ink-muted truncate mt-0.5">admin@wu.edu.et</div>
 </button>

 <button
 type="button"
 @click="setDemoCredentials('head.cs@wu.edu.et', 'Password123!')"
 class="p-2.5 rounded-lg border border-hairline hover:bg-canvas-soft text-left transition-colors group"
 >
 <div class="flex items-center gap-1.5 text-xs font-semibold text-ink group-hover:text-primary">
 <Building2 class="w-3.5 h-3.5 text-primary" /> Department Head
 </div>
 <div class="text-[10px] text-ink-muted truncate mt-0.5">head.cs@wu.edu.et</div>
 </button>
 </div>
 </div>

 <!-- Support Info -->
 <div class="text-center text-[11px] text-ink-muted">
 Need access delegation? Contact Information Technology Directorate at <a href="mailto:ict.support@wu.edu.et" class="text-primary hover:underline">ict.support@wu.edu.et</a>
 </div>
 </div>

 <!-- Footer Note & Public Links -->
 <div class="mt-6 text-center text-xs text-ink-muted space-y-2">
 <div class="flex items-center justify-center gap-3 text-xs font-medium">
 <router-link to="/" class="hover:text-primary transition-colors">
 Portal Home
 </router-link>
 <span>&bull;</span>
 <router-link to="/privacy-policy" class="hover:text-primary transition-colors">
 Privacy Policy
 </router-link>
 <span>&bull;</span>
 <router-link to="/terms" class="hover:text-primary transition-colors">
 Terms of Use
 </router-link>
 </div>
 <p class="text-[11px]">
 Wollo University Integrated Repository &bull; Institutional Governance &bull; 2026
 </p>
 </div>
 </div>
 </div>
</template>
