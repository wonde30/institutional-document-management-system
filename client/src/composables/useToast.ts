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

  function resolveToastPayload(arg1: string, arg2?: string): { title?: string; message: string } {
    if (arg2 !== undefined) {
      return { title: arg1, message: arg2 }
    }
    return { message: arg1 }
  }

  function success(messageOrTitle: string, titleOrMessage?: string) {
    const { title, message } = resolveToastPayload(messageOrTitle, titleOrMessage)
    return add({ message, title, type: 'success' })
  }

  function error(messageOrTitle: string, titleOrMessage?: string) {
    const { title, message } = resolveToastPayload(messageOrTitle, titleOrMessage)
    return add({ message, title, type: 'error', duration: 6000 })
  }

  function warning(messageOrTitle: string, titleOrMessage?: string) {
    const { title, message } = resolveToastPayload(messageOrTitle, titleOrMessage)
    return add({ message, title, type: 'warning', duration: 5000 })
  }

  function info(messageOrTitle: string, titleOrMessage?: string) {
    const { title, message } = resolveToastPayload(messageOrTitle, titleOrMessage)
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
