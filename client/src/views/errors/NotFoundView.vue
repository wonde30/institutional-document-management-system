<script setup lang="ts">
import { useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import { FileQuestion, Home, ArrowLeft } from 'lucide-vue-next'

const router = useRouter()
</script>

<template>
 <div class="min-h-[70vh] flex items-center justify-center p-4">
 <div class="max-w-md w-full bg-surface border border-hairline rounded-2xl shadow-xs p-8 text-center space-y-5">
 <div class="inline-flex w-14 h-14 rounded-xl bg-canvas-soft text-ink-muted items-center justify-center border border-hairline">
 <FileQuestion class="w-7 h-7" />
 </div>

 <div class="space-y-2">
 <h1 class="text-xl font-bold text-ink-secondary tracking-tight">
 Page Not Found (404)
 </h1>
 <p class="text-xs text-ink-muted leading-relaxed">
 The requested system destination or record could not be found, or you may not hold permission to view its metadata.
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
