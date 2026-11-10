<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import BaseButton from '@/components/ui/BaseButton.vue'
import {
 ShieldCheck,
 ArrowRight,
 Sun,
 Moon,
 Menu,
 X,
 FileText,
 Lock,
} from 'lucide-vue-next'

const router = useRouter()
const authStore = useAuthStore()

const isDark = ref(false)
const mobileMenuOpen = ref(false)

function toggleTheme() {
 isDark.value = !isDark.value
 if (isDark.value) {
 document.documentElement.classList.add('dark')
 localStorage.setItem('wu-theme', 'dark')
 } else {
 document.documentElement.classList.remove('dark')
 localStorage.setItem('wu-theme', 'light')
 }
}

onMounted(() => {
 const saved = localStorage.getItem('wu-theme')
 if (saved === 'dark' || (!saved && typeof window !== 'undefined' && typeof window.matchMedia === 'function' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
 isDark.value = true
 document.documentElement.classList.add('dark')
 } else {
 isDark.value = false
 document.documentElement.classList.remove('dark')
 }
})
</script>

<template>
 <header class="sticky top-0 z-50 bg-white/95 dark:bg-[#191919]/95 backdrop-blur-md border-b border-hairline transition-colors">
 <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
 <div class="flex items-center justify-between h-16 sm:h-20">
 <!-- University Crest & Title -->
 <router-link to="/" class="flex items-center gap-3.5 group">
 <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-lg bg-primary text-white flex items-center justify-center font-bold text-base shadow-xs group-hover:bg-primary-hover transition-colors shrink-0">
 WU
 </div>
 <div class="flex flex-col">
 <span class="text-sm sm:text-base font-bold tracking-tight text-ink-secondary group-hover:text-primary transition-colors">
 Wollo University
 </span>
 <span class="text-[11px] sm:text-xs font-medium text-ink-muted">
 Administrative &amp; Document Management System
 </span>
 </div>
 </router-link>

 <!-- Desktop Navigation Links -->
 <nav class="hidden md:flex items-center gap-7 text-xs font-semibold text-ink-muted">
 <a href="/#overview" class="hover:text-primary transition-colors">Overview</a>
 <a href="/#modules" class="hover:text-primary transition-colors">Core Modules</a>
 <a href="/#accreditation" class="hover:text-primary transition-colors">Accreditation</a>
 <a href="/#governance" class="hover:text-primary transition-colors">Governance</a>
 <router-link to="/privacy-policy" class="hover:text-primary transition-colors">Privacy</router-link>
 <router-link to="/terms" class="hover:text-primary transition-colors">Terms</router-link>
 </nav>

 <!-- Actions -->
 <div class="flex items-center gap-3">
 <!-- Theme Toggle -->
 <button
 type="button"
 class="p-2 rounded-lg text-ink-muted hover:bg-surface-hover transition-colors cursor-pointer"
 aria-label="Toggle visual theme"
 @click="toggleTheme"
 >
 <Sun v-if="isDark" class="w-4 h-4 text-amber-400" />
 <Moon v-else class="w-4 h-4 text-ink-muted" />
 </button>

 <!-- Auth Entry Point -->
 <template v-if="authStore.isAuthenticated">
 <BaseButton
 variant="primary"
 size="sm"
 class="text-xs font-semibold py-2 px-3.5 rounded-lg"
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
 class="text-xs font-semibold py-2 px-3.5 rounded-lg"
 @click="router.push('/login')"
 >
 <Lock class="w-3.5 h-3.5 mr-1 text-primary" />
 <span>Sign In</span>
 </BaseButton>
 </template>

 <!-- Mobile Menu Hamburger -->
 <button
 type="button"
 class="md:hidden p-2 rounded-lg text-ink-muted hover:bg-surface-hover transition-colors cursor-pointer"
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
 class="md:hidden border-t border-hairline bg-surface px-4 py-4 space-y-3 text-xs font-semibold text-ink-secondary"
 >
 <a href="/#overview" class="block py-2 hover:text-primary" @click="mobileMenuOpen = false">Overview</a>
 <a href="/#modules" class="block py-2 hover:text-primary" @click="mobileMenuOpen = false">Core Modules</a>
 <a href="/#accreditation" class="block py-2 hover:text-primary" @click="mobileMenuOpen = false">Accreditation</a>
 <a href="/#governance" class="block py-2 hover:text-primary" @click="mobileMenuOpen = false">Governance</a>
 <router-link to="/privacy-policy" class="block py-2 hover:text-primary" @click="mobileMenuOpen = false">Privacy Policy</router-link>
 <router-link to="/terms" class="block py-2 hover:text-primary" @click="mobileMenuOpen = false">Terms &amp; Conditions</router-link>
 </div>
 </header>
</template>
