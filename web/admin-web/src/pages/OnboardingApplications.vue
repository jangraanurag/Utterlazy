<template>
  <div>
    <div class="flex justify-between items-center mb-8">
      <h1 class="font-display text-2xl font-bold">Restaurant Onboarding</h1>
      <div class="flex gap-2">
        <button v-for="f in ['all','pending','approved','rejected']" :key="f"
          :class="filter === f ? 'bg-primary/10 text-primary border-primary/20' : 'bg-white/5 text-on-surface-variant border-white/5'"
          class="px-3 py-1.5 rounded-lg text-xs font-bold border uppercase tracking-wider" @click="switchFilter(f)">{{ f }}</button>
      </div>
    </div>

    <div v-if="loading" class="text-center py-12 text-on-surface-variant">Loading...</div>

    <div v-else-if="error" class="text-center py-12 text-red-500">{{ error }}</div>

    <div v-else-if="filteredApps.length === 0" class="text-center py-12 text-on-surface-variant">No applications found.</div>

    <div v-else class="space-y-4">
      <div v-for="app in filteredApps" :key="app.id" class="bg-surface rounded-2xl border border-outline-variant/30 p-6">
        <div class="flex items-start justify-between mb-4">
          <div>
            <h3 class="font-bold text-lg">{{ app.restaurant_name }}</h3>
            <p class="text-sm text-on-surface-variant">{{ app.owner_name }} &bull; {{ app.email }}</p>
          </div>
          <span :class="statusBadge(app.status)" class="px-3 py-1 rounded-full text-xs font-bold uppercase">{{ app.status }}</span>
        </div>
        <div class="grid grid-cols-3 gap-4 text-sm mb-4">
          <div><span class="text-on-surface-variant">City:</span> {{ app.city }}</div>
          <div><span class="text-on-surface-variant">Phone:</span> {{ app.phone }}</div>
          <div><span class="text-on-surface-variant">FSSAI:</span> {{ app.fssai_number || 'N/A' }}</div>
          <div><span class="text-on-surface-variant">GST:</span> {{ app.gst_number || 'N/A' }}</div>
          <div><span class="text-on-surface-variant">PAN:</span> {{ app.pan_number || 'N/A' }}</div>
          <div><span class="text-on-surface-variant">Cuisine:</span> {{ app.cuisine_type || 'N/A' }}</div>
          <div class="col-span-2"><span class="text-on-surface-variant">Address:</span> {{ app.address }}, {{ app.state }} - {{ app.pincode }}</div>
          <div v-if="app.rejection_reason"><span class="text-on-surface-variant">Reason:</span> <span class="text-red-500">{{ app.rejection_reason }}</span></div>
        </div>
        <div v-if="app.status === 'pending'" class="flex gap-3">
          <button @click="approveApp(app.id)" :disabled="approving" class="px-4 py-2 rounded-lg bg-green-500 text-white text-xs font-bold disabled:opacity-50">Approve</button>
          <button @click="openReject(app.id)" class="px-4 py-2 rounded-lg bg-red-50 text-red-600 text-xs font-bold">Reject</button>
        </div>
      </div>
    </div>

    <div v-if="meta" class="flex justify-center gap-4 mt-8">
      <button :disabled="!meta.prev_page_url" @click="loadPage(meta.current_page - 1)" class="px-4 py-2 rounded-lg bg-surface border border-outline-variant/30 text-sm disabled:opacity-30">Prev</button>
      <span class="px-4 py-2 text-sm text-on-surface-variant">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <button :disabled="!meta.next_page_url" @click="loadPage(meta.current_page + 1)" class="px-4 py-2 rounded-lg bg-surface border border-outline-variant/30 text-sm disabled:opacity-30">Next</button>
    </div>

    <div v-if="showRejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
      <div class="bg-surface rounded-2xl p-6 w-full max-w-md mx-4">
        <h3 class="font-bold text-lg mb-4">Reject Application</h3>
        <textarea v-model="rejectReason" placeholder="Reason for rejection..." class="w-full px-4 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-sm mb-4" rows="3"></textarea>
        <div class="flex gap-3 justify-end">
          <button @click="showRejectModal = false" class="px-4 py-2 rounded-lg bg-white/5 text-on-surface-variant text-sm">Cancel</button>
          <button @click="rejectApp" :disabled="!rejectReason || rejecting" class="px-4 py-2 rounded-lg bg-red-500 text-white text-xs font-bold disabled:opacity-50">Reject</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { getOnboardingApps, approveOnboardingApp, rejectOnboardingApp } from '../api.js'

const filter = ref('all')
const apps = ref([])
const meta = ref(null)
const loading = ref(true)
const error = ref(null)
const approving = ref(false)
const rejecting = ref(false)
const showRejectModal = ref(false)
const rejectReason = ref('')
const rejectId = ref(null)

const filteredApps = computed(() => {
  return apps.value
})

const statusBadge = (s) => ({ pending:'bg-yellow-100 text-yellow-700', approved:'bg-green-100 text-green-700', rejected:'bg-red-100 text-red-700' }[s])

const switchFilter = (f) => {
  filter.value = f
  loadPage(1)
}

const loadPage = async (page) => {
  loading.value = true
  error.value = null
  try {
    const data = await getOnboardingApps(filter.value, page)
    apps.value = data.data || []
    meta.value = { current_page: data.current_page, last_page: data.last_page, next_page_url: data.next_page_url, prev_page_url: data.prev_page_url }
  } catch (e) {
    error.value = e.message || 'Failed to load'
    apps.value = []
  } finally {
    loading.value = false
  }
}

const approveApp = async (id) => {
  approving.value = true
  try {
    await approveOnboardingApp(id)
    await loadPage(meta.value?.current_page || 1)
  } catch (e) {
    error.value = e.message || 'Failed to approve'
  } finally {
    approving.value = false
  }
}

const openReject = (id) => {
  rejectId.value = id
  rejectReason.value = ''
  showRejectModal.value = true
}

const rejectApp = async () => {
  rejecting.value = true
  try {
    await rejectOnboardingApp(rejectId.value, rejectReason.value)
    showRejectModal.value = false
    await loadPage(meta.value?.current_page || 1)
  } catch (e) {
    error.value = e.message || 'Failed to reject'
  } finally {
    rejecting.value = false
  }
}

onMounted(() => loadPage(1))
</script>
