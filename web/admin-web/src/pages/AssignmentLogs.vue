<template>
  <div>
    <div class="flex justify-between items-center mb-8">
      <h1 class="font-display text-2xl font-bold">Assignment Logs</h1>
      <div class="flex items-center gap-3">
        <input v-model="orderFilter" placeholder="Order ID..." class="px-3 py-2 rounded-lg bg-surface-container-lowest border border-outline-variant/30 text-sm w-32" @input="debounceLoad" />
        <select v-model="statusFilter" class="px-4 py-2 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-sm" @change="loadPage(1)">
          <option value="all">All Status</option>
          <option value="assigned">Assigned</option>
          <option value="accepted">Accepted</option>
          <option value="rejected">Rejected</option>
          <option value="expired">Expired</option>
        </select>
        <button @click="loadPage(1)" class="px-3 py-2 rounded-lg bg-primary/10 text-primary text-xs font-bold">Refresh</button>
      </div>
    </div>

    <div v-if="loading" class="text-center py-12 text-on-surface-variant">Loading...</div>

    <div v-else-if="error" class="text-center py-12 text-red-500">{{ error }}</div>

    <div v-else-if="logs.length === 0" class="text-center py-12 text-on-surface-variant">No logs found.</div>

    <div v-else class="bg-surface rounded-2xl border border-outline-variant/30 overflow-hidden">
      <table class="w-full">
        <thead class="bg-surface-container-lowest">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-bold text-on-surface-variant uppercase">Order</th>
            <th class="px-6 py-3 text-left text-xs font-bold text-on-surface-variant uppercase">Rider</th>
            <th class="px-6 py-3 text-left text-xs font-bold text-on-surface-variant uppercase">Status</th>
            <th class="px-6 py-3 text-left text-xs font-bold text-on-surface-variant uppercase">Score</th>
            <th class="px-6 py-3 text-left text-xs font-bold text-on-surface-variant uppercase">Distance</th>
            <th class="px-6 py-3 text-left text-xs font-bold text-on-surface-variant uppercase">Time</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="log in logs" :key="log.id" class="border-t border-outline-variant/10">
            <td class="px-6 py-4 text-sm font-bold">#{{ log.order_id }}</td>
            <td class="px-6 py-4 text-sm">{{ log.rider?.name || 'N/A' }}</td>
            <td class="px-6 py-4">
              <span :class="statusBadge(log.status)" class="px-2 py-1 rounded text-xs font-bold uppercase">{{ log.status }}</span>
            </td>
            <td class="px-6 py-4 text-sm">{{ log.assignment_score !== null ? log.assignment_score : '-' }}</td>
            <td class="px-6 py-4 text-sm">{{ log.distance_km !== null ? log.distance_km + ' km' : '-' }}</td>
            <td class="px-6 py-4 text-sm text-on-surface-variant">{{ timeAgo(log.created_at) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="meta" class="flex justify-center gap-4 mt-8">
      <button :disabled="!meta.prev_page_url" @click="loadPage(meta.current_page - 1)" class="px-4 py-2 rounded-lg bg-surface border border-outline-variant/30 text-sm disabled:opacity-30">Prev</button>
      <span class="px-4 py-2 text-sm text-on-surface-variant">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <button :disabled="!meta.next_page_url" @click="loadPage(meta.current_page + 1)" class="px-4 py-2 rounded-lg bg-surface border border-outline-variant/30 text-sm disabled:opacity-30">Next</button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getAssignmentLogs } from '../api.js'

const logs = ref([])
const meta = ref(null)
const loading = ref(true)
const error = ref(null)
const statusFilter = ref('all')
const orderFilter = ref('')
let debounceTimer = null

const timeAgo = (d) => {
  if (!d) return ''
  const diff = Math.floor((Date.now() - new Date(d).getTime()) / 1000)
  if (diff < 60) return diff + 's ago'
  if (diff < 3600) return Math.floor(diff / 60) + 'm ago'
  if (diff < 86400) return Math.floor(diff / 3600) + 'h ago'
  return Math.floor(diff / 86400) + 'd ago'
}

const statusBadge = (s) => ({ assigned:'bg-blue-100 text-blue-700', accepted:'bg-green-100 text-green-700', rejected:'bg-red-100 text-red-700', expired:'bg-stone-100 text-stone-600' }[s])

const loadPage = async (page) => {
  loading.value = true
  error.value = null
  try {
    const params = { page }
    if (statusFilter.value !== 'all') params.status = statusFilter.value
    if (orderFilter.value) params.order_id = orderFilter.value
    const data = await getAssignmentLogs(params)
    logs.value = data.data || []
    meta.value = { current_page: data.current_page, last_page: data.last_page, next_page_url: data.next_page_url, prev_page_url: data.prev_page_url }
  } catch (e) {
    error.value = e.message || 'Failed to load'
    logs.value = []
  } finally {
    loading.value = false
  }
}

const debounceLoad = () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => loadPage(1), 400)
}

onMounted(() => loadPage(1))
</script>
