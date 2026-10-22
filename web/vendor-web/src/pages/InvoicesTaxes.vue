<script setup>
import { ref, computed, onMounted } from 'vue'
import { getVendorInvoices, downloadInvoicePdf, downloadInvoiceBundle, downloadGstr1Report } from '../api'

const activeTab = ref('commission')
const selectedYear = ref(new Date().getFullYear())
const selectedMonth = ref(new Date().getMonth() + 1)
const invoices = ref([])
const loading = ref(true)
const years = [2024, 2025, 2026, 2027, 2028]
const months = [
  { value: 1, label: 'January' }, { value: 2, label: 'February' }, { value: 3, label: 'March' },
  { value: 4, label: 'April' }, { value: 5, label: 'May' }, { value: 6, label: 'June' },
  { value: 7, label: 'July' }, { value: 8, label: 'August' }, { value: 9, label: 'September' },
  { value: 10, label: 'October' }, { value: 11, label: 'November' }, { value: 12, label: 'December' },
]

async function fetchInvoices() {
  loading.value = true
  try {
    const params = { year: selectedYear.value, month: selectedMonth.value }
    if (activeTab.value !== 'gstr1') params.type = activeTab.value
    const d = await getVendorInvoices(params)
    invoices.value = d.invoices || d.data || []
  } catch (e) { invoices.value = [] }
  loading.value = false
}

function handleDownloadPdf(inv) {
  downloadInvoicePdf(inv.id, `Invoice_${inv.invoice_number || inv.id.slice(0,8)}.pdf`)
}

function handleDownloadBundle() {
  downloadInvoiceBundle(activeTab.value, selectedYear.value, selectedMonth.value)
}

function handleDownloadGstr1() {
  downloadGstr1Report(selectedYear.value, selectedMonth.value)
}

const summary = computed(() => ({
  count: invoices.value.length,
  total: invoices.value.reduce((s, i) => s + Number(i.total_amount || 0), 0).toFixed(2),
}))

function statusBadge(s) {
  const m = { pending: 'badge-warning', approved: 'badge-info', paid: 'badge-success', cancelled: 'badge-error' }
  return m[s] || 'badge-info'
}

onMounted(fetchInvoices)
</script>

<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Invoices & Taxes</h1>
      </div>
    </div>

    <div class="flex gap-3 mb-4 flex-wrap">
      <select v-model="selectedYear" @change="fetchInvoices" class="form-input" style="width:auto;">
        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
      </select>
      <select v-model="selectedMonth" @change="fetchInvoices" class="form-input" style="width:auto;">
        <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
      </select>
    </div>

    <div class="flex gap-1 mb-4 flex-wrap" style="border-bottom:1px solid var(--border);">
      <button v-for="tab in [{k:'commission',l:'Commission'},{k:'ads',l:'Ads'},{k:'recovery',l:'Recovery'},{k:'gstr1',l:'GSTR1'}]" :key="tab.k"
        @click="activeTab=tab.k;fetchInvoices()"
        :style="activeTab===tab.k ? 'border-bottom:2px solid var(--sovereign-orange);color:var(--sovereign-orange);' : 'color:var(--text-muted)'"
        class="font-space font-bold px-3 py-2" style="font-size:0.85rem;border:none;background:none;cursor:pointer;transition:all 0.2s;">
        {{ tab.l }}
      </button>
    </div>

    <div v-if="activeTab !== 'gstr1'" class="flex gap-3 mb-4">
      <div class="card" style="flex:1;">
        <p style="color:var(--text-muted);font-size:0.75rem;text-transform:uppercase;">{{ activeTab === 'commission' ? 'Commission' : activeTab === 'ads' ? 'Ad' : 'Recovery' }} Invoices</p>
        <p class="font-space font-bold" style="font-size:1.5rem;">{{ summary.count }}</p>
      </div>
      <div class="card" style="flex:1;">
        <p style="color:var(--text-muted);font-size:0.75rem;text-transform:uppercase;">Total Amount</p>
        <p class="font-space font-bold" style="font-size:1.5rem;color:var(--sovereign-orange);">₹{{ Number(summary.total).toLocaleString('en-IN') }}</p>
      </div>
      <button class="btn btn-secondary" @click="handleDownloadBundle" style="align-self:center;">⬇ ZIP</button>
    </div>

    <div v-if="activeTab === 'gstr1'" class="card mb-4">
      <p style="color:var(--text-secondary);margin-bottom:12px;">Download GSTR1 report for the selected month</p>
      <button class="btn btn-primary" @click="handleDownloadGstr1">⬇ Download GSTR1 XLSX</button>
    </div>

    <div v-if="loading" class="text-center py-12"><div class="spinner" style="margin:0 auto;"></div></div>
    <div v-else-if="invoices.length === 0" class="card text-center py-12">
      <p class="font-space" style="color:var(--text-muted)">No invoices for this period</p>
    </div>
    <div v-else style="display:grid;gap:8px;">
      <div v-for="inv in invoices" :key="inv.id" class="card">
        <div class="flex items-center justify-between">
          <div>
            <p class="font-mono font-bold" style="font-size:0.85rem;">{{ inv.invoice_number || inv.id.slice(0,8) }}</p>
            <p class="font-space" style="color:var(--text-secondary);font-size:0.8rem;">{{ inv.period_start?.slice(0,10) }} → {{ inv.period_end?.slice(0,10) }}</p>
          </div>
          <div class="text-right flex gap-3 items-center">
            <div>
              <p class="font-space font-bold" style="color:var(--sovereign-orange);">₹{{ Number(inv.total_amount || 0).toLocaleString('en-IN') }}</p>
              <span class="badge" :class="statusBadge(inv.status)">{{ inv.status?.toUpperCase() }}</span>
            </div>
            <button class="btn btn-sm btn-secondary" @click="handleDownloadPdf(inv)">⬇ PDF</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
