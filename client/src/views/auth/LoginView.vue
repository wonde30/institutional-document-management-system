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
 Lock
} from 'lucide-vue-next'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const email = ref('')
const password = ref('')
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

</script>

<template>
 <div class="min-h-screen bg-canvas-soft flex items-center justify-center p-4 select-none">
 <div class="w-full max-w-md">
 <!-- Main Card -->
 <div class="bg-surface border border-hairline rounded-lg border border-hairline bg-surface p-6 sm:p-8 space-y-5">
 <!-- University Branding -->
 <div class="text-center space-y-2">
 <div class="inline-flex w-11 h-11 rounded-lg bg-primary text-white items-center justify-center font-bold text-base ">
 WU
 </div>
 <div>
 <h1 class="text-2xl font-bold text-ink tracking-tight">
 Wollo University
 </h1>
 <p class="text-xs font-semibold text-ink-muted   mt-0.5">
 Integrated Document Repository & Archive
 </p>
 </div>
 </div>

 <!-- Error Notification -->
 <div
 v-if="errorMessage"
 role="alert"
 data-login-error
 class="flex items-start gap-3 p-3.5 rounded-lg bg-[#fde8e8] dark:bg-[#371717] border border-[#f87171]/40 text-[#e03e3e] dark:text-[#f87171] text-xs"
 >
 <AlertCircle class="w-4 h-4 shrink-0 mt-0.5 text-[#e03e3e] dark:text-[#f87171]" />
 <div class="flex-1 font-medium leading-relaxed">{{ errorMessage }}</div>
 </div>

 <!-- Login Form -->
 <form @submit.prevent="handleLogin" class="space-y-4">
 <div class="space-y-1.5">
 <label for="email" class="block text-xs font-semibold text-ink-secondary">
 Institutional Email
 </label>
 <BaseInput
 id="email"
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
 <label for="password" class="block text-xs font-semibold text-ink-secondary">
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
 id="password"
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
 class="w-full justify-center py-2.5 text-sm font-semibold bg-primary hover:bg-primary-hover text-white rounded-lg transition-colors"
 :loading="isLoading"
 >
 Sign In to Workspace
 </BaseButton>
 </div>
 </form>

 <!-- Support Info -->
 <div class="text-center text-xs text-ink-muted">
 Need access delegation? Contact the Information Technology Directorate through your unit's administrative officer.
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
 <p class="text-xs">
 Wollo University Integrated Repository &bull; Institutional Governance &bull; 2026
 </p>
 </div>
 </div>
 </div>
</template>
