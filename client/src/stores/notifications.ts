import { defineStore } from 'pinia'
import { ref } from 'vue'
import { notificationsApi } from '@/api/client'
import type { UserNotification } from '@/types'

export const useNotificationStore = defineStore('notifications', () => {
  const notifications = ref<UserNotification[]>([])
  const unreadCount = ref<number>(0)
  const isLoading = ref<boolean>(false)

  /**
   * The request that is currently in flight, if any.
   *
   * The shell and the notifications screen both read the list. A second call
   * while the first is running joins it instead of issuing another request.
   */
  let pending: Promise<void> | null = null

  async function fetchNotifications(): Promise<void> {
    if (pending) return pending

    pending = (async () => {
      isLoading.value = true
      try {
        const response = await notificationsApi.list()
        const data = response.data.data
        notifications.value = data.items || []
        unreadCount.value = data.unread_count || 0
      } catch {
        // Graceful fallback
      } finally {
        isLoading.value = false
        pending = null
      }
    })()

    return pending
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
