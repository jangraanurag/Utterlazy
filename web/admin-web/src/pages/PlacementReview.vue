<template>
  <div>
    <h1 class="text-3xl font-serif font-bold text-on-surface mb-2">Placement Review</h1>
    <p class="text-on-surface-variant mb-8">WhatsApp sponsored placements. Approved placements spend only after the org approval engine finalizes them.</p>

    <div class="flex items-center gap-3 mb-6">
      <select v-model="status" @change="load" class="bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2 text-sm">
        <option value="">All statuses</option>
        <option value="pending_review">Pending review</option>
        <option value="active">Active</option>
        <option value="paused">Paused</option>
        <option value="exhausted">Exhausted</option>
        <option value="rejected">Rejected</option>
      </select>
      <button @click="load" class="px-4 py-2 rounded-lg bg-surface-container border border-outline-variant/50 text-sm font-bold">Refresh</button>
      <span v-if="message" class="text-sm text-green-400">{{ message }}</span>
      <span v-if="error" class="text-sm text-red-400">{{ error }}</span>
    </div>

    <div v-if="loading" class="py-12 text-center text-on-surface-variant">Loading...</div>

    <div v-else class="space-y-4">
      <div v-for="p in placements" :key="p.id" class="bg-surface-container-lowest rounded-xl border border-outline-variant/30 p-5">
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="flex items-center gap-3 mb-1">
              <h3 class="text-lg font-bold">{{ p.creative_title }}</h3>
              <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full border"
                :class="statusClass(p.status)">{{ p.status.replace('_', ' ') }}</span>
            </div>
            <p class="text-sm text-on-surface-variant mb-2">{{ p.creative_body }}</p>
            <p class="text-xs text-on-surface-variant">
              {{ p.restaurant?.name || 'Restaurant' }} · Budget ₹{{ Number(p.daily_budget).toFixed(2) }}/day ·
              Bid ₹{{ Number(p.cpc_bid).toFixed(2) }} · Spent today ₹{{ Number(p.spent_today).toFixed(2) }}
            </p>
            <p class="text-xs text-on-surface-variant mt-1">
              {{ formatDate(p.start_at) }} → {{ formatDate(p.end_at) }}
              <span v-if="p.pinned_floor != null"> · Pinned floor ₹{{ Number(p.pinned_floor).toFixed(2) }}</span>
            </p>
            <p v-if="p.rejection_reason" class="text-xs text-red-400 mt-2">Rejected: {{ p.rejection_reason }}</p>
          </div>

          <div class="flex flex-col gap-2 shrink-0" v-if="p.status === 'pending_review'">
            <button v-if="p.approval_instance_id" @click="approve(p)"
              class="px-4 py-2 rounded-lg bg-primary text-white text-sm font-bold hover:opacity-90">
              Approve
            </button>
            <button @click="openReject(p)" class="px-4 py-2 rounded-lg bg-red-500/20 text-red-300 text-sm font-bold">
              Reject
            </button>
          </div>
        </div>

        <div v-if="rejectingId === p.id" class="mt-4 pt-4 border-t border-outline-variant/30">
          <label class="block text-xs uppercase tracking-wider text-on-surface-variant mb-1">Reason (10–300)</label>
          <textarea v-model="reason" rows="2" maxlength="300"
            class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2 text-sm"></textarea>
          <div class="flex gap-2 mt-2">
            <button @click="reject(p)" :disabled="reason.trim().length < 10"
              class="px-4 py-2 rounded-lg bg-red-500 text-white text-sm font-bold disabled:opacity-40">Confirm reject</button>
            <button @click="rejectingId = null" class="px-4 py-2 rounded-lg bg-surface-container text-sm">Cancel</button>
          </div>
        </div>
      </div>

      <div v-if="!placements.length" class="text-center py-12 text-on-surface-variant">No placements found.</div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { getWaPlacements, rejectWaPlacement, approveWorkflow } from '../api'

const placements = ref([])
const status = ref('pending_review')
const loading = ref(true)
const error = ref('')
const message = ref('')
const rejectingId = ref(null)
const reason = ref('')

const statusClass = (s) => ({
  'border-green-500/40 text-green-400': s === 'active',
  'border-amber-500/40 text-amber-400': s === 'pending_review' || s === 'exhausted',
  'border-red-500/40 text-red-400': s === 'rejected',
  'border-outline-variant/40 text-on-surface-variant': s === 'paused',
})

const formatDate = (d) => new Date(d).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' })

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await getWaPlacements(status.value)
    placements.value = res.data || []
  } catch (e) {
    error.value = e.message || 'Failed to load placements'
  } finally {
    loading.value = false
  }
}

function openReject(p) {
  rejectingId.value = p.id
  reason.value = ''
}

async function reject(p) {
  try {
    await rejectWaPlacement(p.id, reason.value.trim())
    rejectingId.value = null
    message.value = 'Placement rejected.'
    await load()
  } catch (e) {
    error.value = e.error || e.message || 'Reject failed'
  }
}

async function approve(p) {
  try {
    await approveWorkflow(p.approval_instance_id)
    message.value = 'Placement approved and activated.'
    await load()
  } catch (e) {
    error.value = e.error || e.message || 'Approve failed'
  }
}

onMounted(load)
</script>
