<template>
  <div>
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-black text-white flex items-center gap-3">
          <span class="w-10 h-10 bg-gradient-to-br from-violet-400 to-purple-600 rounded-xl flex items-center justify-center text-lg">📊</span>
          Reporting
        </h1>
        <p class="text-white/50 text-sm mt-1">Live tracking & business reports</p>
      </div>
    </div>

    <!-- Outlet Filter -->
    <div class="flex gap-4 mb-6 flex-wrap">
      <select v-model="selectedOutlet" @change="fetchLiveTracking"
        class="bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none min-w-[200px]">
        <option value="">Select Restaurant...</option>
        <option v-for="r in restaurantList" :key="r.id" :value="r.id">{{ r.name }}</option>
      </select>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 mb-6 border-b border-white/10">
      <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key; tabActivated()"
        :class="activeTab === tab.key ? 'border-b-2 border-secondary text-secondary' : 'text-white/40 hover:text-white/70'"
        class="px-4 py-2.5 text-sm font-bold uppercase tracking-wider transition-colors">
        {{ tab.label }}
      </button>
    </div>

    <div v-if="!selectedOutlet" class="text-center py-12 text-white/30">Select a restaurant to view reports</div>
    <div v-else-if="loading" class="text-center py-12 text-white/50">Loading...</div>
    <div v-else-if="error" class="text-center py-12 text-red-500">{{ error }}</div>

    <!-- ════════ LIVE TRACKING TAB ════════ -->
    <template v-else-if="activeTab === 'live'">
      <div v-for="section in liveData" :key="section.category" class="mb-8">
        <h2 class="text-white font-bold text-lg mb-4 flex items-center gap-2">
          <span class="w-6 h-6 rounded-lg bg-white/10 flex items-center justify-center text-xs">{{ sectionIcon(section.category) }}</span>
          {{ section.category }}
        </h2>

        <!-- Dish trends special rendering -->
        <template v-if="section.category === 'Dish trends'">
          <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <div v-for="dish in section.metrics" :key="dish.menu_item_id"
              class="bg-white/5 border border-white/10 rounded-xl p-4">
              <div class="flex items-center justify-between mb-2">
                <p class="text-white font-bold text-sm">{{ dish.name }}</p>
                <span :class="dish.trend === 'up' ? 'text-green-400' : dish.trend === 'down' ? 'text-red-400' : 'text-white/40'"
                  class="text-xs font-bold">{{ trendArrow(dish.trend) }}</span>
              </div>
              <div class="flex gap-3 text-xs text-white/50 mb-2">
                <span>Orders: <strong class="text-white">{{ dish.dataPoints[dish.dataPoints.length - 1]?.orders || 0 }}</strong></span>
                <span>Revenue: <strong class="text-white">₹{{ dish.dataPoints[dish.dataPoints.length - 1]?.revenue || 0 }}</strong></span>
              </div>
              <SimpleSparkline :data="dish.dataPoints.map(d => d.orders)" :trend="dish.trend" />
            </div>
          </div>
        </template>

        <!-- Regular metrics -->
        <template v-else>
          <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            <div v-for="metric in section.metrics" :key="metric.name"
              class="bg-white/5 border border-white/10 rounded-xl p-4">
              <div class="flex items-center justify-between mb-1">
                <p class="text-white/60 text-xs uppercase tracking-wider">{{ metric.name }}</p>
                <span :class="metric.trend === 'up' ? 'text-green-400' : metric.trend === 'down' ? 'text-red-400' : 'text-white/40'"
                  class="text-sm">{{ trendArrow(metric.trend) }}</span>
              </div>
              <p class="text-white text-2xl font-black mb-2">{{ formatValue(metric) }}</p>
              <SimpleSparkline :data="metric.dataPoints.map(d => d.value)" :trend="metric.trend" />
              <div class="flex justify-between text-[10px] text-white/30 mt-1">
                <span>{{ metric.dataPoints[0]?.date }}</span>
                <span>{{ metric.dataPoints[metric.dataPoints.length - 1]?.date }}</span>
              </div>
            </div>
          </div>
        </template>
      </div>
    </template>

    <!-- ════════ BUSINESS REPORTS TAB ════════ -->
    <template v-else-if="activeTab === 'reports'">
      <div class="flex flex-wrap gap-3 mb-6 items-center">
        <select v-model="reportGranularity" @change="fetchTrendTable"
          class="bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm focus:outline-none">
          <option value="daily">Daily</option>
          <option value="weekly">Weekly</option>
          <option value="monthly">Monthly</option>
        </select>

        <input type="date" v-model="reportStart" @change="fetchTrendTable"
          class="bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm focus:outline-none" />

        <span class="text-white/30">→</span>

        <input type="date" v-model="reportEnd" @change="fetchTrendTable"
          class="bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm focus:outline-none" />

        <button @click="showExportModal = true"
          class="bg-gradient-to-r from-amber-500 to-orange-600 text-white font-bold px-4 py-2 rounded-xl flex items-center gap-2 hover:opacity-90 text-sm ml-auto">
          <span class="material-symbols-outlined text-[18px]">download</span>
          Generate Report
        </button>
      </div>

      <!-- Trend Table -->
      <div class="bg-white/5 border border-white/10 rounded-2xl overflow-hidden overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-white/10 text-white/40 text-xs uppercase tracking-wider">
              <th class="text-left p-3 min-w-[180px]">Metric</th>
              <th class="text-left p-3 min-w-[80px]">Sparkline</th>
              <th v-for="p in trendPeriods" :key="p.label" class="text-right p-3 min-w-[100px]">{{ p.label }}</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="row in trendRows" :key="row.category + row.metric">
              <tr v-if="showCategoryHeader(row)" class="bg-white/5">
                <td colspan="20" class="p-2 text-white/40 text-[10px] uppercase tracking-widest font-bold">{{ row.category }}</td>
              </tr>
              <tr class="border-b border-white/5 hover:bg-white/5 transition-colors">
                <td class="p-3 text-white text-sm">{{ row.metric }}</td>
                <td class="p-3">
                  <MiniSparkline :data="row.values" class="w-20 h-6" />
                </td>
                <td v-for="(val, vi) in row.values" :key="vi" class="p-3 text-right text-white/80 text-xs font-mono">
                  {{ formatTrendValue(val) }}
                </td>
              </tr>
            </template>
            <tr v-if="trendRows.length === 0">
              <td colspan="20" class="p-12 text-center text-white/30">No data for this period. Run aggregation first.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- ════════ EXPORT MODAL ════════ -->
    <div v-if="showExportModal" class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center" @click.self="showExportModal = false">
      <div class="bg-surface border border-white/10 rounded-2xl p-6 w-full max-w-md mx-4">
        <h2 class="text-xl font-bold text-white mb-6">Generate Report</h2>

        <div class="space-y-4">
          <div>
            <label class="text-white/60 text-xs uppercase font-bold mb-1 block">Report View</label>
            <select v-model="exportType"
              class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm">
              <option value="detailed">Detailed Report</option>
              <option value="item_sales">Item Sales Report</option>
            </select>
          </div>

          <div>
            <label class="text-white/60 text-xs uppercase font-bold mb-1 block">Data View</label>
            <select v-model="reportGranularity"
              class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm">
              <option value="daily">Daily</option>
              <option value="weekly">Weekly</option>
              <option value="monthly">Monthly</option>
            </select>
          </div>

          <div>
            <label class="text-white/60 text-xs uppercase font-bold mb-1 block">Duration</label>
            <select v-model="exportDuration" @change="onDurationChange"
              class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm">
              <option value="7">Last 7 days</option>
              <option value="14">Last 14 days</option>
              <option value="30">Last 30 days</option>
              <option value="custom">Custom</option>
            </select>
          </div>

          <div v-if="exportDuration === 'custom'" class="flex gap-4">
            <div class="flex-1">
              <label class="text-white/60 text-xs uppercase font-bold mb-1 block">From</label>
              <input type="date" v-model="reportStart"
                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm" />
            </div>
            <div class="flex-1">
              <label class="text-white/60 text-xs uppercase font-bold mb-1 block">To</label>
              <input type="date" v-model="reportEnd"
                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm" />
            </div>
          </div>

          <p v-if="exportError" class="text-red-400 text-sm">{{ exportError }}</p>

          <button @click="handleExport" :disabled="exportBusy || !selectedOutlet"
            class="w-full bg-gradient-to-r from-amber-500 to-orange-600 text-white font-bold py-3 rounded-xl hover:opacity-90 disabled:opacity-50">
            {{ exportBusy ? 'Downloading...' : 'Download CSV' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import SimpleSparkline from '../components/SimpleSparkline.vue'
import MiniSparkline from '../components/MiniSparkline.vue'

const API_BASE = 'https://catalog.utterlazy.com/api/admin'
const token = () => localStorage.getItem('admin_token')
const headers = () => ({ 'Content-Type': 'application/json', 'Accept': 'application/json', 'Authorization': `Bearer ${token()}` })

const tabs = [
  { key: 'live', label: 'Live Tracking' },
  { key: 'reports', label: 'Business Reports' },
]

const activeTab = ref('live')
const selectedOutlet = ref('')
const restaurantList = ref([])
const loading = ref(false)
const error = ref(null)

// Live Tracking
const liveData = ref([])

// Business Reports
const reportGranularity = ref('daily')
const reportStart = ref('')
const reportEnd = ref('')
const trendRows = ref([])
const trendPeriods = ref([])

// Export
const showExportModal = ref(false)
const exportType = ref('detailed')
const exportDuration = ref('7')
const exportBusy = ref(false)
const exportError = ref('')

const sectionIcon = (cat) => {
  const icons = { 'Sales': '💰', 'Customer experience': '⭐', 'Customer funnel': '🔁', 'Ads': '📢', 'Offers': '🏷️', 'Dish trends': '🍽️' }
  return icons[cat] || '📊'
}

const trendArrow = (t) => t === 'up' ? '↑' : t === 'down' ? '↓' : '→'

const formatValue = (metric) => {
  const last = metric.dataPoints[metric.dataPoints.length - 1]?.value
  if (last === undefined || last === null) return '—'
  if (metric.name.includes('Rs') || metric.name.includes('spend') || metric.name.includes('Sales')) return `₹${Number(last).toLocaleString('en-IN', { maximumFractionDigits: 2 })}`
  if (metric.name.includes('%') || metric.name.includes('rating')) return last
  if (metric.name.includes('CTR') || metric.name.includes('ROI')) return `${last}%`
  if (Number.isInteger(last)) return last.toLocaleString('en-IN')
  return Number(last).toLocaleString('en-IN', { maximumFractionDigits: 1 })
}

const formatTrendValue = (val) => {
  if (val === 0 || val === '0') return '0'
  if (!val) return '—'
  const n = parseFloat(val)
  if (n >= 1000) return `₹${(n / 1000).toFixed(1)}K`
  return n.toFixed(1)
}

let lastCategory = ''
const showCategoryHeader = (row) => {
  if (row.category !== lastCategory) {
    lastCategory = row.category
    return true
  }
  return false
}

const onDurationChange = () => {
  if (exportDuration.value !== 'custom') {
    const days = parseInt(exportDuration.value)
    const end = new Date()
    const start = new Date()
    start.setDate(start.getDate() - days)
    reportStart.value = start.toISOString().split('T')[0]
    reportEnd.value = end.toISOString().split('T')[0]
    fetchTrendTable()
  }
}

const tabActivated = () => {
  lastCategory = ''
  if (activeTab.value === 'live') fetchLiveTracking()
  else if (activeTab.value === 'reports') fetchTrendTable()
}

const fetchRestaurants = async () => {
  try {
    const res = await fetch(`${API_BASE}/reporting/restaurants`, { headers: headers() })
    const json = await res.json()
    restaurantList.value = json.restaurants || json.data || []
  } catch { restaurantList.value = [] }
}

const fetchLiveTracking = async () => {
  if (!selectedOutlet.value) return
  loading.value = true; error.value = null
  try {
    const res = await fetch(`${API_BASE}/reporting/live-tracking?restaurant_id=${selectedOutlet.value}`, { headers: headers() })
    const json = await res.json()
    if (!res.ok) throw new Error(json.message || 'Failed')
    liveData.value = json.data || []
  } catch (e) {
    error.value = e.message
    liveData.value = []
  } finally { loading.value = false }
}

const fetchTrendTable = async () => {
  if (!selectedOutlet.value) return
  loading.value = true; error.value = null
  try {
    const qs = new URLSearchParams({
      restaurant_id: selectedOutlet.value,
      granularity: reportGranularity.value,
      start: reportStart.value,
      end: reportEnd.value,
    })
    const res = await fetch(`${API_BASE}/reporting/trend-table?${qs}`, { headers: headers() })
    const json = await res.json()
    if (!res.ok) throw new Error('Failed')
    trendPeriods.value = json.periods || []
    trendRows.value = json.rows || []
  } catch (e) {
    error.value = e.message
    trendRows.value = []
    trendPeriods.value = []
  } finally { loading.value = false }
}

const handleExport = async () => {
  exportBusy.value = true; exportError.value = ''
  try {
    const qs = new URLSearchParams({
      restaurant_id: selectedOutlet.value,
      type: exportType.value,
      granularity: reportGranularity.value,
      start: reportStart.value,
      end: reportEnd.value,
    })
    const res = await fetch(`${API_BASE}/reporting/export?${qs}`, { headers: { 'Authorization': `Bearer ${token()}` } })
    if (!res.ok) throw new Error('Export failed')
    const blob = await res.blob()
    const link = document.createElement('a')
    link.href = URL.createObjectURL(blob)
    const typeLabel = exportType.value === 'item_sales' ? 'item_sales' : 'detailed'
    link.download = `${typeLabel}_report_${selectedOutlet.value.slice(0, 8)}.csv`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    URL.revokeObjectURL(link.href)
    showExportModal.value = false
  } catch (e) {
    exportError.value = e.message || 'Download failed'
  } finally { exportBusy.value = false }
}

// Set default date range
const endDate = new Date()
const startDate = new Date()
startDate.setDate(startDate.getDate() - 30)
reportStart.value = startDate.toISOString().split('T')[0]
reportEnd.value = endDate.toISOString().split('T')[0]

onMounted(() => { fetchRestaurants() })
</script>

<style scoped>
.relative {
  position: relative;
}
</style>
