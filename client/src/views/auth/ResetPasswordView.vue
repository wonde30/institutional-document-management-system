<script setup lang="ts">
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import { Lock, ArrowLeft, CheckCircle2, AlertCircle } from 'lucide-vue-next'
import { authApi } from '@/api/client'

const router = useRouter()
const route = useRoute()

const token = ref((route.query.token as string) || '')
const email = ref((route.query.email as string) || '')
const password = ref('')
const confirmPassword = ref('')
const isSuccess = ref(false)
const errorMessage = ref('')
const isLoading = ref(false)

async function handleReset() {
 if (password.value !== confirmPassword.value) {
 errorMessage.value = 'Passwords do not match.'
 return
 }
 if (password.value.length < 8) {
 errorMessage.value = 'Password must be at least 8 characters long.'
 return
 }
 if (!token.value || !email.value) {
 errorMessage.value = 'This recovery link is incomplete. Please request a new one.'
 return
 }

 isLoading.value = true
 errorMessage.value = ''

 try {
  await authApi.resetPassword({
   token: token.value,
   email: email.value,
   password: password.value,
   password_confirmation: confirmPassword.value,
  })
  isSuccess.value = true
 } catch (error: unknown) {
  const apiError = error as {
   response?: { data?: { message?: string; errors?: Record<string, string[]> } }
  }
  errorMessage.value =
   apiError.response?.data?.errors?.email?.[0] ??
   apiError.response?.data?.errors?.password?.[0] ??
   apiError.response?.data?.message ??
   'The recovery link is invalid or has expired. Please request a new one.'
 } finally {
  isLoading.value = false
 }
}
</script>

<template>
 <div class="min-h-screen bg-canvas-soft flex items-center justify-center p-4 select-none">
 <div class="w-full max-w-md">
 <div class="bg-surface border border-hairline rounded-xl shadow-xs p-8 sm:p-10 space-y-6">
 <div class="text-center space-y-2">
 <div class="inline-flex w-12 h-12 rounded-xl bg-primary text-white items-center justify-center font-bold text-lg shadow-xs">
 WU
 </div>
 <div>
 <h1 class="text-2xl font-bold text-ink tracking-tight">
 Reset Institutional Password
 </h1>
 <p class="text-xs text-ink-muted mt-0.5">
 Choose a strong password with at least 8 characters.
 </p>
 </div>
 </div>

 <div v-if="isSuccess" class="p-4 rounded-xl bg-[#e8f7ec] dark:bg-[#14301d] border border-[#1aae39]/20 space-y-3 text-center">
 <div class="flex items-center justify-center gap-2 text-xs font-semibold text-[#1aae39] dark:text-[#34d399]">
 <CheckCircle2 class="w-4 h-4 shrink-0" />
 <span>Password Updated</span>
 </div>
 <p class="text-xs text-ink">
 Your password has been successfully updated. You may now sign in.
 </p>
 <router-link
 to="/login"
 class="inline-block mt-2 px-4 py-2 bg-primary hover:bg-primary-hover text-white text-xs font-semibold rounded-lg shadow-xs transition-colors"
 >
 Sign In to Portal
 </router-link>
 </div>

 <form v-else @submit.prevent="handleReset" class="space-y-4">
 <div v-if="errorMessage" class="p-3 rounded-lg bg-[#fde8e8] dark:bg-[#371717] border border-[#f87171]/40 text-[#e03e3e] dark:text-[#f87171] text-xs flex items-center gap-2">
 <AlertCircle class="w-4 h-4 shrink-0" />
 <span>{{ errorMessage }}</span>
 </div>

 <div class="space-y-1.5">
 <label class="block text-xs font-semibold text-ink-secondary">
 New Password (minimum 8 characters) <span class="text-[#e03e3e]">*</span>
 </label>
 <BaseInput
 v-model="password"
 type="password"
 placeholder="••••••••••••"
 required
 :disabled="isLoading"
 >
 <template #prefix>
 <Lock class="w-4 h-4 text-ink-faint" />
 </template>
 </BaseInput>
 </div>

 <div class="space-y-1.5">
 <label class="block text-xs font-semibold text-ink-secondary">
 Confirm New Password <span class="text-[#e03e3e]">*</span>
 </label>
 <BaseInput
 v-model="confirmPassword"
 type="password"
 placeholder="••••••••••••"
 required
 :disabled="isLoading"
 >
 <template #prefix>
 <Lock class="w-4 h-4 text-ink-faint" />
 </template>
 </BaseInput>
 </div>

 <BaseButton
 type="submit"
 variant="primary"
 class="w-full justify-center py-2.5 text-sm font-semibold bg-primary hover:bg-primary-hover text-white rounded-lg transition-colors shadow-xs"
 :loading="isLoading"
 >
 Update Institutional Password
 </BaseButton>
 </form>

 <div class="pt-4 border-t border-hairline text-center">
 <router-link
 to="/login"
 class="inline-flex items-center gap-1.5 text-xs text-primary font-medium hover:underline"
 >
 <ArrowLeft class="w-3.5 h-3.5" />
 Return to Sign In Portal
 </router-link>
 </div>
 </div>
 </div>
 </div>
</template>
