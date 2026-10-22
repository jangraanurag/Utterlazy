<template>
  <div>
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-black text-white flex items-center gap-3">
          <span class="w-10 h-10 bg-gradient-to-br from-cyan-400 to-blue-500 rounded-xl flex items-center justify-center text-lg">🪪</span>
          Rider KYC Review
        </h1>
        <p class="text-white/50 text-sm mt-1">Verify rider documents & compliance</p>
      </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-4 gap-4 mb-6">
      <div class="bg-white/5 border border-white/10 rounded-xl p-4">
        <p class="text-white/40 text-xs uppercase tracking-wider mb-1">Total Riders</p>
        <p class="text-2xl font-black text-white">{{ stats.total }}</p>
      </div>
      <div class="bg-white/5 border border-white/10 rounded-xl p-4">
        <p class="text-white/40 text-xs uppercase tracking-wider mb-1">Verified</p>
        <p class="text-2xl font-black text-green-400">{{ stats.verified }}</p>
      </div>
      <div class="bg-amber-500/10 border border-amber-500/20 rounded-xl p-4">
        <p class="text-amber-300/60 text-xs uppercase tracking-wider mb-1">Pending</p>
        <p class="text-2xl font-black text-amber-400">{{ stats.pending }}</p>
      </div>
      <div class="bg-red-500/10 border border-red-500/20 rounded-xl p-4">
        <p class="text-red-300/60 text-xs uppercase tracking-wider mb-1">Rejected</p>
        <p class="text-2xl font-black text-red-400">{{ stats.rejected }}</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex gap-2 mb-4">
      <button v-for="f in ['all','pending','verified','rejected','unverified']" :key="f"
        @click="filter = f === 'all' ? '' : f; fetchRiders()"
        :class="(!filter && f === 'all') || filter === f ? 'bg-white text-black' : 'bg-white/10 text-white/60 hover:bg-white/20'"
        class="px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider transition-colors">
        {{ f === 'unverified' ? 'Not Submitted' : f }}
      </button>
    </div>

    <!-- Rider List -->
    <div class="bg-white/5 border border-white/10 rounded-2xl overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-white/10 text-white/40 text-xs uppercase tracking-wider">
              <th class="text-left p-4">Rider</th>
              <th class="text-center p-4">Vehicle</th>
              <th class="text-center p-4">KYC Status</th>
              <th class="text-center p-4">Docs Uploaded</th>
              <th class="text-center p-4">Deliveries</th>
              <th class="text-right p-4">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="rider in riders" :key="rider.id"
              class="border-b border-white/5 hover:bg-white/5 transition-colors">
              <td class="p-4">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-full bg-gradient-to-br from-cyan-400/20 to-blue-500/20 flex items-center justify-center text-cyan-400 font-bold text-sm">
                    {{ (rider.name || '?').charAt(0).toUpperCase() }}
                  </div>
                  <div>
                    <p class="font-bold text-white">{{ rider.name }}</p>
                    <p class="text-white/40 text-xs">{{ rider.phone }}</p>
                  </div>
                </div>
              </td>
              <td class="p-4 text-center">
                <span class="px-2 py-1 rounded-md text-xs font-bold uppercase"
                  :class="rider.vehicle_type === 'bike' ? 'bg-blue-500/20 text-blue-400' : rider.vehicle_type === 'scooter' ? 'bg-purple-500/20 text-purple-400' : rider.vehicle_type === 'bicycle' ? 'bg-green-500/20 text-green-400' : 'bg-amber-500/20 text-amber-400'">
                  {{ rider.vehicle_type }}
                </span>
              </td>
              <td class="p-4 text-center">
                <span :class="kycBadgeClass(rider)" class="px-2 py-1 rounded-full text-xs font-bold">
                  {{ kycLabel(rider) }}
                </span>
              </td>
              <td class="p-4 text-center font-bold text-white">{{ rider.documents_count || 0 }}/8</td>
              <td class="p-4 text-center font-bold text-white">{{ rider.total_deliveries || 0 }}</td>
              <td class="p-4 text-right">
                <button @click="openDocs(rider)"
                  class="bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-bold px-4 py-1.5 rounded-lg text-xs hover:opacity-90 transition-opacity shadow-lg shadow-cyan-500/20">
                  Review Docs
                </button>
              </td>
            </tr>
            <tr v-if="riders.length === 0">
              <td colspan="6" class="p-12 text-center text-white/30">No riders found.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="lastPage > 1" class="flex justify-center gap-2 mt-4">
      <button v-for="p in lastPage" :key="p" @click="page = p; fetchRiders()"
        :class="p === page ? 'bg-white text-black' : 'bg-white/10 text-white/60 hover:bg-white/20'"
        class="w-8 h-8 rounded-lg text-xs font-bold transition-colors">{{ p }}</button>
    </div>

    <!-- Document Review Modal -->
    <div v-if="selectedRider" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
      @click.self="selectedRider = null">
      <div class="bg-[#1a1a2e] border border-white/10 rounded-2xl p-8 w-full max-w-3xl max-h-[90vh] overflow-y-auto shadow-2xl">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-xl font-black text-white flex items-center gap-2">
            <span class="material-symbols-outlined text-cyan-400">verified</span>
            {{ selectedRider.name }} — KYC Documents
          </h2>
          <button @click="selectedRider = null" class="text-white/40 hover:text-white text-2xl">&times;</button>
        </div>

        <!-- Rider summary -->
        <div class="grid grid-cols-3 gap-3 mb-6">
          <div class="bg-white/5 rounded-lg p-3">
            <p class="text-white/40 text-xs uppercase">Verification</p>
            <p class="font-bold text-white">{{ kycLabel(selectedRider) }}</p>
          </div>
          <div class="bg-white/5 rounded-lg p-3">
            <p class="text-white/40 text-xs uppercase">Docs Uploaded</p>
            <p class="font-bold text-white">{{ uploadedDocs }}/8</p>
          </div>
          <div class="bg-white/5 rounded-lg p-3">
            <p class="text-white/40 text-xs uppercase">Approved</p>
            <p class="font-bold text-green-400">{{ approvedCount }}/5 required</p>
          </div>
        </div>

        <!-- Document list -->
        <div class="space-y-2">
          <div v-for="(doc, type) in riderDocs" :key="type"
            class="bg-white/5 border border-white/10 rounded-xl p-4">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-cyan-400">{{ docIcon(type) }}</span>
                <div>
                  <p class="font-bold text-white text-sm">{{ docLabel(type) }}</p>
                  <p v-if="doc.status === 'rejected' && doc.rejection_reason"
                    class="text-red-400 text-xs mt-1">{{ doc.rejection_reason }}</p>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <span :class="docBadgeClass(doc.status)" class="px-2 py-1 rounded-md text-xs font-bold uppercase">
                  {{ doc.status }}
                </span>
                <template v-if="doc.status === 'rejected'">
                  <button @click="approveDoc(type)" title="Override: Approve"
                    class="bg-green-500/20 text-green-400 px-2 py-1 rounded-lg text-xs font-bold hover:bg-green-500/30 transition-colors">
                    Approve
                  </button>
                </template>
                <template v-if="doc.status === 'pending'">
                  <button @click="approveDoc(type)"
                    class="bg-green-500/20 text-green-400 px-2 py-1 rounded-lg text-xs font-bold hover:bg-green-500/30 transition-colors">
                    ✓
                  </button>
                  <button @click="promptReject(type)"
                    class="bg-red-500/20 text-red-400 px-2 py-1 rounded-lg text-xs font-bold hover:bg-red-500/30 transition-colors">
                    ✗
                  </button>
                </template>
              </div>
            </div>
          </div>
        </div>

        <!-- Reject reason input -->
        <div v-if="rejectingType" class="mt-4 bg-red-500/10 border border-red-500/20 rounded-xl p-4">
          <label class="text-white/60 text-xs uppercase tracking-wider block mb-2">Rejection Reason</label>
          <div class="flex gap-2">
            <input v-model="rejectReason" class="flex-1 bg-white/5 border border-white/10 rounded-lg px-4 py-2 text-white text-sm focus:outline-none focus:border-red-500"
              placeholder="Why is this document rejected?" />
            <button @click="confirmReject" :disabled="!rejectReason.trim()"
              class="bg-red-500 text-white font-bold px-4 py-2 rounded-lg text-xs hover:opacity-90 disabled:opacity-50 transition-opacity">
              Confirm Reject
            </button>
            <button @click="rejectingType = null; rejectReason = ''"
              class="bg-white/10 text-white/60 px-3 py-2 rounded-lg text-xs hover:bg-white/20 transition-colors">Cancel</button>
          </div>
        </div>

        <!-- Final verify button -->
        <div class="mt-6 flex gap-3">
          <button @click="finalVerify"
            :disabled="approvedCount < 5 || verifLoading"
            class="flex-1 bg-gradient-to-r from-emerald-500 to-green-600 text-white font-bold py-3 rounded-xl hover:opacity-90 transition-opacity shadow-lg shadow-emerald-500/20 disabled:opacity-50">
            {{ verifLoading ? 'Verifying...' : `✓ Finalize Verification (${approvedCount}/5 required)` }}
          </button>
          <button v-if="verifError" class="text-red-400 text-xs mt-2">{{ verifError }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { getRiders, getRiderDocuments, approveRiderDocument, rejectRiderDocument, verifyRiderDocumentsFinal } from '../api'

const riders = ref([])
const page = ref(1)
const lastPage = ref(1)
const filter = ref('')
const stats = reactive({ total: 0, verified: 0, pending: 0, rejected: 0 })
const selectedRider = ref(null)
const riderDocs = ref({})
const rejectingType = ref(null)
const rejectReason = ref('')
const verifLoading = ref(false)
const verifError = ref('')

const docLabels = {
  aadhaar_front: 'Aadhaar (Front)', aadhaar_back: 'Aadhaar (Back)',
  driving_license_front: 'Driving License (Front)', driving_license_back: 'Driving License (Back)',
  pan_card: 'PAN Card', selfie: 'Selfie', vehicle_rc: 'Vehicle RC', insurance: 'Insurance',
}

const docIcons = {
  aadhaar_front: 'badge', aadhaar_back: 'badge',
  driving_license_front: 'drive_eta', driving_license_back: 'drive_eta',
  pan_card: 'credit_card', selfie: 'face', vehicle_rc: 'description', insurance: 'shield',
}

const docLabel = (t) => docLabels[t] || t
const docIcon = (t) => docIcons[t] || 'description'

const uploadedDocs = computed(() => Object.values(riderDocs.value).filter(d => d.status !== 'not_uploaded').length)
const approvedCount = computed(() => {
  const required = ['aadhaar_front', 'aadhaar_back', 'driving_license_front', 'pan_card', 'selfie']
  return required.filter(t => riderDocs.value[t]?.status === 'approved').length
})

const kycBadgeClass = (rider) => {
  const s = rider.verification_status
  if (s === 'verified') return 'bg-green-500/20 text-green-400'
  if (s === 'pending') return 'bg-amber-500/20 text-amber-400'
  if (s === 'rejected') return 'bg-red-500/20 text-red-400'
  return 'bg-white/10 text-white/40'
}

const kycLabel = (rider) => {
  const s = rider.verification_status
  if (s === 'verified') return '✅ Verified'
  if (s === 'pending') return '⏳ Pending'
  if (s === 'rejected') return '❌ Rejected'
  return 'Not Submitted'
}

const docBadgeClass = (status) => {
  if (status === 'approved') return 'bg-green-500/20 text-green-400'
  if (status === 'pending') return 'bg-amber-500/20 text-amber-400'
  if (status === 'rejected') return 'bg-red-500/20 text-red-400'
  return 'bg-white/10 text-white/40'
}

const fetchRiders = async () => {
  try {
    const data = await getRiders(filter.value, page.value)
    riders.value = data.data || []
    lastPage.value = data.last_page || 1
    stats.total = data.total || 0
    stats.verified = riders.value.filter(r => r.verification_status === 'verified').length
    stats.pending = riders.value.filter(r => r.verification_status === 'pending').length
    stats.rejected = riders.value.filter(r => r.verification_status === 'rejected').length
  } catch (e) { console.error(e) }
}

const openDocs = async (rider) => {
  selectedRider.value = rider
  rejectingType.value = null
  rejectReason.value = ''
  verifError.value = ''
  riderDocs.value = {}
  try {
    const data = await getRiderDocuments(rider.id)
    riderDocs.value = data.documents || {}
  } catch (e) { console.error(e) }
}

const approveDoc = async (type) => {
  const doc = riderDocs.value[type]
  if (!doc?.id) return
  try {
    await approveRiderDocument(selectedRider.value.id, doc.id)
    doc.status = 'approved'
  } catch (e) { console.error(e) }
}

const promptReject = (type) => {
  rejectingType.value = type
  rejectReason.value = ''
}

const confirmReject = async () => {
  const type = rejectingType.value
  const doc = riderDocs.value[type]
  if (!doc?.id || !rejectReason.value.trim()) return
  try {
    await rejectRiderDocument(selectedRider.value.id, doc.id, rejectReason.value.trim())
    doc.status = 'rejected'
    doc.rejection_reason = rejectReason.value.trim()
    rejectingType.value = null
    rejectReason.value = ''
  } catch (e) { console.error(e) }
}

const finalVerify = async () => {
  verifLoading.value = true
  verifError.value = ''
  try {
    await verifyRiderDocumentsFinal(selectedRider.value.id)
    selectedRider.value.verification_status = 'verified'
    selectedRider.value.is_verified = true
    await fetchRiders()
  } catch (e) {
    verifError.value = e.message || 'Verification failed'
  } finally { verifLoading.value = false }
}

onMounted(fetchRiders)
</script>
