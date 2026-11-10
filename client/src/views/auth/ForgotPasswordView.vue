<script setup lang="ts">
import { ref } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import { Mail, ArrowLeft, CheckCircle2, AlertCircle } from 'lucide-vue-next'
import { authApi } from '@/api/client'

const email = ref('')
const isSubmitted = ref(false)
const isLoading = ref(false)
const errorMessage = ref<string | null>(null)

async function handleResetRequest() {
 if (!email.value) return

 isLoading.value = true
 errorMessage.value = null

 try {
  await authApi.forgotPassword({ email: email.value.trim() })
  isSubmitted.value = true
 } catch (error: unknown) {
  const apiError = error as {
   response?: { data?: { message?: string; errors?: Record<string, string[]> } }
  }
  errorMessage.value =
   apiError.response?.data?.errors?.email?.[0] ??
   apiError.response?.data?.message ??
   'Recovery instructions could not be dispatched. Please try again shortly.'
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
 Password Recovery
 </h1>
 <p class="text-xs text-ink-muted mt-0.5">
 Enter your institutional email to receive recovery instructions.
 </p>
 </div>
 </div>

 <div v-if="isSubmitted" class="p-4 rounded-xl bg-[#e8f7ec] dark:bg-[#14301d] border border-[#1aae39]/20 space-y-2">
 <div class="flex items-center gap-2 text-xs font-semibold text-[#1aae39] dark:text-[#34d399]">
 <CheckCircle2 class="w-4 h-4 shrink-0" />
 <span>Request Processed</span>
 </div>
 <p class="text-xs text-ink leading-relaxed">
 If an active institutional account matches <strong>{{ email }}</strong>, recovery instructions have been queued. Please check your inbox or contact your college Information Technology administrator.
 </p>
 </div>

 <form v-else @submit.prevent="handleResetRequest" class="space-y-4">
 <div class="space-y-1.5">
 <label class="block text-xs font-semibold text-ink-secondary">
 Institutional Email <span class="text-[#e03e3e]">*</span>
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

 <BaseButton
 type="submit"
 variant="primary"
 class="w-full justify-center py-2.5 text-sm font-semibold bg-primary hover:bg-primary-hover text-white rounded-lg transition-colors shadow-xs"
 :loading="isLoading"
 >
 Send Recovery Link
 </BaseButton>

 <div v-if="errorMessage" class="p-3 rounded-xl bg-[#fdecec] dark:bg-[#3a1414] border border-[#e03e3e]/20 flex items-start gap-2" role="alert">
 <AlertCircle class="w-4 h-4 shrink-0 mt-0.5 text-[#e03e3e] dark:text-[#f87171]" />
 <p class="text-xs text-ink leading-relaxed">{{ errorMessage }}</p>
 </div>
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
