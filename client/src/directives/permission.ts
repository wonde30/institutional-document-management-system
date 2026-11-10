import type { Directive, DirectiveBinding } from 'vue'
import { useAuthStore } from '@/stores/auth'

function checkPermission(el: HTMLElement, binding: DirectiveBinding) {
  const { value } = binding
  const authStore = useAuthStore()

  if (!value) return

  const hasPermission = authStore.hasPermission(value)

  if (!hasPermission) {
    if (binding.modifiers.disable) {
      el.setAttribute('disabled', 'true')
      el.classList.add('opacity-50', 'pointer-events-none', 'cursor-not-allowed')
      el.setAttribute('title', `Action blocked: requires [${value}] permission.`)
    } else {
      if (el.parentNode) {
        el.parentNode.removeChild(el)
      } else {
        el.style.display = 'none'
      }
    }
  } else if (binding.modifiers.disable) {
    el.removeAttribute('disabled')
    el.classList.remove('opacity-50', 'pointer-events-none', 'cursor-not-allowed')
    el.removeAttribute('title')
  }
}

export const vPermission: Directive = {
  mounted(el, binding) {
    checkPermission(el, binding)
  },
  updated(el, binding) {
    checkPermission(el, binding)
  },
}
