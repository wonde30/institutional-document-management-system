<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch, nextTick } from 'vue'
import { X } from 'lucide-vue-next'

const props = withDefaults(
  defineProps<{
    isOpen?: boolean
    show?: boolean
    open?: boolean
    modelValue?: boolean
    title: string
    size?: 'sm' | 'md' | 'lg' | 'xl'
    maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | string
  }>(),
  {
    isOpen: undefined,
    show: undefined,
    open: undefined,
    modelValue: undefined,
    size: 'md',
  }
)

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'update:modelValue', value: boolean): void
}>()

const active = computed(() => props.open ?? props.show ?? props.isOpen ?? props.modelValue ?? false)

const dialogRef = ref<HTMLElement | null>(null)
const titleId = `modal-title-${Math.random().toString(36).substring(2, 9)}`

function handleKeyDown(event: KeyboardEvent) {
  if (event.key === 'Escape' && active.value) {
    emit('close')
    emit('update:modelValue', false)
  }

  // Focus trap: keep Tab within the dialog
  if (event.key === 'Tab' && active.value && dialogRef.value) {
    const focusable = dialogRef.value.querySelectorAll<HTMLElement>(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    )
    if (focusable.length === 0) return

    const first = focusable[0]
    const last = focusable[focusable.length - 1]

    if (event.shiftKey) {
      if (document.activeElement === first) {
        event.preventDefault()
        last.focus()
      }
    } else {
      if (document.activeElement === last) {
        event.preventDefault()
        first.focus()
      }
    }
  }
}

const sizeClasses = computed(() => {
  const s = props.maxWidth || props.size || 'md'
  switch (s) {
    case 'sm':  return 'max-w-sm'
    case 'lg':  return 'max-w-2xl'
    case 'xl':  return 'max-w-4xl'
    case 'md':
    default:    return 'max-w-lg'
  }
})

// Focus the dialog when it opens
watch(active, async (isActive) => {
  if (isActive) {
    await nextTick()
    dialogRef.value?.focus()
  }
})

onMounted(() => window.addEventListener('keydown', handleKeyDown))
onUnmounted(() => window.removeEventListener('keydown', handleKeyDown))
</script>

<template>
  <Transition
    enter-active-class="transition ease-out duration-200"
    enter-from-class="opacity-0"
    enter-to-class="opacity-100"
    leave-active-class="transition ease-in duration-150"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div
      v-if="active"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs modal-overlay"
      @click.self="() => { emit('close'); emit('update:modelValue', false); }"
    >
      <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0 scale-95 translate-y-1"
        enter-to-class="opacity-100 scale-100 translate-y-0"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100 scale-100 translate-y-0"
        leave-to-class="opacity-0 scale-95 translate-y-1"
        appear
      >
        <div
          v-if="active"
          ref="dialogRef"
          tabindex="-1"
          class="w-full bg-surface rounded-xl shadow-lg border border-hairline overflow-hidden transform modal-dialog focus:outline-hidden"
          :class="sizeClasses"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="titleId"
        >
          <!-- Modal Header -->
          <div class="px-5 py-4 border-b border-hairline flex items-center justify-between modal-header">
            <h3 :id="titleId" class="text-base font-semibold text-ink card-title">
              {{ title }}
            </h3>
            <button
              type="button"
              class="p-1.5 text-ink-faint hover:text-ink rounded-lg hover:bg-canvas-soft transition-colors cursor-pointer btn-close"
              @click="() => { emit('close'); emit('update:modelValue', false); }"
              aria-label="Close dialog"
            >
              <X class="w-4 h-4" />
            </button>
          </div>

          <!-- Modal Body -->
          <div class="p-5 max-h-[calc(85vh-120px)] overflow-y-auto scrollbar-thin modal-body text-ink-secondary">
            <slot name="body">
              <slot></slot>
            </slot>
          </div>

          <!-- Modal Footer -->
          <div
            v-if="$slots.footer"
            class="px-5 py-3.5 bg-canvas-soft border-t border-hairline flex items-center justify-end gap-2.5 modal-footer"
          >
            <slot name="footer"></slot>
          </div>
        </div>
      </Transition>
    </div>
  </Transition>
</template>
