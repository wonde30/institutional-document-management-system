<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useThemeStore } from '@/stores/theme'
import BaseButton from '@/components/ui/BaseButton.vue'
import {
  ArrowRight,
  Sun,
  Moon,
  Laptop,
  Menu,
  X,
  Lock,
} from 'lucide-vue-next'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const themeStore = useThemeStore()

const mobileMenuOpen = ref(false)

function scrollToSection(sectionId: string) {
  mobileMenuOpen.value = false
  if (route.path !== '/') {
    router.push(`/#${sectionId}`)
    return
  }
  const el = document.getElementById(sectionId)
  if (el) {
    el.scrollIntoView({ behavior: 'smooth' })
  }
}

onMounted(() => {
  themeStore.init()
})
</script>

<template>
  <header class="sticky top-0 z-50 bg-surface/95 backdrop-blur-md border-b border-hairline transition-colors">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-14 sm:h-16">
        <!-- University Crest & Title -->
        <router-link to="/" class="flex items-center gap-3 group">
          <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-lg bg-primary text-white flex items-center justify-center font-bold text-sm sm:text-base shadow-2xs group-hover:bg-primary-hover transition-colors shrink-0">
            WU
          </div>
          <div class="flex flex-col">
            <span class="text-sm sm:text-base font-bold tracking-tight text-ink group-hover:text-primary transition-colors leading-tight">
              Wollo University
            </span>
            <span class="text-[11px] font-medium text-ink-muted leading-tight">
              Administrative &amp; Document Management System
            </span>
          </div>
        </router-link>

        <!-- Desktop Navigation Links -->
        <nav class="hidden md:flex items-center gap-6 text-xs font-semibold text-ink-muted">
          <a href="#overview" class="hover:text-primary transition-colors" @click.prevent="scrollToSection('overview')">Overview</a>
          <a href="#modules" class="hover:text-primary transition-colors" @click.prevent="scrollToSection('modules')">Core Modules</a>
          <a href="#accreditation" class="hover:text-primary transition-colors" @click.prevent="scrollToSection('accreditation')">Accreditation</a>
          <a href="#governance" class="hover:text-primary transition-colors" @click.prevent="scrollToSection('governance')">Governance</a>
          <router-link to="/privacy-policy" class="hover:text-primary transition-colors">Privacy</router-link>
          <router-link to="/terms" class="hover:text-primary transition-colors">Terms</router-link>
        </nav>

        <!-- Actions -->
        <div class="flex items-center gap-2 sm:gap-3">
          <!-- Theme Toggle -->
          <button
            type="button"
            class="p-2 rounded-lg text-ink-muted hover:bg-surface-hover hover:text-ink transition-colors cursor-pointer"
            aria-label="Toggle visual theme"
            @click="themeStore.toggle"
          >
            <Sun v-if="themeStore.mode === 'light'" class="w-4 h-4 text-amber-500" />
            <Moon v-else-if="themeStore.mode === 'dark'" class="w-4 h-4 text-primary" />
            <Laptop v-else class="w-4 h-4 text-ink-muted" />
          </button>

          <!-- Auth Entry Point -->
          <template v-if="authStore.isAuthenticated">
            <BaseButton
              variant="primary"
              size="sm"
              class="text-xs font-semibold py-1.5 px-3.5 rounded-lg"
              @click="router.push('/dashboard')"
            >
              <span>Workspace</span>
              <ArrowRight class="w-3.5 h-3.5 ml-1" />
            </BaseButton>
          </template>

          <template v-else>
            <BaseButton
              variant="outline"
              size="sm"
              class="text-xs font-semibold py-1.5 px-3.5 rounded-lg"
              @click="router.push('/login')"
            >
              <Lock class="w-3.5 h-3.5 mr-1 text-primary" />
              <span>Sign In</span>
            </BaseButton>
          </template>

          <!-- Mobile Menu Hamburger -->
          <button
            type="button"
            class="md:hidden p-2 rounded-lg text-ink-muted hover:bg-surface-hover hover:text-ink transition-colors cursor-pointer"
            @click="mobileMenuOpen = !mobileMenuOpen"
            aria-label="Toggle mobile menu"
          >
            <X v-if="mobileMenuOpen" class="w-5 h-5" />
            <Menu v-else class="w-5 h-5" />
          </button>
        </div>
      </div>
    </div>

    <!-- Mobile Drawer Menu -->
    <div
      v-if="mobileMenuOpen"
      class="md:hidden border-t border-hairline bg-surface px-4 py-3 space-y-2 text-xs font-semibold text-ink-secondary shadow-lg animate-in fade-in duration-150"
    >
      <a href="#overview" class="block py-2 px-2 rounded-md hover:bg-surface-hover hover:text-primary transition-colors" @click.prevent="scrollToSection('overview')">Overview</a>
      <a href="#modules" class="block py-2 px-2 rounded-md hover:bg-surface-hover hover:text-primary transition-colors" @click.prevent="scrollToSection('modules')">Core Modules</a>
      <a href="#accreditation" class="block py-2 px-2 rounded-md hover:bg-surface-hover hover:text-primary transition-colors" @click.prevent="scrollToSection('accreditation')">Accreditation</a>
      <a href="#governance" class="block py-2 px-2 rounded-md hover:bg-surface-hover hover:text-primary transition-colors" @click.prevent="scrollToSection('governance')">Governance</a>
      <router-link to="/privacy-policy" class="block py-2 px-2 rounded-md hover:bg-surface-hover hover:text-primary transition-colors" @click="mobileMenuOpen = false">Privacy Policy</router-link>
      <router-link to="/terms" class="block py-2 px-2 rounded-md hover:bg-surface-hover hover:text-primary transition-colors" @click="mobileMenuOpen = false">Terms &amp; Conditions</router-link>
    </div>
  </header>
</template>
