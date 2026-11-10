<script setup lang="ts">
import { useToast } from '@/composables/useToast'
import { CheckCircle2, AlertCircle, AlertTriangle, Info, X } from 'lucide-vue-next'

const { toasts, remove } = useToast()
</script>

<template>
 <div
 class="fixed bottom-5 right-5 z-50 flex flex-col gap-2.5 max-w-sm w-full pointer-events-none"
 role="region"
 aria-live="polite"
 aria-label="Notifications"
 >
 <transition-group
 enter-active-class="transform ease-out duration-300 transition"
 enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
 enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
 leave-active-class="transition ease-in duration-200"
 leave-from-class="opacity-100"
 leave-to-class="opacity-0 scale-95"
 >
 <div
 v-for="toast in toasts"
 :key="toast.id"
 class="pointer-events-auto flex items-start gap-3 p-4 rounded-xl border shadow-lg backdrop-blur-xs transition"
 :class="{
 'bg-white border-emerald-200 text-slate-800 dark:bg-slate-900 dark:border-emerald-800/60 dark:text-slate-100': toast.type === 'success',
 'bg-white border-rose-200 text-slate-800 dark:bg-slate-900 dark:border-rose-800/60 dark:text-slate-100': toast.type === 'error',
 'bg-white border-amber-200 text-slate-800 dark:bg-slate-900 dark:border-amber-800/60 dark:text-slate-100': toast.type === 'warning',
 'bg-white border-blue-200 text-slate-800 dark:bg-slate-900 dark:border-blue-800/60 dark:text-slate-100': toast.type === 'info',
 }"
 >
 <!-- Icon -->
 <div class="shrink-0 mt-0.5">
 <CheckCircle2 v-if="toast.type === 'success'" class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
 <AlertCircle v-else-if="toast.type === 'error'" class="w-5 h-5 text-rose-600 dark:text-rose-400" />
 <AlertTriangle v-else-if="toast.type === 'warning'" class="w-5 h-5 text-amber-600 dark:text-amber-400" />
 <Info v-else class="w-5 h-5 text-blue-600 dark:text-blue-400" />
 </div>

 <!-- Content -->
 <div class="flex-1 min-w-0">
 <h4 v-if="toast.title" class="text-xs font-bold uppercase tracking-wider mb-0.5 text-slate-900 dark:text-white">
 {{ toast.title }}
 </h4>
 <p class="text-xs font-medium text-slate-700 dark:text-slate-300 break-words leading-relaxed">
 {{ toast.message }}
 </p>
 </div>

 <!-- Close button -->
 <button
 type="button"
 class="shrink-0 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition p-1 rounded-md"
 aria-label="Dismiss notification"
 @click="remove(toast.id)"
 >
 <X class="w-4 h-4" />
 </button>
 </div>
 </transition-group>
 </div>
</template>
