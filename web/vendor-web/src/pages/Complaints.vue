<script setup>
import { ref, onMounted } from 'vue'
import { getComplaints, respondComplaint, closeComplaint } from '../api'

const complaints = ref([])
const loading = ref(true)
const showRespond = ref(false)
const selected = ref(null)
const response = ref('')
const refundAmount = ref('')
const saving = ref(false)

async function fetchComplaints() {
  loading.value = true
  try {
    const d = await getComplaints()
    complaints.value = d.complaints || d.data || []
  } catch (e) { complaints.value = [] }
  loading.value = false
}

function openRespond(c) {
  selected.value = c
  response.value = ''
  refundAmount.value = ''
  showRespond.value = true
}

async function handleRespond() {
  if (!response.value.trim()) return
  saving.value = true
  try {
    await respondComplaint(selected.value.id, {
      vendor_response: response.value,
      refund_amount: refundAmount.value || null,
    })
    showRespond.value = false
    await fetchComplaints()
  } catch (e) { alert('Failed to respond') }
  saving.value = false
}

async function handleClose(id) {
  try { await closeComplaint(id); await fetchComplaints() }
  catch (e) { alert('Failed to close') }
}

function statusBadge(s) {
  const m = { pending: 'badge-warning', resolved: 'badge-success', closed: 'badge-info' }
  return m[s] || 'badge-info'
}

onMounted(fetchComplaints)
</script>

<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Complaints</h1>
        <p class="font-space" style="color:var(--text-secondary);font-size:0.85rem;">Customer complaints and resolutions</p>
      </div>
      <button class="btn btn-secondary" @click="fetchComplaints">REFRESH</button>
    </div>

    <div v-if="loading" class="text-center py-16"><div class="spinner" style="margin:0 auto;"></div></div>
    <div v-else-if="complaints.length === 0" class="card text-center py-16">
      <div style="font-size:3rem;opacity:0.2;margin-bottom:16px;">🎉</div>
      <p class="font-space" style="color:var(--text-muted)">No complaints — great service!</p>
    </div>
    <div v-else style="display:grid;gap:12px;">
      <div v-for="c in complaints" :key="c.id" class="card">
        <div class="flex items-start justify-between">
          <div style="flex:1;">
            <div class="flex gap-2 items-center mb-1">
              <span class="badge" :class="statusBadge(c.status)">{{ c.status?.toUpperCase() }}</span>
              <span style="color:var(--text-muted);font-size:0.8rem;">#{{ c.order_id?.slice(0,8) }}</span>
            </div>
            <p class="font-space font-bold">{{ c.reason }}</p>
            <p v-if="c.description" style="color:var(--text-secondary);font-size:0.85rem;margin-top:4px;">{{ c.description }}</p>
            <p style="color:var(--text-muted);font-size:0.8rem;margin-top:4px;">{{ new Date(c.created_at).toLocaleDateString('en-IN', {day:'2-digit',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}) }}</p>
          </div>
          <div v-if="c.status === 'pending'" class="flex gap-2" style="min-width:140px;">
            <button class="btn btn-sm btn-primary" @click="openRespond(c)">RESPOND</button>
            <button class="btn btn-sm" style="background:var(--error);color:white;" @click="handleClose(c.id)">CLOSE</button>
          </div>
          <div v-else-if="c.vendor_response" class="text-right" style="max-width:200px;">
            <p style="color:var(--text-secondary);font-size:0.8rem;">Your response:</p>
            <p style="font-size:0.85rem;">{{ c.vendor_response }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Respond Modal -->
    <div v-if="showRespond" class="modal-overlay" @click.self="showRespond=false">
      <div class="modal-card">
        <h2 class="font-space font-bold mb-2">Respond to Complaint</h2>
        <p style="color:var(--text-secondary);font-size:0.85rem;margin-bottom:16px;">{{ selected?.reason }}</p>
        <div style="display:grid;gap:12px;">
          <div>
            <label class="form-label">Your Response</label>
            <textarea v-model="response" class="form-input" rows="3" placeholder="Explain what happened..."></textarea>
          </div>
          <div>
            <label class="form-label">Refund Amount (₹) — optional</label>
            <input type="number" v-model="refundAmount" class="form-input" min="0" placeholder="0" />
          </div>
          <button class="btn btn-primary w-full" @click="handleRespond" :disabled="saving || !response.trim()">
            {{ saving ? 'Sending...' : 'SUBMIT RESPONSE' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
