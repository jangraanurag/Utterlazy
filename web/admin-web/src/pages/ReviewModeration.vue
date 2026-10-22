<template>
  <div>
    <div class="flex justify-between items-center mb-8">
      <h1 class="font-display text-2xl font-bold">Review Moderation</h1>
      <div class="w-64">
        <input v-model="restaurantFilter" placeholder="Filter by restaurant name..."
          class="w-full px-4 py-2 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-sm placeholder:text-on-surface-variant/50 focus:outline-none focus:border-primary/50" />
      </div>
    </div>

    <div v-if="loading" class="text-center py-12 text-on-surface-variant">Loading...</div>
    <div v-else-if="error" class="text-center py-12 text-red-500">{{ error }}</div>
    <div v-else-if="reviews.length === 0" class="text-center py-12 text-on-surface-variant">No reviews found.</div>

    <div v-else class="bg-surface rounded-2xl border border-outline-variant/30 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-surface-container-lowest text-on-surface-variant text-xs uppercase tracking-wider">
              <th class="text-left p-4">Restaurant</th>
              <th class="text-left p-4">User</th>
              <th class="text-center p-4">Rating</th>
              <th class="text-left p-4">Comment</th>
              <th class="text-left p-4">Date</th>
              <th class="text-center p-4">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="rev in reviews" :key="rev.id" class="border-b border-outline-variant/10 hover:bg-surface-container-lowest/30 transition-colors">
              <td class="p-4 font-medium">{{ rev.restaurant?.name || 'N/A' }}</td>
              <td class="p-4">{{ rev.user?.name || 'N/A' }}</td>
              <td class="p-4 text-center">
                <span class="text-amber-400">{{ '★'.repeat(rev.rating) }}{{ '☆'.repeat(5 - rev.rating) }}</span>
                <span class="text-on-surface-variant text-xs ml-1">({{ rev.rating }})</span>
              </td>
              <td class="p-4 max-w-xs">
                <p class="truncate text-on-surface-variant" :title="rev.comment">{{ rev.comment || '-' }}</p>
              </td>
              <td class="p-4 text-on-surface-variant text-xs">{{ timeAgo(rev.created_at) }}</td>
              <td class="p-4 text-center">
                <span :class="rev.status === 'approved' ? 'bg-green-500/20 text-green-400' : rev.status === 'rejected' ? 'bg-red-500/20 text-red-400' : 'bg-yellow-500/20 text-yellow-400'"
                  class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase">{{ rev.status }}</span>
              </td>
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
import { getReviews } from '../api.js'

const reviews = ref([])
const loading = ref(true)
const error = ref(null)
const meta = ref(null)
const restaurantFilter = ref('')

const timeAgo = (d) => {
  const diff = Math.floor((Date.now() - new Date(d).getTime()) / 1000)
  if (diff < 60) return `${diff}s ago`
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
  return `${Math.floor(diff / 86400)}d ago`
}

let filterTimeout = null
watch(restaurantFilter, () => {
  clearTimeout(filterTimeout)
  filterTimeout = setTimeout(() => loadPage(1), 400)
})

const loadPage = async (page) => {
  loading.value = true
  error.value = null
  try {
    const data = await getReviews(restaurantFilter.value, page)
    reviews.value = data.data || []
    meta.value = { current_page: data.current_page, last_page: data.last_page, next_page_url: data.next_page_url, prev_page_url: data.prev_page_url }
  } catch (e) {
    error.value = e.message || 'Failed to load'
    reviews.value = []
  } finally {
    loading.value = false
  }
}

onMounted(() => loadPage(1))
</script>
