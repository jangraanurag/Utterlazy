<template>
  <div>
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-black text-white flex items-center gap-3">
          <span class="w-10 h-10 bg-gradient-to-br from-amber-400 to-orange-500 rounded-xl flex items-center justify-center text-lg">💳</span>
          Vendor Payments
        </h1>
        <p class="text-white/50 text-sm mt-1">Manage restaurant weekly payouts</p>
      </div>
      <div class="flex gap-3">
        <button @click="handleDownloadCsv" :disabled="dlBusy"
          class="bg-gradient-to-r from-amber-500 to-orange-600 text-white font-bold px-5 py-2.5 rounded-xl flex items-center gap-2 hover:opacity-90 transition-opacity shadow-lg shadow-amber-500/20 disabled:opacity-50">
          <span class="material-symbols-outlined text-[18px]">download</span>
          {{ dlBusy ? 'Downloading...' : 'Download CSV' }}
        </button>
        <button @click="handleGenerateWeekly" :disabled="generating"
          class="bg-gradient-to-r from-emerald-500 to-green-600 text-white font-bold px-5 py-2.5 rounded-xl flex items-center gap-2 hover:opacity-90 transition-opacity shadow-lg shadow-emerald-500/20 disabled:opacity-50">
          <span class="material-symbols-outlined text-[18px]">auto_awesome</span>
          {{ generating ? 'Generating...' : 'Generate Weekly' }}
        </button>
      </div>
    </div>

    <div v-if="loading" class="text-center py-12 text-on-surface-variant">Loading...</div>

    <div v-else-if="error" class="text-center py-12 text-red-500">{{ error }}</div>

    <div v-else class="bg-white/5 border border-white/10 rounded-2xl overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-white/10 text-white/40 text-xs uppercase tracking-wider">
              <th class="text-left p-4">Payment ID</th>
              <th class="text-left p-4">Restaurant</th>
              <th class="text-left p-4">Period</th>
              <th class="text-right p-4">Total Orders</th>
              <th class="text-right p-4">Gross Revenue</th>
              <th class="text-right p-4">Commission %</th>
              <th class="text-right p-4">Commission Amt</th>
              <th class="text-right p-4">Refunds</th>
              <th class="text-right p-4">Net Payable</th>
              <th class="text-center p-4">Status</th>
              <th class="text-center p-4">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in payments" :key="p.id"
              class="border-b border-white/5 hover:bg-white/5 transition-colors">
              <td class="p-4 font-mono text-xs text-white/60">#{{ p.id }}</td>
              <td class="p-4 font-bold text-white">{{ p.restaurant?.name || 'N/A' }}</td>
              <td class="p-4 text-white/70 text-xs">{{ formatDate(p.week_start) }} → {{ formatDate(p.week_end) }}</td>
              <td class="p-4 text-right text-white font-bold">{{ p.total_orders }}</td>
              <td class="p-4 text-right text-white">₹{{ parseFloat(p.gross_revenue).toLocaleString() }}</td>
              <td class="p-4 text-right text-cyan-400">{{ p.commission_rate }}%</td>
              <td class="p-4 text-right text-orange-400">₹{{ parseFloat(p.commission_amount).toLocaleString() }}</td>
              <td class="p-4 text-right text-red-400">-₹{{ parseFloat(p.total_refunds).toLocaleString() }}</td>
              <td class="p-4 text-right font-black text-lg text-green-400">₹{{ parseFloat(p.net_payable).toLocaleString() }}</td>
              <td class="p-4 text-center">
                <span :class="statusClass(p.status)"
                  class="px-2 py-1 rounded-full text-[10px] font-bold uppercase">
                  {{ p.status }}
                </span>
              </td>
              <td class="p-4 text-center">
                <div class="flex gap-1 justify-center">
                  <button v-if="p.status === 'pending'" @click="handleApprove(p.id)"
                    class="px-3 py-1 rounded-lg bg-green-500/20 text-green-400 text-xs font-bold hover:bg-green-500/30 transition-colors">
                    Approve
                  </button>
                  <button v-if="p.status === 'approved'" @click="handleComplete(p.id)"
                    class="px-3 py-1 rounded-lg bg-blue-500/20 text-blue-400 text-xs font-bold hover:bg-blue-500/30 transition-colors">
                    Complete
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="payments.length === 0">
              <td colspan="11" class="p-12 text-center text-white/30">No vendor payments found.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="lastPage > 1" class="flex justify-center gap-2 mt-4">
      <button v-for="p in lastPage" :key="p" @click="page = p; fetchPayments()"
        :class="p === page ? 'bg-white text-black' : 'bg-white/10 text-white/60 hover:bg-white/20'"
        class="w-8 h-8 rounded-lg text-xs font-bold transition-colors">{{ p }}</button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getVendorPayments, approveVendorPayment, completeVendorPayment, generateWeeklyPayments, downloadPaymentsExcel } from '../api'

const payments = ref([])
const page = ref(1)
const lastPage = ref(1)
const loading = ref(true)
const error = ref(null)
const generating = ref(false)
const dlBusy = ref(false)

const formatDate = (d) => {
  if (!d) return '—'
  const date = new Date(d)
  return date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
}

const statusClass = (s) => ({
  pending: 'bg-amber-500/20 text-amber-400',
  approved: 'bg-blue-500/20 text-blue-400',
  completed: 'bg-green-500/20 text-green-400',
}[s] || 'bg-white/10 text-white/40')

const fetchPayments = async () => {
  loading.value = true
  error.value = null
  try {
    const data = await getVendorPayments(page.value)
    payments.value = data.data || []
    lastPage.value = data.last_page || 1
  } catch (e) {
    error.value = e.message || 'Failed to load'
    payments.value = []
  } finally {
    loading.value = false
  }
}

const handleApprove = async (id) => {
  try {
    await approveVendorPayment(id)
    await fetchPayments()
  } catch (e) {
    error.value = e.message || 'Failed to approve'
  }
}

const handleComplete = async (id) => {
  try {
    await completeVendorPayment(id)
    await fetchPayments()
  } catch (e) {
    error.value = e.message || 'Failed to complete'
  }
}

const handleGenerateWeekly = async () => {
  if (!confirm('Generate weekly payments for all vendors?')) return
  generating.value = true
  try {
    const result = await generateWeeklyPayments()
    alert(result.message || 'Payments generated!')
    await fetchPayments()
  } catch (e) {
    error.value = e.message || 'Failed to generate'
  } finally {
    generating.value = false
  }
}

const handleDownloadCsv = async () => {
  dlBusy.value = true
  try {
    await downloadPaymentsExcel()
  } catch (e) {
    error.value = e.message || 'Download failed'
  } finally {
    dlBusy.value = false
  }
}

onMounted(fetchPayments)
</script>
