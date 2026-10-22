<template>
  <svg :width="width" :height="height" :viewBox="`0 0 ${width} ${height}`" class="w-full">
    <polyline
      :points="points"
      :fill="trend === 'up' ? 'rgba(34, 197, 94, 0.08)' : trend === 'down' ? 'rgba(239, 68, 68, 0.08)' : 'rgba(255,255,255,0.05)'"
      stroke="none"
    />
    <polyline
      :points="points"
      :stroke="trend === 'up' ? '#22c55e' : trend === 'down' ? '#ef4444' : '#6b7280'"
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
  trend: { type: String, default: 'flat' },
  width: { type: Number, default: 120 },
  height: { type: Number, default: 30 },
})

const points = computed(() => {
  const d = props.data
  if (!d.length) return ''
  const max = Math.max(...d, 1)
  const w = props.width
  const h = props.height - 4
  return d.map((v, i) => {
    const x = (i / (d.length - 1 || 1)) * w
    const y = h - (v / max) * h + 2
    return `${x.toFixed(1)},${y.toFixed(1)}`
  }).join(' ')
})
</script>
