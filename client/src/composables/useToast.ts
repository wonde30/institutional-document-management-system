import { ref } from 'vue'

export interface Toast {
  id: string
  title?: string
  message: string
  type: 'success' | 'error' | 'warning' | 'info'
  duration?: number
}

const toasts = ref<Toast[]>([])

export function useToast() {
  function add(toast: Omit<Toast, 'id'>) {
    const id = Math.random().toString(36).substring(2, 9)
    const newToast: Toast = {
      id,
      duration: 4000,
      ...toast,
    }

    toasts.value.push(newToast)

    if (newToast.duration && newToast.duration > 0) {
      setTimeout(() => {
        remove(id)
      }, newToast.duration)
    }

    return id
  }

  function remove(id: string) {
    toasts.value = toasts.value.filter((t) => t.id !== id)
  }

  function success(message: string, title?: string) {
    return add({ message, title, type: 'success' })
  }

  function error(message: string, title?: string) {
    return add({ message, title, type: 'error', duration: 6000 })
  }

  function warning(message: string, title?: string) {
    return add({ message, title, type: 'warning', duration: 5000 })
  }

  function info(message: string, title?: string) {
    return add({ message, title, type: 'info' })
  }

  return {
    toasts,
    add,
    remove,
    success,
    error,
    warning,
    info,
  }
}
