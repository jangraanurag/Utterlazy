<template>
  <div>
    <div class="flex justify-between items-center mb-8">
      <h1 class="font-display text-2xl font-bold">Complaint Management</h1>
      <div class="flex gap-2">
        <button v-for="f in ['all', 'pending', 'resolved', 'rejected']" :key="f"
          :class="filter === f ? 'bg-primary/10 text-primary border-primary/20' : 'bg-white/5 text-on-surface-variant border-white/5'"
          class="px-3 py-1.5 rounded-lg text-xs font-bold border uppercase tracking-wider" @click="switchFilter(f)">{{ f }}</button>
      </div>
    </div>

    <div v-if="loading" class="text-center py-12 text-on-surface-variant">Loading...</div>
    <div v-else-if="error" class="text-center py-12 text-red-500">{{ error }}</div>
    <div v-else-if="complaints.length === 0" class="text-center py-12 text-on-surface-variant">No complaints found.</div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <div v-for="c in complaints" :key="c.id" class="bg-surface rounded-2xl border border-outline-variant/30 p-6">
        <div class="flex items-start justify-between mb-3">
          <div class="min-w-0">
            <h3 class="font-bold text-sm truncate">{{ c.user?.name || 'Unknown' }}</h3>
            <p class="text-xs text-on-surface-variant">Order #{{ c.order_id || 'N/A' }}</p>
          </div>
          <span :class="statusClass(c.status)"
            class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase shrink-0 ml-2">{{ c.status }}</span>
        </div>
        <p class="text-xs text-on-surface-variant mb-1">{{ c.restaurant?.name || 'Unknown restaurant' }}</p>
        <p class="text-sm font-medium mb-1">{{ c.reason }}</p>
        <p class="text-xs text-on-surface-variant mb-3 line-clamp-2">{{ c.description }}</p>
        <div class="flex items-center justify-between">
          <span class="text-xs text-on-surface-variant">{{ timeAgo(c.created_at) }}</span>
          <button @click="openDetail(c)"
            class="px-3 py-1 rounded-lg bg-primary/10 text-primary text-xs font-bold">View</button>
        </div>
      </div>
    </div>

    <div v-if="meta" class="flex justify-center gap-4 mt-8">
      <button :disabled="!meta.prev_page_url" @click="loadPage(meta.current_page - 1)"
        class="px-4 py-2 rounded-lg bg-surface border border-outline-variant/30 text-sm disabled:opacity-30">Prev</button>
      <span class="px-4 py-2 text-sm text-on-surface-variant">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <button :disabled="!meta.next_page_url" @click="loadPage(meta.current_page + 1)"
        class="px-4 py-2 rounded-lg bg-surface border border-outline-variant/30 text-sm disabled:opacity-30">Next</button>
    </div>

    <div v-if="selectedComplaint" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div class="bg-surface rounded-2xl p-6 w-full max-w-lg mx-4">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-bold text-lg">Complaint Details</h3>
          <button @click="selectedComplaint = null" class="text-on-surface-variant hover:text-white">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>
        <div class="space-y-3 text-sm">
          <div><span class="text-on-surface-variant">User:</span> {{ selectedComplaint.user?.name || 'Unknown' }}</div>
          <div><span class="text-on-surface-variant">Order #:</span> {{ selectedComplaint.order_id || 'N/A' }}</div>
          <div><span class="text-on-surface-variant">Restaurant:</span> {{ selectedComplaint.restaurant?.name || 'N/A' }}</div>
          <div><span class="text-on-surface-variant">Reason:</span> {{ selectedComplaint.reason }}</div>
          <div><span class="text-on-surface-variant">Description:</span>
            <p class="mt-1 text-on-surface-variant bg-surface-container-lowest rounded-xl p-3">{{ selectedComplaint.description }}</p>
          </div>
          <div><span class="text-on-surface-variant">Date:</span> {{ new Date(selectedComplaint.created_at).toLocaleString() }}</div>
          <div>
            <span class="text-on-surface-variant">Status:</span>
            <span :class="statusClass(selectedComplaint.status)"
              class="ml-2 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase">{{ selectedComplaint.status }}</span>
          </div>
        </div>
        <div class="flex justify-end mt-6">
          <button @click="selectedComplaint = null"
            class="px-4 py-2 rounded-lg bg-white/5 text-on-surface-variant text-sm">Close</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getComplaints } from '../api.js'

const complaints = ref([])
const loading = ref(true)
const error = ref(null)
const meta = ref(null)
const filter = ref('all')
const selectedComplaint = ref(null)

const timeAgo = (d) => {
  const diff = Math.floor((Date.now() - new Date(d).getTime()) / 1000)
  if (diff < 60) return `${diff}s ago`
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
  return `${Math.floor(diff / 86400)}d ago`
}

const statusClass = (s) => ({
  pending: 'bg-yellow-500/20 text-yellow-400',
  resolved: 'bg-green-500/20 text-green-400',
  rejected: 'bg-red-500/20 text-red-400'
}[s] || 'bg-white/10 text-on-surface-variant')

const switchFilter = (f) => {
  filter.value = f
  loadPage(1)
}

const loadPage = async (page) => {
  loading.value = true
  error.value = null
  try {
    const data = await getComplaints(filter.value, page)
    complaints.value = data.data || []
    meta.value = { current_page: data.current_page, last_page: data.last_page, next_page_url: data.next_page_url, prev_page_url: data.prev_page_url }
  } catch (e) {
    error.value = e.message || 'Failed to load'
    complaints.value = []
  } finally {
    loading.value = false
  }
}

const openDetail = (c) => {
  selectedComplaint.value = c
}

onMounted(() => loadPage(1))
</script>
