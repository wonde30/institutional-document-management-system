import { defineStore } from 'pinia'
import { ref } from 'vue'
import { notificationsApi } from '@/api/client'
import type { UserNotification } from '@/types'

export const useNotificationStore = defineStore('notifications', () => {
  const notifications = ref<UserNotification[]>([])
  const unreadCount = ref<number>(0)
  const isLoading = ref<boolean>(false)

  async function fetchNotifications() {
    isLoading.value = true
    try {
      const response = await notificationsApi.list()
      const data = response.data.data
      const rawItems = data.items as unknown as UserNotification[] | { data?: UserNotification[] }
      notifications.value = Array.isArray(rawItems) ? rawItems : rawItems?.data || []
      unreadCount.value = data.unread_count || 0
    } catch {
      // Graceful fallback
    } finally {
      isLoading.value = false
    }
  }

  async function markAsRead(id: number) {
    try {
      await notificationsApi.markRead(id)
      const target = notifications.value.find((n) => n.id === id)
      if (target && !target.read_at) {
        target.read_at = new Date().toISOString()
        unreadCount.value = Math.max(0, unreadCount.value - 1)
      }
    } catch {
      // Ignore
    }
  }

  async function markAllAsRead() {
    try {
      await notificationsApi.markAllRead()
      notifications.value.forEach((n) => {
        n.read_at = new Date().toISOString()
      })
      unreadCount.value = 0
    } catch {
      // Ignore
    }
  }

  return {
    notifications,
    unreadCount,
    isLoading,
    fetchNotifications,
    markAsRead,
    markAllAsRead,
  }
})
