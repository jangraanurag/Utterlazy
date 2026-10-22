<script setup>
import { ref, onMounted } from 'vue'
import { getPendingApprovals, getApprovalHistory, sendApprovalOtp, verifyApprovalOtp, rejectApproval } from '../api'

const pending = ref([])
const history = ref([])
const activeTab = ref('pending')
const loading = ref(true)
const showOtpModal = ref(false)
const selectedItem = ref(null)
const otp = ref('')
const otpError = ref('')
const otpBusy = ref(false)

async function fetchData() {
  loading.value = true
  try {
    const [p, h] = await Promise.all([getPendingApprovals(), getApprovalHistory()])
    pending.value = p.pending || []
    history.value = h.history || []
  } catch (e) { pending.value = []; history.value = [] }
  loading.value = false
}

function startApproval(item) {
  selectedItem.value = item
  otp.value = ''
  otpError.value = ''
  showOtpModal.value = true
}

async function handleSendOtp() {
  otpBusy.value = true; otpError.value = ''
  try {
    await sendApprovalOtp({
      approval_id: selectedItem.value.id,
      type: selectedItem.value.type,
    })
  } catch (e) { otpError.value = 'Failed to send OTP' }
  otpBusy.value = false
}

async function handleVerifyOtp() {
  otpBusy.value = true; otpError.value = ''
  try {
    await verifyApprovalOtp({
      approval_id: selectedItem.value.id,
      type: selectedItem.value.type,
      otp: otp.value,
    })
    showOtpModal.value = false
    await fetchData()
  } catch (e) { otpError.value = e.response?.data?.error || 'Invalid OTP' }
  otpBusy.value = false
}

async function handleReject(item) {
  if (!confirm('Reject this submission?')) return
  try {
    await rejectApproval({ approval_id: item.id, type: item.type })
    await fetchData()
  } catch (e) { alert('Failed to reject') }
}

onMounted(fetchData)
</script>

<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Approvals</h1>
        <p class="font-space" style="color:var(--text-secondary);font-size:0.85rem;">Review and approve Account Manager submissions</p>
      </div>
      <button class="btn btn-secondary" @click="fetchData">REFRESH</button>
    </div>

    <div class="flex gap-1 mb-4" style="border-bottom:1px solid var(--border);">
      <button v-for="t in [{k:'pending',l:'Pending'},{k:'history',l:'History'}]" :key="t.k"
        @click="activeTab=t.k"
        :style="activeTab===t.k ? 'border-bottom:2px solid var(--sovereign-orange);color:var(--sovereign-orange);' : 'color:var(--text-muted)'"
        class="font-space font-bold px-3 py-2" style="font-size:0.85rem;border:none;background:none;cursor:pointer;">
        {{ t.l }} <span v-if="t.k==='pending' && pending.length">({{ pending.length }})</span>
      </button>
    </div>

    <div v-if="loading" class="text-center py-16"><div class="spinner" style="margin:0 auto;"></div></div>

    <!-- Pending Tab -->
    <template v-else-if="activeTab === 'pending'">
      <div v-if="pending.length === 0" class="card text-center py-16">
        <div style="font-size:3rem;opacity:0.2;margin-bottom:16px;">✅</div>
        <p class="font-space" style="color:var(--text-muted)">No pending approvals</p>
      </div>
      <div v-else style="display:grid;gap:12px;">
        <div v-for="item in pending" :key="item.id" class="card">
          <div class="flex items-center justify-between">
            <div>
              <p class="font-space font-bold">{{ item.title }}</p>
              <p class="font-space" style="color:var(--text-secondary);font-size:0.85rem;">
                {{ item.type === 'ad' ? '📢' : '🏷️' }} {{ item.type?.toUpperCase() }} · by {{ item.submitted_by }} · {{ new Date(item.submitted_at).toLocaleDateString('en-IN') }}
              </p>
            </div>
            <div class="flex gap-2">
              <button class="btn btn-sm btn-primary" @click="startApproval(item)">APPROVE</button>
              <button class="btn btn-sm" style="background:var(--error);color:white;" @click="handleReject(item)">REJECT</button>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- History Tab -->
    <template v-else>
      <div v-if="history.length === 0" class="card text-center py-16">
        <p class="font-space" style="color:var(--text-muted)">No approval history</p>
      </div>
      <div v-else style="display:grid;gap:8px;">
        <div v-for="item in history" :key="item.id" class="card">
          <div class="flex items-center justify-between">
            <div>
              <p class="font-space font-bold" style="font-size:0.9rem;">{{ item.title }}</p>
              <p class="font-space" style="color:var(--text-secondary);font-size:0.8rem;">{{ item.type?.toUpperCase() }} · {{ new Date(item.submitted_at).toLocaleDateString('en-IN') }}</p>
            </div>
            <span class="badge" :class="item.approval_status === 'approved' ? 'badge-success' : 'badge-error'">{{ item.approval_status?.toUpperCase() }}</span>
          </div>
        </div>
      </div>
    </template>

    <!-- OTP Modal -->
    <div v-if="showOtpModal" class="modal-overlay" @click.self="showOtpModal=false">
      <div class="modal-card" style="max-width:380px;">
        <h2 class="font-space font-bold mb-2">Approve {{ selectedItem?.type?.toUpperCase() }}</h2>
        <p style="color:var(--text-secondary);font-size:0.85rem;margin-bottom:16px;">{{ selectedItem?.title }}</p>
        <div style="display:grid;gap:12px;">
          <button class="btn btn-secondary w-full" @click="handleSendOtp" :disabled="otpBusy">SEND OTP</button>
          <input v-model="otp" type="text" class="form-input text-center font-mono" placeholder="Enter 6-digit OTP" maxlength="6" />
          <p v-if="otpError" style="color:var(--error);font-size:0.85rem;">{{ otpError }}</p>
          <button class="btn btn-primary w-full" @click="handleVerifyOtp" :disabled="otpBusy || otp.length < 6">
            {{ otpBusy ? 'Verifying...' : 'VERIFY & APPROVE' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
