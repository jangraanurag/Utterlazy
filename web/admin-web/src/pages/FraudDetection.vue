<template>
  <div>
    <div class="flex justify-between items-center mb-8">
      <h1 class="font-display text-2xl font-bold">Fraud Detection</h1>
      <div class="flex gap-2">
        <button v-for="sev in ['all','low','medium','high','critical']" :key="sev"
          :class="filter === sev ? 'bg-primary/10 text-primary border-primary/20' : 'bg-white/5 text-on-surface-variant border-white/5'"
          class="px-3 py-1.5 rounded-lg text-xs font-bold border uppercase tracking-wider" @click="filter = sev">
          {{ sev }}
        </button>
      </div>
    </div>

    <div v-if="loading" class="text-center py-12 text-on-surface-variant">Loading...</div>

    <div v-else-if="error" class="text-center py-12 text-red-500">{{ error }}</div>

    <template v-else>
      <div class="grid grid-cols-4 gap-4 mb-8">
        <div class="bg-surface rounded-xl border border-outline-variant/30 p-4 text-center">
          <p class="text-2xl font-bold text-red-500">{{ severityCounts.critical }}</p>
          <p class="text-xs text-on-surface-variant">Critical</p>
        </div>
        <div class="bg-surface rounded-xl border border-outline-variant/30 p-4 text-center">
          <p class="text-2xl font-bold text-orange-500">{{ severityCounts.high }}</p>
          <p class="text-xs text-on-surface-variant">High</p>
        </div>
        <div class="bg-surface rounded-xl border border-outline-variant/30 p-4 text-center">
          <p class="text-2xl font-bold text-yellow-500">{{ severityCounts.medium }}</p>
          <p class="text-xs text-on-surface-variant">Medium</p>
        </div>
        <div class="bg-surface rounded-xl border border-outline-variant/30 p-4 text-center">
          <p class="text-2xl font-bold text-green-500">{{ severityCounts.low }}</p>
          <p class="text-xs text-on-surface-variant">Low</p>
        </div>
      </div>

      <div v-if="filteredLogs.length === 0" class="text-center py-12 text-on-surface-variant">No fraud logs found.</div>

      <div v-else class="space-y-3">
        <div v-for="log in filteredLogs" :key="log.id" class="bg-surface rounded-xl border border-outline-variant/30 p-5 flex items-center gap-4">
          <div :class="severityColor(log.severity)" class="w-10 h-10 rounded-xl flex items-center justify-center">
            <span class="material-symbols-outlined text-white text-[18px]">{{ severityIcon(log.type) }}</span>
          </div>
          <div class="flex-1">
            <div class="flex items-center gap-2">
              <h4 class="font-bold text-sm">{{ log.type.replace(/_/g, ' ').toUpperCase() }}</h4>
              <span :class="severityBadge(log.severity)" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase">{{ log.severity }}</span>
              <span v-if="log.status === 'reviewed'" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-green-100 text-green-700">Reviewed</span>
            </div>
            <p class="text-xs text-on-surface-variant mt-1">{{ log.description }}</p>
            <p class="text-[10px] text-on-surface-variant mt-0.5">{{ log.entity_type }} #{{ log.entity_id }}</p>
          </div>
          <span class="text-xs text-on-surface-variant">{{ timeAgo(log.created_at) }}</span>
          <button v-if="log.status !== 'reviewed'" @click="reviewLog(log.id)" :disabled="reviewingId === log.id" class="px-3 py-1.5 rounded-lg bg-primary/10 text-primary text-xs font-bold disabled:opacity-50">Mark Reviewed</button>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { getFraudLogs, reviewFraudLog } from '../api.js'

const filter = ref('all')
const logs = ref([])
const loading = ref(true)
const error = ref(null)
const reviewingId = ref(null)

const severityCounts = computed(() => {
  const c = { critical: 0, high: 0, medium: 0, low: 0 }
  logs.value.forEach(l => { if (c[l.severity] !== undefined) c[l.severity]++ })
  return c
})

const filteredLogs = computed(() =>
  filter.value === 'all' ? logs.value : logs.value.filter(l => l.severity === filter.value)
)

const timeAgo = (d) => {
  if (!d) return ''
  const diff = Math.floor((Date.now() - new Date(d).getTime()) / 1000)
  if (diff < 60) return diff + 's ago'
  if (diff < 3600) return Math.floor(diff / 60) + 'm ago'
  if (diff < 86400) return Math.floor(diff / 3600) + 'h ago'
  return Math.floor(diff / 86400) + 'd ago'
}

const severityColor = (s) => ({ critical:'bg-red-500', high:'bg-orange-500', medium:'bg-yellow-500', low:'bg-green-500' }[s])
const severityBadge = (s) => ({ critical:'bg-red-100 text-red-700', high:'bg-orange-100 text-orange-700', medium:'bg-yellow-100 text-yellow-700', low:'bg-green-100 text-green-700' }[s])
const severityIcon = (t) => {
  if (t.includes('fake') || t.includes('fraud')) return 'block'
  if (t.includes('gps') || t.includes('spoof')) return 'my_location'
  if (t.includes('abuse') || t.includes('claim')) return 'person_off'
  if (t.includes('quality') || t.includes('rating')) return 'star'
  return 'warning'
}

const reviewLog = async (id) => {
  reviewingId.value = id
  try {
    await reviewFraudLog(id)
    const log = logs.value.find(l => l.id === id)
    if (log) log.status = 'reviewed'
  } catch (e) {
    error.value = e.message || 'Failed to review'
  } finally {
    reviewingId.value = null
  }
}

onMounted(async () => {
  try {
    const data = await getFraudLogs()
    logs.value = data.data || []
  } catch (e) {
    error.value = e.message || 'Failed to load'
  } finally {
    loading.value = false
  }
})
</script>
