<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { authApi } from '@/api/client'
import BaseButton from '@/components/ui/BaseButton.vue'
import { MailCheck, LogOut, CheckCircle2, AlertCircle } from 'lucide-vue-next'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const isResent = ref(false)
const isLoading = ref(false)
const isVerifying = ref(false)
const isVerified = ref(false)
const errorMessage = ref('')

const maskedEmail = computed(() => {
 const email = authStore.user?.email || 'user@wu.edu.et'
 const [name, domain] = email.split('@')
 if (name.length <= 2) return `${name}***@${domain}`
 return `${name[0]}***${name[name.length - 1]}@${domain}`
})

async function handleResend() {
 isLoading.value = true
 errorMessage.value = ''

 try {
  await authApi.sendVerificationEmail()
  isResent.value = true
 } catch (error: unknown) {
  const apiError = error as { response?: { data?: { message?: string } } }
  errorMessage.value =
   apiError.response?.data?.message ??
   'The verification notice could not be dispatched. Please try again shortly.'
 } finally {
  isLoading.value = false
 }
}

async function handleLogout() {
 await authStore.logout()
 router.push('/login')
}

/**
 * Complete the confirmation when the view is opened from the link that was
 * delivered by mail.
 */
onMounted(async () => {
 const id = route.query.id as string | undefined
 const hash = route.query.hash as string | undefined

 if (!id || !hash) {
  return
 }

 isVerifying.value = true

 try {
  await authApi.verifyEmail(id, hash)
  isVerified.value = true
  await authStore.fetchUser()
 } catch (error: unknown) {
  const apiError = error as { response?: { data?: { message?: string } } }
  errorMessage.value =
   apiError.response?.data?.message ??
   'The confirmation link is invalid or has expired.'
 } finally {
  isVerifying.value = false
 }
})
</script>

<template>
 <div class="min-h-screen bg-canvas-soft flex items-center justify-center p-4 select-none">
 <div class="w-full max-w-md">
 <div class="bg-surface border border-hairline rounded-xl p-8 sm:p-10 space-y-6">
 <div class="text-center space-y-2">
 <div class="inline-flex w-12 h-12 rounded-xl bg-primary text-white items-center justify-center font-bold text-lg">
 <MailCheck class="w-6 h-6" />
 </div>
 <div>
 <h1 class="text-2xl font-bold text-ink tracking-tight">
 Email Verification
 </h1>
 <p class="text-xs text-ink-muted mt-0.5">
 Institutional account confirmation required
 </p>
 </div>
 </div>

 <div v-if="isVerified" class="p-4 rounded-xl bg-[#e8f7ec] dark:bg-[#14301d] border border-[#1aae39]/20 space-y-2">
 <div class="flex items-center gap-2 text-xs font-semibold text-[#1aae39] dark:text-[#34d399]">
 <CheckCircle2 class="w-4 h-4 shrink-0" />
 <span>Address Confirmed</span>
 </div>
 <p class="text-xs text-ink leading-relaxed">
 Your institutional identity has been confirmed. You may now continue to the workspace.
 </p>
 </div>

 <div v-else-if="errorMessage" class="p-3 rounded-lg bg-[#fdecec] dark:bg-[#3a1414] border border-[#e03e3e]/20 flex items-start gap-2" role="alert">
 <AlertCircle class="w-4 h-4 shrink-0 mt-0.5 text-[#e03e3e] dark:text-[#f87171]" />
 <p class="text-xs text-ink leading-relaxed">{{ errorMessage }}</p>
 </div>

 <div v-else class="p-4 rounded-xl bg-canvas-soft border border-hairline space-y-2">
 <p class="text-xs text-ink leading-relaxed">
 A confirmation link was sent to your registered address:
 <strong class="text-xs">{{ maskedEmail }}</strong>.
 </p>
 <p class="text-xs text-ink-muted">
 For data security, access to protected repository records remains restricted until your institutional identity is confirmed.
 </p>
 </div>

 <div v-if="isVerifying" class="p-3 rounded-lg bg-canvas-soft border border-hairline text-xs text-ink-muted flex items-center gap-2">
 <span class="inline-block w-3 h-3 rounded-full border-2 border-primary border-t-transparent animate-spin" aria-hidden="true"></span>
 <span>Confirming your institutional address…</span>
 </div>

 <div v-if="isResent" class="p-3 rounded-lg bg-[#e8f7ec] dark:bg-[#14301d] text-xs text-[#1aae39] dark:text-[#34d399] flex items-center gap-2">
 <CheckCircle2 class="w-4 h-4 shrink-0" />
 <span>Fresh verification link dispatched to your inbox.</span>
 </div>

 <div class="space-y-2.5">
 <BaseButton
 variant="primary"
 class="w-full justify-center py-2.5 text-sm font-semibold bg-primary hover:bg-primary-hover text-white rounded-lg transition-colors"
 :loading="isLoading"
 @click="handleResend"
 >
 Resend Verification Link
 </BaseButton>

 <BaseButton
 variant="outline"
 class="w-full justify-center py-2 text-xs font-semibold rounded-lg"
 @click="handleLogout"
 >
 <LogOut class="w-3.5 h-3.5 mr-1.5" />
 Sign Out of Session
 </BaseButton>
 </div>
 </div>
 </div>
 </div>
</template>
