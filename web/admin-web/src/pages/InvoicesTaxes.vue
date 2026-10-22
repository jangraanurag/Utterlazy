<template>
  <div>
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-black text-white flex items-center gap-3">
          <span class="w-10 h-10 bg-gradient-to-br from-cyan-400 to-blue-500 rounded-xl flex items-center justify-center text-lg">🧾</span>
          Invoices & Taxes
        </h1>
        <p class="text-white/50 text-sm mt-1">Manage GST invoices, ads, recovery, and GSTR1 reports</p>
      </div>
      <div class="flex gap-2">
        <button @click="openGenerateModal"
          class="bg-gradient-to-r from-emerald-500 to-green-600 text-white font-bold px-4 py-2.5 rounded-xl flex items-center gap-2 hover:opacity-90 text-sm">
          <span class="material-symbols-outlined text-[18px]">add_circle</span>
          Generate Invoice
        </button>
        <button v-if="activeTab !== 'gstr1'" @click="downloadBundleFn"
          class="bg-gradient-to-r from-amber-500 to-orange-600 text-white font-bold px-4 py-2.5 rounded-xl flex items-center gap-2 hover:opacity-90 text-sm" :disabled="dlBusy">
          <span class="material-symbols-outlined text-[18px]">folder_zip</span>
          Download ZIP
        </button>
        <button v-if="activeTab === 'gstr1'" @click="downloadGstr1Fn"
          class="bg-gradient-to-r from-amber-500 to-orange-600 text-white font-bold px-4 py-2.5 rounded-xl flex items-center gap-2 hover:opacity-90 text-sm" :disabled="dlBusy">
          <span class="material-symbols-outlined text-[18px]">description</span>
          Download GSTR1 XLSX
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex gap-4 mb-6 flex-wrap">
      <select v-model="selectedOutlet" @change="fetchData"
        class="bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none">
        <option value="">All Restaurants</option>
        <option v-for="r in restaurantList" :key="r.id" :value="r.id">{{ r.name }}</option>
      </select>
      <select v-model="filters.year" @change="fetchData"
        class="bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none">
        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
      </select>
      <select v-model="filters.month" @change="fetchData"
        class="bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none">
        <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
      </select>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 mb-6 border-b border-white/10">
      <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key; fetchData()"
        :class="activeTab === tab.key ? 'border-b-2 border-secondary text-secondary' : 'text-white/40 hover:text-white/70'"
        class="px-4 py-2.5 text-sm font-bold uppercase tracking-wider transition-colors">
        {{ tab.label }}
      </button>
    </div>

    <!-- Summary Cards (commission/ads/recovery tabs) -->
    <div v-if="activeTab !== 'gstr1'" class="grid grid-cols-5 gap-4 mb-6">
      <div class="bg-white/5 rounded-xl p-4"><p class="text-white/40 text-xs uppercase">Invoices</p><p class="text-white text-xl font-black">{{ summary.invoices }}</p></div>
      <div class="bg-white/5 rounded-xl p-4"><p class="text-white/40 text-xs uppercase">Taxable Value</p><p class="text-white text-xl font-black">₹{{ summary.taxable | num }}</p></div>
      <div class="bg-white/5 rounded-xl p-4"><p class="text-white/40 text-xs uppercase">CGST @ 9%</p><p class="text-blue-400 text-xl font-black">₹{{ summary.cgst | num }}</p></div>
      <div class="bg-white/5 rounded-xl p-4"><p class="text-white/40 text-xs uppercase">SGST @ 9%</p><p class="text-green-400 text-xl font-black">₹{{ summary.sgst | num }}</p></div>
      <div class="bg-white/5 rounded-xl p-4"><p class="text-white/40 text-xs uppercase">Total</p><p class="text-cyan-400 text-xl font-black">₹{{ summary.total | num }}</p></div>
    </div>

    <!-- GSTR1 Info -->
    <div v-if="activeTab === 'gstr1'" class="bg-white/5 border border-white/10 rounded-xl p-4 mb-6">
      <p class="text-white/60 text-sm">GSTR1 report includes 6 sheets: B2CS (Table 7), B2CS Summary (7A), Nil/Exempt (Table 8), HSN Summary (Table 12), Tax Liability, and At-a-glance. Click "Download GSTR1 XLSX" above.</p>
    </div>

    <div v-if="loading" class="text-center py-12 text-on-surface-variant">Loading...</div>
    <div v-else-if="error" class="text-center py-12 text-red-500">{{ error }}</div>

    <!-- Table -->
    <div v-else class="bg-white/5 border border-white/10 rounded-2xl overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-white/10 text-white/40 text-xs uppercase tracking-wider">
              <th class="text-left p-3">Invoice #</th>
              <th class="text-left p-3">Restaurant</th>
              <th class="text-left p-3">Period</th>
              <th class="text-right p-3">Amount</th>
              <th class="text-right p-3">CGST</th>
              <th class="text-right p-3">SGST</th>
              <th class="text-right p-3">Total</th>
              <th class="text-center p-3">Status</th>
              <th class="text-center p-3">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="inv in items" :key="inv.id" class="border-b border-white/5 hover:bg-white/5 transition-colors">
              <td class="p-3 font-mono text-xs text-white/80">{{ inv.invoice_number }}</td>
              <td class="p-3 text-white text-sm">{{ inv.restaurant?.name || inv.restaurant_id?.slice(0,8) }}</td>
              <td class="p-3 text-white/50 text-xs">{{ fmtDate(inv.period_start) }} → {{ fmtDate(inv.period_end) }}</td>
              <td class="p-3 text-right text-white">₹{{ +inv.amount | num }}</td>
              <td class="p-3 text-right text-blue-400">₹{{ +inv.cgst_amount | num }}</td>
              <td class="p-3 text-right text-green-400">₹{{ +inv.sgst_amount | num }}</td>
              <td class="p-3 text-right text-white font-bold">₹{{ +inv.total_amount | num }}</td>
              <td class="p-3 text-center">
                <span :class="inv.status === 'paid' ? 'bg-green-500/20 text-green-400' : inv.status === 'approved' ? 'bg-blue-500/20 text-blue-400' : 'bg-amber-500/20 text-amber-400'"
                  class="px-2 py-1 rounded-full text-[10px] font-bold uppercase">{{ inv.status }}</span>
              </td>
              <td class="p-3 text-center">
                <div class="flex gap-1 justify-center">
                  <button @click="downloadInvoiceFn(inv)" class="px-2 py-1 rounded-lg bg-white/10 text-white/60 text-xs font-bold hover:bg-white/20" title="Download PDF">PDF</button>
                  <button v-if="inv.status === 'pending'" @click="openEditModal(inv)" class="px-2 py-1 rounded-lg bg-amber-500/20 text-amber-400 text-xs font-bold hover:bg-amber-500/30">Edit#</button>
                  <button v-if="inv.status === 'pending'" @click="approveInvoiceFn(inv.id)" class="px-2 py-1 rounded-lg bg-green-500/20 text-green-400 text-xs font-bold hover:bg-green-500/30">Approve</button>
                  <button v-if="inv.status === 'approved'" @click="markPaidFn(inv.id)" class="px-2 py-1 rounded-lg bg-blue-500/20 text-blue-400 text-xs font-bold hover:bg-blue-500/30">Paid</button>
                  <button v-if="inv.status !== 'paid' && inv.status !== 'cancelled'" @click="cancelInvoiceFn(inv.id)" class="px-2 py-1 rounded-lg bg-red-500/20 text-red-400 text-xs font-bold hover:bg-red-500/30">X</button>
                </div>
              </td>
            </tr>
            <tr v-if="items.length === 0">
              <td colspan="9" class="p-12 text-center text-white/30">No invoices found for this period.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Generate Invoice Modal -->
    <div v-if="showGenModal" class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center" @click.self="showGenModal = false">
      <div class="bg-surface border border-white/10 rounded-2xl p-6 w-full max-w-md mx-4">
        <h2 class="text-xl font-bold text-white mb-6">Generate Invoice</h2>
        <div class="space-y-4">
          <div>
            <label class="text-white/60 text-xs uppercase font-bold mb-1 block">Restaurant</label>
            <select v-model="genForm.restaurant_id"
              class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm">
              <option value="">Select...</option>
              <option v-for="r in restaurantList" :key="r.id" :value="r.id">{{ r.name }}</option>
            </select>
          </div>
          <div>
            <label class="text-white/60 text-xs uppercase font-bold mb-1 block">Type</label>
            <select v-model="genForm.type"
              class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm">
              <option value="commission">Commission (Online Ordering)</option>
              <option value="ads">Ads</option>
              <option value="recovery">Recovery</option>
            </select>
          </div>
          <div>
            <label class="text-white/60 text-xs uppercase font-bold mb-1 block">Invoice Number</label>
            <input v-model="genForm.invoice_number" placeholder="Auto-generated if empty"
              class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm" />
          </div>
          <div class="flex gap-4">
            <div class="flex-1">
              <label class="text-white/60 text-xs uppercase font-bold mb-1 block">Start</label>
              <input type="date" v-model="genForm.period_start"
                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm" />
            </div>
            <div class="flex-1">
              <label class="text-white/60 text-xs uppercase font-bold mb-1 block">End</label>
              <input type="date" v-model="genForm.period_end"
                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm" />
            </div>
          </div>
          <p v-if="genError" class="text-red-400 text-sm">{{ genError }}</p>
          <button @click="handleGenerate" :disabled="genBusy || !genForm.restaurant_id || !genForm.period_start"
            class="w-full bg-gradient-to-r from-emerald-500 to-green-600 text-white font-bold py-3 rounded-xl hover:opacity-90 disabled:opacity-50">
            {{ genBusy ? 'Generating...' : 'Generate' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Edit Invoice Number Modal -->
    <div v-if="showEditModal" class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center" @click.self="showEditModal = false">
      <div class="bg-surface border border-white/10 rounded-2xl p-6 w-full max-w-md mx-4">
        <h2 class="text-xl font-bold text-white mb-6">Edit Invoice Number</h2>
        <input v-model="editInvNumber"
          class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm mb-4" />
        <button @click="handleEditInvoice" :disabled="editBusy"
          class="w-full bg-gradient-to-r from-blue-500 to-blue-600 text-white font-bold py-3 rounded-xl hover:opacity-90 disabled:opacity-50">
          {{ editBusy ? 'Saving...' : 'Save' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { listGstRestaurants, downloadInvoicePdf, downloadInvoiceBundle, downloadGstr1Xlsx } from '../api'

const getInvoices = async (params) => {
  return await fetchInvoiceList(params)
}
const approveInvoiceById = async (id) => {
  const token = localStorage.getItem('admin_token')
  await fetch(`https://catalog.utterlazy.com/api/admin/invoices/${id}/approve`, {
    method: 'PATCH', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Authorization': `Bearer ${token}` }
  }).then(r => r.ok ? r.json() : Promise.reject(r))
}
const markInvoicePaidById = async (id) => {
  const token = localStorage.getItem('admin_token')
  await fetch(`https://catalog.utterlazy.com/api/admin/invoices/${id}/mark-paid`, {
    method: 'PATCH', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Authorization': `Bearer ${token}` }
  }).then(r => r.ok ? r.json() : Promise.reject(r))
}
const cancelInvoiceById = async (id) => {
  const token = localStorage.getItem('admin_token')
  await fetch(`https://catalog.utterlazy.com/api/admin/invoices/${id}/cancel`, {
    method: 'PATCH', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Authorization': `Bearer ${token}` }
  }).then(r => r.ok ? r.json() : Promise.reject(r))
}
const generateInvoiceRequest = async (data) => {
  const token = localStorage.getItem('admin_token')
  const res = await fetch('https://catalog.utterlazy.com/api/admin/invoices/generate', {
    method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Authorization': `Bearer ${token}` },
    body: JSON.stringify(data)
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

const fetchInvoiceList = async (params) => {
  const token = localStorage.getItem('admin_token')
  const qs = new URLSearchParams()
  Object.entries(params).forEach(([k,v]) => { if (v) qs.append(k,v) })
  const res = await fetch(`https://catalog.utterlazy.com/api/admin/invoices?${qs}`, {
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Authorization': `Bearer ${token}` }
  })
  if (!res.ok) throw new Error('Failed')
  return await res.json()
}

const tabs = [
  { key: 'commission', label: 'Online Ordering' },
  { key: 'ads',        label: 'Ads' },
  { key: 'recovery',   label: 'Recovery' },
  { key: 'gstr1',      label: 'GSTR1 Report' },
]

const activeTab = ref('commission')
const selectedOutlet = ref('')
const restaurantList = ref([])
const years = [2024, 2025, 2026, 2027, 2028]
const months = [
  { value: 1, label: 'January' }, { value: 2, label: 'February' }, { value: 3, label: 'March' },
  { value: 4, label: 'April' }, { value: 5, label: 'May' }, { value: 6, label: 'June' },
  { value: 7, label: 'July' }, { value: 8, label: 'August' }, { value: 9, label: 'September' },
  { value: 10, label: 'October' }, { value: 11, label: 'November' }, { value: 12, label: 'December' },
]

const filters = ref({
  year: new Date().getFullYear(),
  month: new Date().getMonth() + 1,
})

const items = ref([])
const loading = ref(true)
const error = ref(null)
const dlBusy = ref(false)

const showGenModal = ref(false)
const genForm = ref({ restaurant_id: '', type: 'commission', invoice_number: '', period_start: '', period_end: '' })
const genBusy = ref(false)
const genError = ref('')

const showEditModal = ref(false)
const editInvId = ref(null)
const editInvNumber = ref('')
const editBusy = ref(false)

const summary = computed(() => {
  return {
    invoices: items.value.length,
    taxable: items.value.reduce((s, i) => s + (+i.amount || 0), 0).toFixed(2),
    cgst: items.value.reduce((s, i) => s + (+i.cgst_amount || 0), 0).toFixed(2),
    sgst: items.value.reduce((s, i) => s + (+i.sgst_amount || 0), 0).toFixed(2),
    total: items.value.reduce((s, i) => s + (+i.total_amount || 0), 0).toFixed(2),
  }
})

const fmtDate = (d) => {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
}

const fetchData = async () => {
  loading.value = true; error.value = null
  try {
    if (activeTab.value === 'gstr1') {
      items.value = []
      loading.value = false
      return
    }
    const params = { type: activeTab.value, year: filters.value.year, month: filters.value.month, per_page: 100 }
    if (selectedOutlet.value) params.restaurant_id = selectedOutlet.value
    const data = await fetchInvoiceList(params)
    items.value = data.data || []
  } catch (e) {
    error.value = e.message || 'Failed to load'
    items.value = []
  } finally {
    loading.value = false
  }
}

const fetchRestaurants = async () => {
  try {
    const list = await listGstRestaurants()
    restaurantList.value = list || []
  } catch { restaurantList.value = [] }
}

// Generate
const openGenerateModal = () => {
  genForm.value = { restaurant_id: '', type: 'commission', invoice_number: '', period_start: '', period_end: '' }
  genError.value = ''
  showGenModal.value = true
}

const handleGenerate = async () => {
  genBusy.value = true; genError.value = ''
  try {
    await generateInvoiceRequest({
      restaurant_id: genForm.value.restaurant_id,
      type: genForm.value.type,
      invoice_number: genForm.value.invoice_number || undefined,
      period_start: genForm.value.period_start,
      period_end: genForm.value.period_end,
    })
    showGenModal.value = false
    activeTab.value = genForm.value.type
    await fetchData()
  } catch (e) {
    genError.value = e?.message || e?.error || 'Failed'
  } finally { genBusy.value = false }
}

// Edit invoice number
const openEditModal = (inv) => {
  editInvId.value = inv.id
  editInvNumber.value = inv.invoice_number
  showEditModal.value = true
}

const handleEditInvoice = async () => {
  editBusy.value = true
  try {
    const token = localStorage.getItem('admin_token')
    const res = await fetch(`https://catalog.utterlazy.com/api/admin/invoices/${editInvId.value}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Authorization': `Bearer ${token}` },
      body: JSON.stringify({ invoice_number: editInvNumber.value })
    })
    if (!res.ok) throw await res.json()
    showEditModal.value = false
    await fetchData()
  } catch (e) {
    alert('Failed to update invoice number')
  } finally { editBusy.value = false }
}

// Actions
const approveInvoiceFn = async (id) => {
  try { await approveInvoiceById(id); await fetchData() } catch (e) { alert('Failed') }
}
const markPaidFn = async (id) => {
  try { await markInvoicePaidById(id); await fetchData() } catch (e) { alert('Failed') }
}
const cancelInvoiceFn = async (id) => {
  if (!confirm('Cancel this invoice?')) return
  try { await cancelInvoiceById(id); await fetchData() } catch (e) { alert('Failed') }
}
const downloadInvoiceFn = async (inv) => {
  dlBusy.value = true
  try { await downloadInvoicePdf(inv.id) } catch (e) { alert('Download failed') }
  finally { dlBusy.value = false }
}
const downloadBundleFn = async () => {
  if (!selectedOutlet.value) { alert('Select a restaurant first'); return }
  dlBusy.value = true
  try { await downloadInvoiceBundle(selectedOutlet.value, activeTab.value, filters.value.year, filters.value.month) } catch (e) { alert('Download failed') }
  finally { dlBusy.value = false }
}
const downloadGstr1Fn = async () => {
  if (!selectedOutlet.value) { alert('Select a restaurant first'); return }
  dlBusy.value = true
  try { await downloadGstr1Xlsx(selectedOutlet.value, filters.value.year, filters.value.month) } catch (e) { alert('Download failed') }
  finally { dlBusy.value = false }
}

onMounted(() => {
  fetchRestaurants()
  fetchData()
})
</script>
