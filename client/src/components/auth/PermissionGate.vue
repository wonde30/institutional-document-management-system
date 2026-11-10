<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'

const props = withDefaults(
 defineProps<{
 permission?: string
 role?: string
 behavior?: 'hide' | 'disable'
 disabledReason?: string
 }>(),
 {
 behavior: 'hide',
 disabledReason: 'You do not have the required institutional permission for this action.',
 }
)

const authStore = useAuthStore()

const isAuthorized = computed(() => {
 if (authStore.isSuperAdmin) return true

 if (props.role && !authStore.hasRole(props.role)) {
 return false
 }

 if (props.permission && !authStore.hasPermission(props.permission)) {
 return false
 }

 return true
})
</script>

<template>
 <template v-if="isAuthorized">
 <slot />
 </template>

 <template v-else-if="behavior === 'disable'">
 <div class="relative group inline-block cursor-not-allowed">
 <div class="opacity-50 pointer-events-none select-none">
 <slot />
 </div>
 <div
 class="hidden group-hover:block absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 px-2.5 py-1 text-[11px] text-white bg-slate-800 rounded shadow-lg whitespace-nowrap z-50 pointer-events-none"
 >
 {{ disabledReason }}
 </div>
 </div>
 </template>

 <template v-else>
 <slot name="fallback" />
 </template>
</template>
