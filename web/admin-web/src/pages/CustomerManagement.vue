<template>
  <div>
    <div class="flex justify-between items-center mb-8">
      <h1 class="font-display text-2xl font-bold">Customer Management</h1>
      <div class="relative">
        <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">search</span>
        <input v-model="searchQuery" placeholder="Search by name, email or phone..."
          class="w-72 pl-10 pr-4 py-2 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-sm placeholder:text-on-surface-variant/50 focus:outline-none focus:border-primary/50" />
      </div>
    </div>

    <div v-if="loading" class="text-center py-12 text-on-surface-variant">Loading...</div>
    <div v-else-if="error" class="text-center py-12 text-red-500">{{ error }}</div>
    <div v-else-if="customers.length === 0" class="text-center py-12 text-on-surface-variant">No customers found.</div>

    <div v-else class="bg-surface rounded-2xl border border-outline-variant/30 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-surface-container-lowest text-on-surface-variant text-xs uppercase tracking-wider">
              <th class="text-left p-4">Name</th>
              <th class="text-left p-4">Email</th>
              <th class="text-left p-4">Phone</th>
              <th class="text-center p-4">Total Orders</th>
              <th class="text-left p-4">Joined</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in customers" :key="c.id" class="border-b border-outline-variant/10 hover:bg-surface-container-lowest/30 transition-colors">
              <td class="p-4 font-medium">{{ c.name }}</td>
              <td class="p-4 text-on-surface-variant">{{ c.email }}</td>
              <td class="p-4 text-on-surface-variant">{{ c.phone || '-' }}</td>
              <td class="p-4 text-center font-semibold">{{ c.total_orders ?? 0 }}</td>
              <td class="p-4 text-on-surface-variant text-xs">{{ timeAgo(c.created_at) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="meta" class="flex justify-center gap-4 mt-8">
      <button :disabled="!meta.prev_page_url" @click="loadPage(meta.current_page - 1)"
        class="px-4 py-2 rounded-lg bg-surface border border-outline-variant/30 text-sm disabled:opacity-30">Prev</button>
      <span class="px-4 py-2 text-sm text-on-surface-variant">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <button :disabled="!meta.next_page_url" @click="loadPage(meta.current_page + 1)"
        class="px-4 py-2 rounded-lg bg-surface border border-outline-variant/30 text-sm disabled:opacity-30">Next</button>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { getCustomers } from '../api.js'

const customers = ref([])
const loading = ref(true)
const error = ref(null)
const meta = ref(null)
const searchQuery = ref('')

const timeAgo = (d) => {
  const diff = Math.floor((Date.now() - new Date(d).getTime()) / 1000)
  if (diff < 60) return `${diff}s ago`
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
  return `${Math.floor(diff / 86400)}d ago`
}

let searchTimeout = null
watch(searchQuery, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => loadPage(1), 400)
})

const loadPage = async (page) => {
  loading.value = true
  error.value = null
  try {
    const data = await getCustomers(searchQuery.value, page)
    customers.value = data.data || []
    meta.value = { current_page: data.current_page, last_page: data.last_page, next_page_url: data.next_page_url, prev_page_url: data.prev_page_url }
  } catch (e) {
    error.value = e.message || 'Failed to load'
    customers.value = []
  } finally {
    loading.value = false
  }
}

onMounted(() => loadPage(1))
</script>
