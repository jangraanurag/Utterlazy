<template>
  <div>
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-black text-white flex items-center gap-3">
          <span class="w-10 h-10 bg-gradient-to-br from-emerald-400 to-green-500 rounded-xl flex items-center justify-center text-lg">💰</span>
          Rider Payouts
        </h1>
        <p class="text-white/50 text-sm mt-1">Per-delivery payout breakdown & settlements</p>
      </div>
      <div class="flex gap-3">
        <button @click="handleDownloadCsv" :disabled="dlBusy"
          class="bg-gradient-to-r from-amber-500 to-orange-600 text-white font-bold px-5 py-2.5 rounded-xl flex items-center gap-2 hover:opacity-90 transition-opacity shadow-lg shadow-amber-500/20 disabled:opacity-50">
          <span class="material-symbols-outlined text-[18px]">download</span>
          {{ dlBusy ? 'Downloading...' : 'Download CSV' }}
        </button>
        <button @click="handleSettleAll" :disabled="settling"
          class="bg-gradient-to-r from-emerald-500 to-green-600 text-white font-bold px-5 py-2.5 rounded-xl flex items-center gap-2 hover:opacity-90 transition-opacity shadow-lg shadow-emerald-500/20 disabled:opacity-50">
          <span class="material-symbols-outlined text-[18px]">payments</span>
          {{ settling ? 'Settling...' : 'Settle All' }}
        </button>
      </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-5 gap-4 mb-6">
      <div class="bg-gradient-to-br from-red-500/20 to-red-600/10 border border-red-500/20 rounded-xl p-4">
        <p class="text-red-300/60 text-xs uppercase tracking-wider mb-1">Unsettled</p>
        <p class="text-2xl font-black text-red-400">₹{{ summary.total_unsettled }}</p>
      </div>
      <div class="bg-gradient-to-br from-green-500/20 to-green-600/10 border border-green-500/20 rounded-xl p-4">
        <p class="text-green-300/60 text-xs uppercase tracking-wider mb-1">Settled Today</p>
        <p class="text-2xl font-black text-green-400">₹{{ summary.total_settled_today }}</p>
      </div>
      <div class="bg-gradient-to-br from-blue-500/20 to-blue-600/10 border border-blue-500/20 rounded-xl p-4">
        <p class="text-blue-300/60 text-xs uppercase tracking-wider mb-1">Total Today</p>
        <p class="text-2xl font-black text-blue-400">₹{{ summary.total_payouts_today }}</p>
      </div>
      <div class="bg-gradient-to-br from-purple-500/20 to-purple-600/10 border border-purple-500/20 rounded-xl p-4">
        <p class="text-purple-300/60 text-xs uppercase tracking-wider mb-1">Avg / Delivery</p>
        <p class="text-2xl font-black text-purple-400">₹{{ summary.avg_payout_per_delivery }}</p>
      </div>
      <div class="bg-gradient-to-br from-amber-500/20 to-amber-600/10 border border-amber-500/20 rounded-xl p-4">
        <p class="text-amber-300/60 text-xs uppercase tracking-wider mb-1">Tips Today</p>
        <p class="text-2xl font-black text-amber-400">₹{{ summary.total_tips_today }}</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex gap-3 mb-4">
      <select v-model="settledFilter" @change="fetchPayouts"
        class="bg-white/5 border border-white/10 rounded-lg px-3 py-1.5 text-white text-xs focus:outline-none focus:border-emerald-500">
        <option value="">All Payouts</option>
        <option value="false">Unsettled Only</option>
        <option value="true">Settled Only</option>
      </select>
      <input v-model="dateFilter" @change="fetchPayouts" type="date"
        class="bg-white/5 border border-white/10 rounded-lg px-3 py-1.5 text-white text-xs focus:outline-none focus:border-emerald-500" />
    </div>

    <!-- Table -->
    <div class="bg-white/5 border border-white/10 rounded-2xl overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-white/10 text-white/40 text-xs uppercase tracking-wider">
              <th class="text-left p-4">Rider</th>
              <th class="text-left p-4">Order</th>
              <th class="text-right p-4">Base</th>
              <th class="text-right p-4">Distance</th>
              <th class="text-right p-4">Surge</th>
              <th class="text-right p-4">Wait</th>
              <th class="text-right p-4">Tip</th>
              <th class="text-right p-4">Bonus</th>
              <th class="text-right p-4">Penalty</th>
              <th class="text-right p-4">COD</th>
              <th class="text-right p-4 font-black">Net</th>
              <th class="text-center p-4">Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in payouts" :key="p.id"
              class="border-b border-white/5 hover:bg-white/5 transition-colors">
              <td class="p-4">
                <p class="font-bold text-white text-xs">{{ p.rider?.name || '—' }}</p>
                <p class="text-white/30 text-[10px]">{{ p.rider?.phone || '' }}</p>
              </td>
              <td class="p-4">
                <p class="text-white/60 text-xs font-mono">#{{ (p.order_id || '').slice(0,8) }}</p>
                <p class="text-white/30 text-[10px]">₹{{ parseFloat(p.order?.grand_total || 0).toFixed(0) }}</p>
              </td>
              <td class="p-4 text-right text-white font-bold">₹{{ p.base_pay }}</td>
              <td class="p-4 text-right text-cyan-400">₹{{ p.distance_pay }}</td>
              <td class="p-4 text-right" :class="p.surge_pay > 0 ? 'text-orange-400 font-bold' : 'text-white/30'">
                {{ p.surge_pay > 0 ? '₹' + p.surge_pay : '—' }}
                <span v-if="p.surge_multiplier > 1" class="text-[10px] text-orange-300">({{ p.surge_multiplier }}×)</span>
              </td>
              <td class="p-4 text-right" :class="p.wait_pay > 0 ? 'text-purple-400' : 'text-white/30'">
                {{ p.wait_pay > 0 ? '₹' + p.wait_pay : '—' }}
              </td>
              <td class="p-4 text-right" :class="p.tip_amount > 0 ? 'text-amber-400 font-bold' : 'text-white/30'">
                {{ p.tip_amount > 0 ? '₹' + p.tip_amount : '—' }}
              </td>
              <td class="p-4 text-right" :class="p.incentive_bonus > 0 ? 'text-green-400 font-bold' : 'text-white/30'">
                {{ p.incentive_bonus > 0 ? '₹' + p.incentive_bonus : '—' }}
              </td>
              <td class="p-4 text-right" :class="p.penalty > 0 ? 'text-red-400 font-bold' : 'text-white/30'">
                {{ p.penalty > 0 ? '-₹' + p.penalty : '—' }}
              </td>
              <td class="p-4 text-right" :class="p.cash_collected > 0 ? 'text-red-300' : 'text-white/30'">
                {{ p.cash_collected > 0 ? '-₹' + p.cash_collected : '—' }}
              </td>
              <td class="p-4 text-right">
                <span class="font-black text-lg" :class="p.total_payout >= 0 ? 'text-green-400' : 'text-red-400'">
                  ₹{{ parseFloat(p.total_payout).toFixed(2) }}
                </span>
              </td>
              <td class="p-4 text-center">
                <span :class="p.is_settled ? 'bg-green-500/20 text-green-400' : 'bg-amber-500/20 text-amber-400'"
                  class="px-2 py-1 rounded-full text-[10px] font-bold uppercase">
                  {{ p.is_settled ? 'Settled' : 'Pending' }}
                </span>
              </td>
            </tr>
            <tr v-if="payouts.length === 0">
              <td colspan="12" class="p-12 text-center text-white/30">No payout records found.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="lastPage > 1" class="flex justify-center gap-2 mt-4">
      <button v-for="p in lastPage" :key="p" @click="page = p; fetchPayouts()"
        :class="p === page ? 'bg-white text-black' : 'bg-white/10 text-white/60 hover:bg-white/20'"
        class="w-8 h-8 rounded-lg text-xs font-bold transition-colors">{{ p }}</button>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { getRiderPayouts, settlePayouts, downloadRiderPayoutsExcel } from '../api'

const payouts = ref([])
const page = ref(1)
const lastPage = ref(1)
const settling = ref(false)
const dlBusy = ref(false)
const settledFilter = ref('')
const dateFilter = ref('')

const summary = reactive({
  total_unsettled: 0,
  total_settled_today: 0,
  total_payouts_today: 0,
  avg_payout_per_delivery: 0,
  total_tips_today: 0,
})

const fetchPayouts = async () => {
  try {
    const filters = { page: page.value }
    if (settledFilter.value !== '') filters.settled = settledFilter.value
    if (dateFilter.value) filters.date = dateFilter.value

    const data = await getRiderPayouts(filters)
    payouts.value = data.payouts?.data || []
    lastPage.value = data.payouts?.last_page || 1
    Object.assign(summary, data.summary || {})
  } catch (e) { console.error(e) }
}

const handleSettleAll = async () => {
  if (!confirm('Settle ALL unsettled payouts?')) return
  settling.value = true
  try {
    const result = await settlePayouts()
    alert(`${result.settled_count} payouts settled!`)
    await fetchPayouts()
  } catch (e) { console.error(e) }
  finally { settling.value = false }
}

const handleDownloadCsv = async () => {
  dlBusy.value = true
  try {
    const filters = {}
    if (settledFilter.value !== '') filters.settled = settledFilter.value
    if (dateFilter.value) filters.date = dateFilter.value
    await downloadRiderPayoutsExcel(filters)
  } catch (e) {
    console.error(e)
  } finally {
    dlBusy.value = false
  }
}

onMounted(fetchPayouts)
</script>
