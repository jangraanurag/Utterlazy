<template>
  <div>
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-black text-white flex items-center gap-3">
          <span class="w-10 h-10 bg-gradient-to-br from-emerald-400 to-green-500 rounded-xl flex items-center justify-center text-lg">🗓️</span>
          Daily Rider Payout Cycles
        </h1>
        <p class="text-white/50 text-sm mt-1">Daily payout cycles — maker-checker UTR workflow</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex gap-3 mb-4 flex-wrap">
      <select v-model="statusFilter" @change="fetchCycles"
        class="bg-white/5 border border-white/10 rounded-lg px-3 py-1.5 text-white text-xs focus:outline-none focus:border-emerald-500">
        <option value="">All Statuses</option>
        <option value="generated">Generated</option>
        <option value="pending_payment">Pending Payment</option>
        <option value="paid">Paid</option>
      </select>
      <input v-model="dateFrom" @change="fetchCycles" type="date"
        class="bg-white/5 border border-white/10 rounded-lg px-3 py-1.5 text-white text-xs focus:outline-none focus:border-emerald-500" />
      <input v-model="dateTo" @change="fetchCycles" type="date"
        class="bg-white/5 border border-white/10 rounded-lg px-3 py-1.5 text-white text-xs focus:outline-none focus:border-emerald-500" />
      <input v-model="riderSearch" placeholder="Search rider ID..."
        class="bg-white/5 border border-white/10 rounded-lg px-3 py-1.5 text-white text-xs focus:outline-none focus:border-emerald-500 w-40"
        @change="fetchCycles" />
    </div>

    <!-- Summary -->
    <div class="grid grid-cols-3 gap-4 mb-6">
      <div class="bg-white/5 border border-white/10 rounded-xl p-4">
        <p class="text-white/40 text-xs uppercase tracking-wider mb-1">Total Payable</p>
        <p class="text-2xl font-black text-white">₹{{ summary.total }}</p>
      </div>
      <div class="bg-amber-500/10 border border-amber-500/20 rounded-xl p-4">
        <p class="text-amber-300/60 text-xs uppercase tracking-wider mb-1">Unpaid</p>
        <p class="text-2xl font-black text-amber-400">₹{{ summary.unpaid }}</p>
      </div>
      <div class="bg-green-500/10 border border-green-500/20 rounded-xl p-4">
        <p class="text-green-300/60 text-xs uppercase tracking-wider mb-1">Paid</p>
        <p class="text-2xl font-black text-green-400">₹{{ summary.paid }}</p>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="text-center py-12 text-white/40">Loading...</div>

    <!-- Error -->
    <div v-else-if="error" class="bg-red-500/10 border border-red-500/20 rounded-xl p-4 text-red-400 text-sm">{{ error }}</div>

    <!-- Cycles Table -->
    <div v-else class="bg-white/5 border border-white/10 rounded-2xl overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-white/10 text-white/40 text-xs uppercase tracking-wider">
              <th class="text-left p-4">Rider</th>
              <th class="text-center p-4">Date</th>
              <th class="text-right p-4">Orders</th>
              <th class="text-right p-4">Distance</th>
              <th class="text-right p-4">Net Payable</th>
              <th class="text-center p-4">Status</th>
              <th class="text-center p-4">UTR</th>
              <th class="text-right p-4">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="cycle in cycles" :key="cycle.id"
              class="border-b border-white/5 hover:bg-white/5 transition-colors">
              <td class="p-4">
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 rounded-full bg-gradient-to-br from-emerald-400/20 to-green-500/20 flex items-center justify-center text-emerald-400 font-bold text-xs">
                    {{ (cycle.rider?.name || '?').charAt(0).toUpperCase() }}
                  </div>
                  <div>
                    <p class="font-bold text-white text-sm">{{ cycle.rider?.name || 'Unknown' }}</p>
                    <p class="text-white/30 text-xs">{{ cycle.rider?.phone || '' }}</p>
                  </div>
                </div>
              </td>
              <td class="p-4 text-center font-bold text-white">{{ cycle.cycle_date }}</td>
              <td class="p-4 text-right font-bold text-white">{{ cycle.total_orders }}</td>
              <td class="p-4 text-right text-white">{{ cycle.total_distance_km ? cycle.total_distance_km.toFixed(1) : '-' }} km</td>
              <td class="p-4 text-right font-bold text-green-400 text-base">₹{{ cycle.net_payable?.toFixed(0) || '0' }}</td>
              <td class="p-4 text-center">
                <span :class="statusBadge(cycle.status)" class="px-2 py-1 rounded-full text-xs font-bold uppercase">
                  {{ cycle.status?.replace('_', ' ') }}
                </span>
              </td>
              <td class="p-4 text-center text-xs text-white/50">{{ cycle.utr_number || '-' }}</td>
              <td class="p-4 text-right">
                <button v-if="cycle.status !== 'paid'" @click="openPayModal(cycle)"
                  class="bg-gradient-to-r from-emerald-500 to-green-600 text-white font-bold px-3 py-1.5 rounded-lg text-xs hover:opacity-90 transition-opacity shadow-lg shadow-emerald-500/20">
                  Pay
                </button>
                <button v-else @click="showDetail(cycle)"
                  class="bg-white/10 text-white/60 px-3 py-1.5 rounded-lg text-xs hover:bg-white/20 transition-colors">
                  Detail
                </button>
              </td>
            </tr>
            <tr v-if="cycles.length === 0">
              <td colspan="8" class="p-12 text-center text-white/30">No payout cycles found.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="lastPage > 1" class="flex justify-center gap-2 mt-4">
      <button v-for="p in lastPage" :key="p" @click="page = p; fetchCycles()"
        :class="p === page ? 'bg-white text-black' : 'bg-white/10 text-white/60 hover:bg-white/20'"
        class="w-8 h-8 rounded-lg text-xs font-bold transition-colors">{{ p }}</button>
    </div>

    <!-- Pay Modal -->
    <div v-if="payingCycle" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
      @click.self="payingCycle = null">
      <div class="bg-[#1a1a2e] border border-white/10 rounded-2xl p-8 w-full max-w-md shadow-2xl">
        <h2 class="text-xl font-black text-white mb-2 flex items-center gap-2">
          <span class="material-symbols-outlined text-emerald-400">payments</span>
          Mark Cycle Paid
        </h2>
        <p class="text-white/50 text-sm mb-6">
          {{ payingCycle.rider?.name }} — {{ payingCycle.cycle_date }} — ₹{{ payingCycle.net_payable?.toFixed(0) }}
        </p>
        <div class="space-y-4">
          <div>
            <label class="text-white/50 text-xs uppercase tracking-wider block mb-1">UTR Number *</label>
            <input v-model="utrNumber" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-emerald-500"
              placeholder="e.g. HDFC123456789" />
          </div>
          <div>
            <label class="text-white/50 text-xs uppercase tracking-wider block mb-1">Payment Mode</label>
            <select v-model="paymentMode" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-emerald-500">
              <option value="bank_transfer">Bank Transfer</option>
              <option value="upi">UPI</option>
              <option value="cash">Cash</option>
            </select>
          </div>
          <div>
            <label class="text-white/50 text-xs uppercase tracking-wider block mb-1">Notes (optional)</label>
            <input v-model="paymentNotes" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-emerald-500"
              placeholder="Any additional notes" />
          </div>
        </div>
        <p v-if="payError" class="text-red-400 text-xs mt-3">{{ payError }}</p>
        <div class="flex gap-3 mt-6">
          <button @click="payingCycle = null" class="flex-1 bg-white/10 text-white font-bold py-2.5 rounded-xl hover:bg-white/20 transition-colors">Cancel</button>
          <button @click="confirmPay" :disabled="!utrNumber.trim() || paying"
            class="flex-1 bg-gradient-to-r from-emerald-500 to-green-600 text-white font-bold py-2.5 rounded-xl hover:opacity-90 transition-opacity disabled:opacity-50">
            {{ paying ? 'Processing...' : 'Confirm Payment' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { getRiderPayoutCycles, payRiderPayoutCycle } from '../api'

const cycles = ref([])
const page = ref(1)
const lastPage = ref(1)
const loading = ref(true)
const error = ref(null)
const statusFilter = ref('')
const dateFrom = ref('')
const dateTo = ref('')
const riderSearch = ref('')
const summary = reactive({ total: 0, unpaid: 0, paid: 0 })
const payingCycle = ref(null)
const utrNumber = ref('')
const paymentMode = ref('bank_transfer')
const paymentNotes = ref('')
const paying = ref(false)
const payError = ref('')

const statusBadge = (s) => {
  if (s === 'paid') return 'bg-green-500/20 text-green-400'
  if (s === 'pending_payment') return 'bg-amber-500/20 text-amber-400'
  return 'bg-blue-500/20 text-blue-400'
}

const fetchCycles = async () => {
  loading.value = true
  error.value = null
  try {
    const filters = { page: page.value }
    if (statusFilter.value) filters.status = statusFilter.value
    if (dateFrom.value) filters.date_from = dateFrom.value
    if (dateTo.value) filters.date_to = dateTo.value
    if (riderSearch.value) filters.rider_id = riderSearch.value
    const data = await getRiderPayoutCycles(filters)
    cycles.value = data.data || []
    lastPage.value = data.last_page || 1
    summary.total = cycles.value.reduce((s, c) => s + (c.net_payable || 0), 0)
    summary.unpaid = cycles.value.filter(c => c.status !== 'paid').reduce((s, c) => s + (c.net_payable || 0), 0)
    summary.paid = cycles.value.filter(c => c.status === 'paid').reduce((s, c) => s + (c.net_payable || 0), 0)
  } catch (e) {
    error.value = e.message || 'Failed to fetch cycles'
  } finally { loading.value = false }
}

const openPayModal = (cycle) => {
  payingCycle.value = cycle
  utrNumber.value = ''
  paymentMode.value = 'bank_transfer'
  paymentNotes.value = ''
  payError.value = ''
}

const confirmPay = async () => {
  paying.value = true
  payError.value = ''
  try {
    await payRiderPayoutCycle(payingCycle.value.id, utrNumber.value.trim(), paymentMode.value, paymentNotes.value)
    payingCycle.value.status = 'paid'
    payingCycle.value.utr_number = utrNumber.value.trim()
    payingCycle.value = null
    await fetchCycles()
  } catch (e) {
    payError.value = e.error || e.message || 'Payment failed'
  } finally { paying.value = false }
}

const showDetail = (cycle) => {
  alert(`Cycle: ${cycle.cycle_date}\nRider: ${cycle.rider?.name}\nUTR: ${cycle.utr_number}\nPaid: ₹${cycle.net_payable?.toFixed(0)}`)
}

onMounted(fetchCycles)
</script>
