<template>
  <div>
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-black text-white flex items-center gap-3">
          <span class="w-10 h-10 bg-gradient-to-br from-indigo-400 to-purple-500 rounded-xl flex items-center justify-center text-lg">📄</span>
          Invoices
        </h1>
        <p class="text-white/50 text-sm mt-1">Commission & Sponsorship invoice management</p>
      </div>
      <div class="flex gap-3">
        <button @click="handleDownloadCsv" :disabled="dlBusy"
          class="bg-gradient-to-r from-amber-500 to-orange-600 text-white font-bold px-5 py-2.5 rounded-xl flex items-center gap-2 hover:opacity-90 transition-opacity shadow-lg shadow-amber-500/20 disabled:opacity-50">
          <span class="material-symbols-outlined text-[18px]">download</span>
          {{ dlBusy ? 'Downloading...' : 'Download CSV' }}
        </button>
        <button @click="openGenModal" :disabled="generating"
          class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white font-bold px-5 py-2.5 rounded-xl flex items-center gap-2 hover:opacity-90 transition-opacity shadow-lg shadow-indigo-500/20 disabled:opacity-50">
          <span class="material-symbols-outlined text-[18px]">receipt_long</span>
          Generate Invoice
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
              <th class="text-left p-4">ID</th>
              <th class="text-left p-4">Restaurant</th>
              <th class="text-left p-4">Invoice #</th>
              <th class="text-left p-4">Type</th>
              <th class="text-left p-4">Period</th>
              <th class="text-right p-4">Base Amt</th>
              <th class="text-right p-4">GST Rate</th>
              <th class="text-right p-4">GST Amt</th>
              <th class="text-right p-4">Total Amt</th>
              <th class="text-center p-4">Status</th>
              <th class="text-center p-4">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="inv in invoices" :key="inv.id"
              class="border-b border-white/5 hover:bg-white/5 transition-colors">
              <td class="p-4 font-mono text-xs text-white/60">#{{ inv.id.slice(0, 8) }}</td>
              <td class="p-4 font-bold text-white">{{ inv.restaurant?.name || 'N/A' }}</td>
              <td class="p-4 text-white/70 font-mono text-xs">{{ inv.invoice_number }}</td>
              <td class="p-4 text-white/70 text-xs">
                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase"
                  :class="inv.type === 'commission' ? 'bg-cyan-500/20 text-cyan-400' : 'bg-purple-500/20 text-purple-400'">
                  {{ inv.type }}
                </span>
              </td>
              <td class="p-4 text-white/70 text-xs">{{ formatDate(inv.period_start) }} → {{ formatDate(inv.period_end) }}</td>
              <td class="p-4 text-right text-white">₹{{ parseFloat(inv.amount).toLocaleString() }}</td>
              <td class="p-4 text-right text-cyan-400">{{ inv.gst_rate }}%</td>
              <td class="p-4 text-right text-white/60">₹{{ parseFloat(inv.gst_amount).toLocaleString() }}</td>
              <td class="p-4 text-right text-orange-400 font-bold">₹{{ parseFloat(inv.total_amount).toLocaleString() }}</td>
              <td class="p-4 text-center">
                <span :class="statusClass(inv.status)"
                  class="px-2 py-1 rounded-full text-[10px] font-bold uppercase">
                  {{ inv.status }}
                </span>
              </td>
              <td class="p-4 text-center">
                <div class="flex gap-1 justify-center">
                  <button v-if="inv.status === 'pending' || inv.status === 'due'" @click="handleApprove(inv.id)"
                    class="px-3 py-1 rounded-lg bg-green-500/20 text-green-400 text-xs font-bold hover:bg-green-500/30 transition-colors">
                    Approve
                  </button>
                  <button v-if="inv.status === 'approved'" @click="handleMarkPaid(inv.id)"
                    class="px-3 py-1 rounded-lg bg-blue-500/20 text-blue-400 text-xs font-bold hover:bg-blue-500/30 transition-colors">
                    Mark Paid
                  </button>
                  <button v-if="inv.status === 'pending' || inv.status === 'due' || inv.status === 'approved'" @click="handleCancel(inv.id)"
                    class="px-3 py-1 rounded-lg bg-red-500/20 text-red-400 text-xs font-bold hover:bg-red-500/30 transition-colors">
                    Cancel
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="invoices.length === 0">
              <td colspan="11" class="p-12 text-center text-white/30">No invoices found.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="lastPage > 1" class="flex justify-center gap-2 mt-4">
      <button v-for="p in lastPage" :key="p" @click="page = p; fetchInvoices()"
        :class="p === page ? 'bg-white text-black' : 'bg-white/10 text-white/60 hover:bg-white/20'"
        class="w-8 h-8 rounded-lg text-xs font-bold transition-colors">{{ p }}</button>
    </div>

    <!-- Modal for Manual Invoice Generation -->
    <div v-if="showGenModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
      <div class="bg-slate-900 border border-white/10 rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl p-6 text-white">
        <div class="flex justify-between items-center mb-6">
          <h3 class="text-xl font-bold">Generate Manual Invoice</h3>
          <button @click="showGenModal = false" class="text-white/40 hover:text-white text-xl">&times;</button>
        </div>
        
        <form @submit.prevent="submitManualInvoice" class="space-y-4">
          <div>
            <label class="block text-xs uppercase tracking-wider text-white/50 mb-1 font-bold">Restaurant</label>
            <select v-model="form.restaurant_id" required class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500">
              <option value="" disabled class="bg-slate-900">Select Restaurant</option>
              <option v-for="res in restaurants" :key="res.id" :value="res.id" class="bg-slate-900">{{ res.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-xs uppercase tracking-wider text-white/50 mb-1 font-bold">Invoice Type</label>
            <select v-model="form.type" required class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500">
              <option value="commission" class="bg-slate-900">Commission (15% on Net Orders)</option>
              <option value="sponsorship" class="bg-slate-900">Sponsorship</option>
            </select>
          </div>

          <div>
            <label class="block text-xs uppercase tracking-wider text-white/50 mb-1 font-bold">Invoice Number</label>
            <input v-model="form.invoice_number" type="text" required placeholder="e.g. INV-2026-001" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 font-mono text-sm" />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs uppercase tracking-wider text-white/50 mb-1 font-bold">Period Start</label>
              <input v-model="form.period_start" type="date" required class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 text-sm" />
            </div>
            <div>
              <label class="block text-xs uppercase tracking-wider text-white/50 mb-1 font-bold">Period End</label>
              <input v-model="form.period_end" type="date" required class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 text-sm" />
            </div>
          </div>

          <div v-if="form.type === 'sponsorship'">
            <label class="block text-xs uppercase tracking-wider text-white/50 mb-1 font-bold">Base Amount (₹)</label>
            <input v-model.number="form.amount" type="number" step="0.01" required min="0" placeholder="0.00" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white focus:outline-none focus:border-indigo-500 font-mono" />
            <p class="text-xs text-white/40 mt-1">18% GST will be added automatically.</p>
          </div>

          <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-white/5">
            <button type="button" @click="showGenModal = false" class="px-5 py-2.5 rounded-xl border border-white/10 font-bold hover:bg-white/5 transition-colors">Cancel</button>
            <button type="submit" :disabled="generating" class="px-5 py-2.5 rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 font-bold text-white hover:opacity-90 transition-opacity disabled:opacity-50">
              {{ generating ? 'Generating...' : 'Generate' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getInvoices, approveInvoice, markInvoicePaid, cancelInvoice, generateInvoices, downloadInvoicesExcel, getRestaurants } from '../api'

const invoices = ref([])
const page = ref(1)
const lastPage = ref(1)
const loading = ref(true)
const error = ref(null)
const generating = ref(false)
const dlBusy = ref(false)

const showGenModal = ref(false)
const restaurants = ref([])
const form = ref({
  restaurant_id: '',
  type: 'commission',
  invoice_number: '',
  period_start: '',
  period_end: '',
  amount: 0
})

const formatDate = (d) => {
  if (!d) return '—'
  const date = new Date(d)
  return date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
}

const statusClass = (s) => ({
  pending: 'bg-amber-500/20 text-amber-400',
  due: 'bg-rose-500/20 text-rose-400 border border-rose-500/30',
  approved: 'bg-blue-500/20 text-blue-400',
  paid: 'bg-green-500/20 text-green-400',
  cancelled: 'bg-red-500/20 text-red-400',
}[s] || 'bg-white/10 text-white/40')

const fetchInvoices = async () => {
  loading.value = true
  error.value = null
  try {
    const data = await getInvoices(page.value)
    invoices.value = data.data || data.invoices || []
    lastPage.value = data.last_page || 1
  } catch (e) {
    error.value = e.message || 'Failed to load'
    invoices.value = []
  } finally {
    loading.value = false
  }
}

const openGenModal = async () => {
  // Pre-fill dates for last week (Monday to Sunday)
  const d = new Date()
  const day = d.getDay()
  // diff to previous monday
  const diffMon = d.getDate() - day + (day === 0 ? -6 : 1) - 7
  const prevMonday = new Date(d.setDate(diffMon))
  const prevSunday = new Date(prevMonday)
  prevSunday.setDate(prevSunday.getDate() + 6)
  
  form.value.period_start = prevMonday.toISOString().slice(0, 10)
  form.value.period_end = prevSunday.toISOString().slice(0, 10)
  form.value.restaurant_id = ''
  form.value.type = 'commission'
  form.value.invoice_number = 'INV-' + Date.now().toString().slice(-6)
  form.value.amount = 0
  
  showGenModal.value = true
  
  // Load restaurants
  try {
    const res = await getRestaurants('', 1)
    restaurants.value = res.data || res.restaurants || res
  } catch (e) {
    console.error('Failed to load restaurants', e)
  }
}

const submitManualInvoice = async () => {
  generating.value = true
  try {
    const dataToSend = {
      restaurant_id: form.value.restaurant_id,
      type: form.value.type,
      invoice_number: form.value.invoice_number,
      period_start: form.value.period_start,
      period_end: form.value.period_end
    }
    if (form.value.type === 'sponsorship') {
      dataToSend.amount = form.value.amount
    }
    const result = await generateInvoices(dataToSend)
    alert(result.message || 'Invoice generated!')
    showGenModal.value = false
    await fetchInvoices()
  } catch (e) {
    alert(e.message || 'Failed to generate invoice')
  } finally {
    generating.value = false
  }
}

const handleApprove = async (id) => {
  try {
    await approveInvoice(id)
    await fetchInvoices()
  } catch (e) {
    error.value = e.message || 'Failed to approve'
  }
}

const handleMarkPaid = async (id) => {
  try {
    await markInvoicePaid(id)
    await fetchInvoices()
  } catch (e) {
    error.value = e.message || 'Failed to mark paid'
  }
}

const handleCancel = async (id) => {
  if (!confirm('Cancel this invoice?')) return
  try {
    await cancelInvoice(id)
    await fetchInvoices()
  } catch (e) {
    error.value = e.message || 'Failed to cancel'
  }
}

const handleDownloadCsv = async () => {
  dlBusy.value = true
  try {
    await downloadInvoicesExcel()
  } catch (e) {
    error.value = e.message || 'Download failed'
  } finally {
    dlBusy.value = false
  }
}

onMounted(fetchInvoices)
</script>
