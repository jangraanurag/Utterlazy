<template>
  <div>
    <div class="flex justify-between items-center mb-8">
      <div>
        <h1 class="text-3xl font-serif font-bold text-on-surface">Financial Overview</h1>
        <p class="text-on-surface-variant text-sm mt-1">Consolidated platform financials at a glance</p>
      </div>
      <button @click="refresh" class="px-4 py-2 rounded-lg bg-primary/10 text-primary border border-primary/20 text-xs font-bold flex items-center gap-1">
        <span class="material-symbols-outlined text-[16px]">refresh</span> Refresh
      </button>
    </div>

    <div v-if="loading" class="py-12 text-center text-on-surface-variant">
      <span class="material-symbols-outlined text-4xl animate-spin block mb-2">sync</span>
      Loading financials...
    </div>

    <div v-else class="space-y-6">
      <!-- KPI row -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 p-6">
          <p class="text-on-surface-variant text-xs uppercase tracking-wider mb-1">Today's Revenue</p>
          <p class="text-3xl font-black text-on-surface">₹{{ Number(stats.today_revenue || 0).toLocaleString() }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 p-6">
          <p class="text-on-surface-variant text-xs uppercase tracking-wider mb-1">This Week</p>
          <p class="text-3xl font-black text-on-surface">₹{{ Number(stats.week_revenue || 0).toLocaleString() }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 p-6">
          <p class="text-on-surface-variant text-xs uppercase tracking-wider mb-1">This Month</p>
          <p class="text-3xl font-black text-on-surface">₹{{ Number(stats.month_revenue || 0).toLocaleString() }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 p-6">
          <p class="text-on-surface-variant text-xs uppercase tracking-wider mb-1">Unsettled Payouts</p>
          <p class="text-3xl font-black text-orange-400">₹{{ Number(stats.unsettled_payouts || 0).toLocaleString() }}</p>
        </div>
      </div>

      <!-- Quick export buttons -->
      <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 p-6">
        <h2 class="text-lg font-bold mb-4">Quick Downloads</h2>
        <div class="flex flex-wrap gap-3">
          <button @click="downloadPayments" :disabled="dlBusy" class="px-4 py-2 rounded-xl bg-primary/10 text-primary border border-primary/20 text-sm font-bold hover:bg-primary/20 transition-colors disabled:opacity-50 flex items-center gap-1">
            <span class="material-symbols-outlined text-[18px]">download</span> Payments CSV
          </button>
          <button @click="downloadInvoices" :disabled="dlBusy" class="px-4 py-2 rounded-xl bg-primary/10 text-primary border border-primary/20 text-sm font-bold hover:bg-primary/20 transition-colors disabled:opacity-50 flex items-center gap-1">
            <span class="material-symbols-outlined text-[18px]">download</span> Invoices CSV
          </button>
          <button @click="downloadRiderPayouts" :disabled="dlBusy" class="px-4 py-2 rounded-xl bg-primary/10 text-primary border border-primary/20 text-sm font-bold hover:bg-primary/20 transition-colors disabled:opacity-50 flex items-center gap-1">
            <span class="material-symbols-outlined text-[18px]">download</span> Rider Payouts CSV
          </button>
        </div>
        <p v-if="dlMsg" class="mt-3 text-sm" :class="dlMsgType === 'success' ? 'text-green-400' : 'text-red-400'">{{ dlMsg }}</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getAccountingOverview, downloadPaymentsExcel, downloadInvoicesExcel, downloadRiderPayoutsExcel } from '../api.js'

const stats = ref({})
const loading = ref(true)
const dlBusy = ref(false)
const dlMsg = ref('')
const dlMsgType = ref('success')

const refresh = async () => {
  loading.value = true
  try {
    stats.value = await getAccountingOverview()
  } catch (e) {
    dlMsg.value = 'Failed to load: ' + e.message
    dlMsgType.value = 'error'
  } finally {
    loading.value = false
  }
}

const downloadPayments = async () => {
  dlBusy.value = true; dlMsg.value = ''
  try { const f = await downloadPaymentsExcel(); dlMsg.value = `Downloaded ${f}`; dlMsgType.value = 'success' }
  catch (e) { dlMsg.value = e.message; dlMsgType.value = 'error' }
  finally { dlBusy.value = false }
}

const downloadInvoices = async () => {
  dlBusy.value = true; dlMsg.value = ''
  try { const f = await downloadInvoicesExcel(); dlMsg.value = `Downloaded ${f}`; dlMsgType.value = 'success' }
  catch (e) { dlMsg.value = e.message; dlMsgType.value = 'error' }
  finally { dlBusy.value = false }
}

const downloadRiderPayouts = async () => {
  dlBusy.value = true; dlMsg.value = ''
  try { const f = await downloadRiderPayoutsExcel(); dlMsg.value = `Downloaded ${f}`; dlMsgType.value = 'success' }
  catch (e) { dlMsg.value = e.message; dlMsgType.value = 'error' }
  finally { dlBusy.value = false }
}

onMounted(refresh)
</script>
