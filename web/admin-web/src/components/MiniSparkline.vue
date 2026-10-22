<template>
  <svg :width="width" :height="height" :viewBox="`0 0 ${width} ${height}`">
    <polyline
      :points="points"
      stroke="rgba(168, 85, 247, 0.7)"
      fill="none"
      stroke-width="1.5"
      stroke-linecap="round"
      stroke-linejoin="round"
    />
  </svg>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  data: { type: Array, default: () => [] },
  width: { type: Number, default: 80 },
  height: { type: Number, default: 24 },
})

const points = computed(() => {
  const d = props.data
  if (!d.length) return ''
  const max = Math.max(...d, 1)
  const w = props.width
  const h = props.height - 2
  return d.map((v, i) => {
    const x = (i / (d.length - 1 || 1)) * w
    const y = h - (v / max) * h + 1
    return `${x.toFixed(1)},${y.toFixed(1)}`
  }).join(' ')
})
</script>
