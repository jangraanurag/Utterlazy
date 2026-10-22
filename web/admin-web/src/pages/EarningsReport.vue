<template>
  <div>
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-black text-white flex items-center gap-3">
          <span class="w-10 h-10 bg-gradient-to-br from-amber-400 to-red-500 rounded-xl flex items-center justify-center text-lg">📊</span>
          Platform Earnings
        </h1>
        <p class="text-white/50 text-sm mt-1">Consolidated revenue report across all streams</p>
      </div>
      <div class="flex gap-3">
        <button @click="handleDownloadEarnings" :disabled="dlBusy"
          class="bg-gradient-to-r from-amber-500 to-orange-600 text-white font-bold px-5 py-2.5 rounded-xl flex items-center gap-2 hover:opacity-90 transition-opacity shadow-lg shadow-amber-500/20 disabled:opacity-50">
          <span class="material-symbols-outlined text-[18px]">download</span>
          {{ dlBusy ? 'Downloading...' : 'Download Earnings' }}
        </button>
        <button @click="fetchOverview" :disabled="loading"
          class="bg-white/10 text-white font-bold px-4 py-2.5 rounded-xl flex items-center gap-2 hover:bg-white/20 transition-opacity disabled:opacity-50">
          <span class="material-symbols-outlined text-[18px]">refresh</span>
          Refresh
        </button>
      </div>
    </div>

    <div v-if="loading" class="text-center py-12 text-on-surface-variant">Loading...</div>

    <div v-else-if="error" class="text-center py-12 text-red-500">{{ error }}</div>

    <div v-else class="space-y-6">
      <!-- Revenue KPI row -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white/5 border border-white/10 rounded-2xl p-6">
          <p class="text-white/40 text-xs uppercase tracking-wider mb-1">Commission Revenue</p>
          <p class="text-3xl font-black text-white">₹{{ Number(overview.commission_revenue || 0).toLocaleString() }}</p>
        </div>
        <div class="bg-white/5 border border-white/10 rounded-2xl p-6">
          <p class="text-white/40 text-xs uppercase tracking-wider mb-1">GST Collected</p>
          <p class="text-3xl font-black text-orange-400">₹{{ Number(overview.gst_collected || 0).toLocaleString() }}</p>
        </div>
        <div class="bg-white/5 border border-white/10 rounded-2xl p-6">
          <p class="text-white/40 text-xs uppercase tracking-wider mb-1">Sponsorship Revenue</p>
          <p class="text-3xl font-black text-purple-400">₹{{ Number(overview.sponsorship_revenue || 0).toLocaleString() }}</p>
        </div>
        <div class="bg-white/5 border border-white/10 rounded-2xl p-6">
          <p class="text-white/40 text-xs uppercase tracking-wider mb-1">Net Platform Earnings</p>
          <p class="text-3xl font-black text-green-400">₹{{ Number(overview.net_earnings || 0).toLocaleString() }}</p>
        </div>
      </div>

      <!-- Monthly Breakdown -->
      <div class="bg-white/5 border border-white/10 rounded-2xl overflow-hidden">
        <div class="p-6 border-b border-white/10">
          <h2 class="text-lg font-bold text-white">Monthly Breakdown ({{ selectedYear }})</h2>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-white/10 text-white/40 text-xs uppercase tracking-wider">
                <th class="text-left p-4">Month</th>
                <th class="text-right p-4">Total Orders</th>
                <th class="text-right p-4">Gross Revenue</th>
                <th class="text-right p-4">Commission</th>
                <th class="text-right p-4">GST</th>
                <th class="text-right p-4">Sponsorship</th>
                <th class="text-right p-4 font-black">Net Earnings</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="m in overview.monthly" :key="m.month"
                class="border-b border-white/5 hover:bg-white/5 transition-colors">
                <td class="p-4 font-bold text-white">{{ monthName(m.month) }}</td>
                <td class="p-4 text-right text-white/70">{{ m.total_orders }}</td>
                <td class="p-4 text-right text-white">₹{{ Number(m.gross_revenue).toLocaleString() }}</td>
                <td class="p-4 text-right text-orange-400">₹{{ Number(m.commission).toLocaleString() }}</td>
                <td class="p-4 text-right text-cyan-400">₹{{ Number(m.gst).toLocaleString() }}</td>
                <td class="p-4 text-right text-purple-400">₹{{ Number(m.sponsorship).toLocaleString() }}</td>
                <td class="p-4 text-right font-black text-lg text-green-400">₹{{ Number(m.net_earnings).toLocaleString() }}</td>
              </tr>
              <tr v-if="!overview.monthly || overview.monthly.length === 0">
                <td colspan="7" class="p-12 text-center text-white/30">No monthly data yet.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { getAccountingOverview, downloadBlob } from '../api'

const loading = ref(true)
const error = ref(null)
const dlBusy = ref(false)
const selectedYear = ref(new Date().getFullYear())

const overview = reactive({
  commission_revenue: 0,
  gst_collected: 0,
  sponsorship_revenue: 0,
  net_earnings: 0,
  monthly: [],
})

const monthName = (m) => ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][m - 1] || m

const fetchOverview = async () => {
  loading.value = true
  error.value = null
  try {
    const data = await getAccountingOverview()
    Object.assign(overview, data.data || data)
  } catch (e) {
    error.value = e.message || 'Failed to load'
  } finally {
    loading.value = false
  }
}

const handleDownloadEarnings = async () => {
  dlBusy.value = true
  try {
    await downloadBlob('/api/admin/earnings/export', `Earnings_${selectedYear.value}.xlsx`)
  } catch (e) {
    error.value = e.message || 'Download failed'
  } finally {
    dlBusy.value = false
  }
}

onMounted(fetchOverview)
</script>
