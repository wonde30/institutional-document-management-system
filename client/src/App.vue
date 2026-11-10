<script setup lang="ts">
import { useRoute } from 'vue-router'
import AppLayout from '@/layouts/AppLayout.vue'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const authStore = useAuthStore()
</script>

<template>
  <div id="app-root">
    <!-- Clean Layout for Public Pages, Guest-Only Routes, or Unauthenticated State -->
    <template v-if="route.meta.guestOnly || route.meta.isPublic || !authStore.isAuthenticated">
      <router-view />
    </template>

    <!-- Institutional Enterprise Shell for Authenticated Application Routes -->
    <template v-else>
      <AppLayout>
        <router-view />
      </AppLayout>
    </template>
  </div>
</template>

<style>
#app-root {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}
</style>
