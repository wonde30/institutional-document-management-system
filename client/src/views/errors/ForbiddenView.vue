<script setup lang="ts">
import { useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import { ShieldAlert, ArrowLeft, Home } from 'lucide-vue-next'

const router = useRouter()
</script>

<template>
 <div class="min-h-[70vh] flex items-center justify-center p-4">
 <div class="max-w-md w-full bg-surface border border-hairline rounded-2xl shadow-xs p-8 text-center space-y-5">
 <div class="inline-flex w-14 h-14 rounded-xl bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-400 items-center justify-center border border-amber-200 dark:border-amber-900">
 <ShieldAlert class="w-7 h-7" />
 </div>

 <div class="space-y-2">
 <h1 class="text-xl font-bold text-ink-secondary tracking-tight">
 Access Restricted (403 Forbidden)
 </h1>
 <p class="text-xs text-ink-muted leading-relaxed">
 Your active institutional account does not hold the required authorization scope or capability to access this resource. Internal resource metadata has not been disclosed.
 </p>
 </div>

 <div class="pt-2 flex items-center justify-center gap-3">
 <BaseButton variant="outline" size="sm" @click="router.back()">
 <ArrowLeft class="w-3.5 h-3.5 mr-1.5" />
 Return to Previous Page
 </BaseButton>

 <BaseButton
 variant="primary"
 size="sm"
 @click="router.push('/dashboard')"
 >
 <Home class="w-3.5 h-3.5 mr-1.5" />
 Workspace Dashboard
 </BaseButton>
 </div>
 </div>
 </div>
</template>
