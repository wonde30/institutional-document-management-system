<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import BaseButton from '@/components/ui/BaseButton.vue'
import { ArrowRight, Lock, Building2 } from 'lucide-vue-next'

const router = useRouter()
const authStore = useAuthStore()

function handleCtaClick() {
  if (authStore.isAuthenticated) {
    router.push('/dashboard')
  } else {
    router.push('/login')
  }
}
</script>

<template>
  <section
    id="access"
    class="py-16 sm:py-20 bg-surface border-b border-hairline transition-colors relative overflow-hidden"
    aria-labelledby="cta-heading"
  >
    <!-- Background subtle gradient accent -->
    <div
      class="absolute inset-0 bg-radial from-primary/5 via-transparent to-transparent pointer-events-none"
      aria-hidden="true"
    />

    <div class="max-w-4xl mx-auto px-4 sm:px-6 text-center space-y-5 sm:space-y-6 relative">
      <div
        class="w-12 h-12 rounded-xl bg-primary text-white flex items-center justify-center font-bold text-base mx-auto shadow-md"
      >
        WU
      </div>

      <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-md text-xs font-semibold bg-primary-subtle text-primary border border-primary/20">
        <Lock class="w-3.5 h-3.5" />
        <span>Authorized Personnel Only</span>
      </div>

      <h2 id="cta-heading" class="text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight text-ink">
        Authorized Staff Workspace Access
      </h2>

      <p class="text-xs sm:text-sm text-ink-muted max-w-xl mx-auto leading-relaxed">
        Access to WU-DMS is restricted to active Wollo University academic and administrative staff. Sign in with your verified institutional account (<span class="font-mono text-xs text-primary font-semibold">@wu.edu.et</span>) to manage documents, inter-office dispatches, and quality assurance workflows.
      </p>

      <div class="pt-2 flex flex-col sm:flex-row items-center justify-center gap-3">
        <BaseButton
          variant="primary"
          size="md"
          class="text-xs sm:text-sm font-semibold py-2.5 px-6 rounded-lg shadow-sm hover:shadow transition-all w-full sm:w-auto"
          @click="handleCtaClick"
        >
          <span>{{ authStore.isAuthenticated ? 'Open Workspace Dashboard' : 'Sign In with Institutional ID' }}</span>
          <ArrowRight class="w-4 h-4 ml-1.5" />
        </BaseButton>

        <a
          href="#overview"
          class="inline-flex items-center justify-center px-5 py-2.5 rounded-lg border border-hairline text-xs sm:text-sm font-semibold text-ink-secondary hover:bg-surface-hover hover:text-ink transition-colors w-full sm:w-auto cursor-pointer"
        >
          <span>Back to Top</span>
        </a>
      </div>
    </div>
  </section>
</template>
