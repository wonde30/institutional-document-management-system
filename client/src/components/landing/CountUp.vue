<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'

const props = withDefaults(
  defineProps<{
    value: number
    duration?: number
    prefix?: string
    suffix?: string
  }>(),
  {
    duration: 1200,
    prefix: '',
    suffix: '',
  }
)

const displayValue = ref<number>(0)

function animate() {
  if (typeof window === 'undefined') {
    displayValue.value = props.value
    return
  }

  // Respect user preference for reduced motion
  const prefersReduced =
    typeof window !== 'undefined' && typeof window.matchMedia === 'function'
      ? window.matchMedia('(prefers-reduced-motion: reduce)').matches
      : false
  if (prefersReduced) {
    displayValue.value = props.value
    return
  }

  const start = 0
  const end = props.value
  const startTime = performance.now()

  function step(currentTime: number) {
    const elapsed = currentTime - startTime
    const progress = Math.min(elapsed / props.duration, 1)

    // Ease-out cubic formula
    const easeOut = 1 - Math.pow(1 - progress, 3)
    displayValue.value = Math.floor(start + (end - start) * easeOut)

    if (progress < 1) {
      requestAnimationFrame(step)
    } else {
      displayValue.value = end
    }
  }

  requestAnimationFrame(step)
}

watch(
  () => props.value,
  () => {
    animate()
  }
)

onMounted(() => {
  animate()
})
</script>

<template>
  <span class="tabular-nums">
    {{ prefix }}{{ displayValue.toLocaleString() }}{{ suffix }}
  </span>
</template>
