<template>
  <div>
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-black text-white flex items-center gap-3">
          <span class="w-10 h-10 bg-gradient-to-br from-teal-400 to-emerald-500 rounded-xl flex items-center justify-center text-lg">🧾</span>
          GST Reports
        </h1>
        <p class="text-white/50 text-sm mt-1">Restaurant-wise monthly GST summary</p>
      </div>
    </div>

    <div class="bg-white/5 border border-white/10 rounded-2xl p-6 mb-8">
      <div class="flex items-end gap-4 flex-wrap">
        <div>
          <label class="text-white/40 text-xs uppercase tracking-wider block mb-1">Restaurant</label>
          <select v-model="selectedRestaurant"
            class="bg-white/5 border border-white/10 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-teal-500 min-w-[220px]">
            <option value="">Select a restaurant</option>
            <option v-for="r in restaurants" :key="r.id" :value="r.id">{{ r.name }}</option>
          </select>
        </div>
        <div>
          <label class="text-white/40 text-xs uppercase tracking-wider block mb-1">Year</label>
          <select v-model="selectedYear"
            class="bg-white/5 border border-white/10 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-teal-500">
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
        <div>
          <label class="text-white/40 text-xs uppercase tracking-wider block mb-1">Month</label>
          <select v-model="selectedMonth"
            class="bg-white/5 border border-white/10 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-teal-500">
            <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </div>
        <button @click="handleGenerateReport"
          :disabled="!selectedRestaurant || fetching"
          class="bg-gradient-to-r from-teal-500 to-emerald-600 text-white font-bold px-5 py-2.5 rounded-xl flex items-center gap-2 hover:opacity-90 transition-opacity shadow-lg shadow-teal-500/20 disabled:opacity-50">
          <span class="material-symbols-outlined text-[18px]">description</span>
          {{ fetching ? 'Fetching...' : 'Generate Report' }}
        </button>
        <button @click="handleDownloadExcel"
          :disabled="!selectedRestaurant || dlBusy"
          class="bg-gradient-to-r from-amber-500 to-orange-600 text-white font-bold px-5 py-2.5 rounded-xl flex items-center gap-2 hover:opacity-90 transition-opacity shadow-lg shadow-amber-500/20 disabled:opacity-50">
          <span class="material-symbols-outlined text-[18px]">download</span>
          {{ dlBusy ? 'Downloading...' : 'Download Excel' }}
        </button>
      </div>
    </div>

    <div v-if="fetching && !report" class="text-center py-12 text-on-surface-variant">
      <span class="material-symbols-outlined text-4xl animate-spin block mb-2">sync</span>
      Loading report...
    </div>

    <div v-else-if="error" class="text-center py-12 text-red-500">{{ error }}</div>

    <div v-else-if="report === null" class="text-center py-12 text-on-surface-variant">
      Select a restaurant, year, and month, then click Generate Report.
    </div>

    <div v-else-if="report && report.orders && report.orders.length === 0" class="text-center py-12 text-on-surface-variant">
      No data found for this period.
    </div>

    <div v-else-if="report" class="space-y-6">
      <div class="bg-white/5 border border-white/10 rounded-2xl p-6">
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <span class="text-white/40 text-xs uppercase tracking-wider">Restaurant</span>
            <p class="text-white font-bold text-lg">{{ report.restaurant?.name }}</p>
          </div>
          <div>
            <span class="text-white/40 text-xs uppercase tracking-wider">GSTIN</span>
            <p class="text-white font-mono">{{ report.restaurant?.gstin || 'N/A' }}</p>
          </div>
        </div>
      </div>

      <div class="bg-white/5 border border-white/10 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-white/10 text-white/40 text-xs uppercase tracking-wider">
                <th class="text-left p-4">Order #</th>
                <th class="text-left p-4">Date</th>
                <th class="text-right p-4">Subtotal</th>
                <th class="text-right p-4">CGST</th>
                <th class="text-right p-4">SGST</th>
                <th class="text-right p-4">GST Amount</th>
                <th class="text-right p-4">Grand Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="o in report.orders" :key="o.order_number"
                class="border-b border-white/5 hover:bg-white/5 transition-colors">
                <td class="p-4 font-mono text-xs text-white/60">#{{ o.order_number }}</td>
                <td class="p-4 text-white/70 text-xs">{{ formatDate(o.created_at) }}</td>
                <td class="p-4 text-right text-white">₹{{ parseFloat(o.subtotal).toFixed(2) }}</td>
                <td class="p-4 text-right text-cyan-400">₹{{ parseFloat(o.cgst).toFixed(2) }}</td>
                <td class="p-4 text-right text-cyan-400">₹{{ parseFloat(o.sgst).toFixed(2) }}</td>
                <td class="p-4 text-right text-orange-400 font-bold">₹{{ (parseFloat(o.cgst) + parseFloat(o.sgst)).toFixed(2) }}</td>
                <td class="p-4 text-right font-black text-green-400">₹{{ parseFloat(o.grand_total).toFixed(2) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="report.summary" class="bg-gradient-to-br from-teal-500/10 to-emerald-600/5 border border-teal-500/20 rounded-2xl p-6">
        <h3 class="text-white/40 text-xs uppercase tracking-wider mb-4 font-bold">Summary</h3>
        <div class="grid grid-cols-4 gap-6">
          <div>
            <p class="text-white/40 text-xs uppercase tracking-wider">Total Orders</p>
            <p class="text-2xl font-black text-white">{{ report.summary.total_orders }}</p>
          </div>
          <div>
            <p class="text-white/40 text-xs uppercase tracking-wider">Total Taxable Value</p>
            <p class="text-2xl font-black text-white">₹{{ parseFloat(report.summary.total_taxable).toLocaleString() }}</p>
          </div>
          <div>
            <p class="text-white/40 text-xs uppercase tracking-wider">Total GST</p>
            <p class="text-2xl font-black text-orange-400">₹{{ parseFloat(report.summary.total_gst).toLocaleString() }}</p>
          </div>
          <div>
            <p class="text-white/40 text-xs uppercase tracking-wider">Total Invoice Value</p>
            <p class="text-2xl font-black text-green-400">₹{{ parseFloat(report.summary.total_invoice_value).toLocaleString() }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getGstRestaurants, getGstReport, downloadGstExcel } from '../api'

const restaurants = ref([])
const selectedRestaurant = ref('')
const selectedYear = ref(new Date().getFullYear())
const selectedMonth = ref(new Date().getMonth() + 1)
const report = ref(null)
const fetching = ref(false)
const error = ref(null)
const dlBusy = ref(false)

const currentYear = new Date().getFullYear()
const years = Array.from({ length: 4 }, (_, i) => currentYear - i)

const months = [
  { value: 1, label: 'January' }, { value: 2, label: 'February' }, { value: 3, label: 'March' },
  { value: 4, label: 'April' }, { value: 5, label: 'May' }, { value: 6, label: 'June' },
  { value: 7, label: 'July' }, { value: 8, label: 'August' }, { value: 9, label: 'September' },
  { value: 10, label: 'October' }, { value: 11, label: 'November' }, { value: 12, label: 'December' },
]

const formatDate = (d) => {
  if (!d) return '—'
  const date = new Date(d)
  return date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
}

const fetchRestaurants = async () => {
  try {
    const data = await getGstRestaurants()
    restaurants.value = data.data || data.restaurants || data || []
  } catch (e) {
    console.error(e)
  }
}

const handleGenerateReport = async () => {
  if (!selectedRestaurant.value) return
  fetching.value = true
  error.value = null
  report.value = null
  try {
    const data = await getGstReport(selectedRestaurant.value, selectedYear.value, selectedMonth.value)
    report.value = data.data || data
  } catch (e) {
    error.value = e.message || 'Failed to fetch report'
  } finally {
    fetching.value = false
  }
}

const handleDownloadExcel = async () => {
  if (!selectedRestaurant.value) return
  dlBusy.value = true
  try {
    await downloadGstExcel(selectedRestaurant.value, selectedYear.value, selectedMonth.value)
  } catch (e) {
    error.value = e.message || 'Download failed'
  } finally {
    dlBusy.value = false
  }
}

onMounted(fetchRestaurants)
</script>
