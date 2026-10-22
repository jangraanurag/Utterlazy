<script setup>
import { ref, onMounted, watch } from 'vue'
import { getEarnings, downloadGstReport } from '../api'
import { useAuth } from '../auth'

const { restaurant } = useAuth()
const period = ref('today')
const data = ref(null)
const loading = ref(true)

const reportYear = ref(new Date().getFullYear())
const reportMonth = ref(new Date().getMonth() + 1)
const downloading = ref(false)

const periods = [
  { key: 'today', label: 'TODAY' },
  { key: 'week', label: 'WEEK' },
  { key: 'month', label: 'MONTH' },
]

async function fetchEarnings() {
  loading.value = true
  try { data.value = await getEarnings(period.value) }
  catch (e) { console.error(e) }
  loading.value = false
}

onMounted(fetchEarnings)
watch(period, fetchEarnings)

async function handleDownload() {
  downloading.value = true
  try {
    const filename = `GST_${restaurant.value?.name || 'Restaurant'}_${reportYear.value}_${String(reportMonth.value).padStart(2, '0')}.xlsx`
    await downloadGstReport(reportYear.value, reportMonth.value, filename)
  } catch (e) { alert('Download failed: ' + e.message) }
  finally { downloading.value = false }
}
</script>

<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Earnings</h1>
        <p class="font-space" style="color: var(--text-secondary);">
          GSTIN: {{ restaurant?.gst_number || 'NOT CONFIGURED' }}
        </p>
      </div>
      <button class="btn btn-secondary" @click="window.print()">Export</button>
    </div>

    <div class="flex gap-4 mb-8">
      <button v-for="p in periods" :key="p.key" class="btn btn-sm" :class="period === p.key ? 'btn-primary' : 'btn-secondary'" @click="period = p.key" style="min-width: 90px;">
        {{ p.label }}
      </button>
    </div>

    <div v-if="loading" class="text-center" style="padding: 100px;">
      <div class="spinner" style="margin: 0 auto;"></div>
    </div>
    <div v-else-if="!data" class="card text-center" style="padding: 100px;">
      <p class="font-space text-muted">No data found</p>
    </div>
    <div v-else>
      <div class="grid-kpi" style="margin-bottom: 32px;">
        <div class="card kpi-card">
          <span class="kpi-label" style="color: var(--success);">GROSS</span>
          <div class="kpi-value">₹{{ data.gross_revenue?.toLocaleString('en-IN') }}</div>
          <span style="font-size: 0.7rem; color: var(--text-muted);">{{ data.total_orders }} orders</span>
        </div>
        <div class="card kpi-card">
          <span class="kpi-label" style="color: var(--danger);">COMMISSION</span>
          <div class="kpi-value">-₹{{ data.commission_amount?.toLocaleString('en-IN') }}</div>
          <span style="font-size: 0.7rem; color: var(--text-muted);">@ {{ data.commission_rate }}%</span>
        </div>
        <div class="card kpi-card">
          <span class="kpi-label" style="color: var(--sovereign-orange);">GST</span>
          <div class="kpi-value">₹{{ Math.round(data.gross_revenue * 0.05).toLocaleString('en-IN') }}</div>
          <span style="font-size: 0.7rem; color: var(--text-muted);">Output tax</span>
        </div>
        <div class="card kpi-card">
          <span class="kpi-label" style="color: var(--sovereign-teal);">NET</span>
          <div class="kpi-value">₹{{ data.net_payout?.toLocaleString('en-IN') }}</div>
          <span style="font-size: 0.7rem; color: var(--success); font-weight: 700;">Ready</span>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px; margin-bottom: 32px;">
        <div class="card">
          <h3 class="font-space mb-6" style="font-size: 1.2rem; font-weight: 700;">Tax Reconciliation</h3>
          <div style="display: grid; gap: 16px;">
            <div class="flex-between" style="border-bottom: 1px solid var(--glass-edge); padding-bottom: 8px;">
              <span class="text-sm">Output GST</span>
              <span class="font-space font-bold">₹{{ (data.gross_revenue * 0.05).toFixed(2) }}</span>
            </div>
            <div class="flex-between" style="border-bottom: 1px solid var(--glass-edge); padding-bottom: 8px;">
              <span class="text-sm">TCS (1%)</span>
              <span class="font-space font-bold" style="color: var(--danger);">-₹{{ (data.gross_revenue * 0.01).toFixed(2) }}</span>
            </div>
            <div class="flex-between" style="border-bottom: 1px solid var(--glass-edge); padding-bottom: 8px;">
              <span class="text-sm">TDS (1%)</span>
              <span class="font-space font-bold" style="color: var(--danger);">-₹{{ (data.gross_revenue * 0.01).toFixed(2) }}</span>
            </div>
            <div class="flex-between">
              <span class="text-sm font-bold">Taxable Value</span>
              <span class="font-space font-bold" style="color: var(--sovereign-orange);">₹{{ (data.gross_revenue - data.gross_revenue * 0.02).toFixed(2) }}</span>
            </div>
          </div>
        </div>

        <div class="card">
          <h3 class="font-space mb-6" style="font-size: 1.2rem; font-weight: 700;">Settlement</h3>
          <div class="text-center" style="padding: 16px 0;">
            <div class="text-sm text-muted mb-2">Bank Account</div>
            <div class="font-space font-bold tracking-widest">
              {{ restaurant?.bank_account_number ? `•••• ${restaurant.bank_account_number.slice(-4)}` : 'NOT CONFIGURED' }}
            </div>
            <div class="font-space text-xs mt-1" style="color: var(--sovereign-teal);">
              {{ restaurant?.bank_name?.toUpperCase() || 'BANK' }} | {{ restaurant?.bank_ifsc?.toUpperCase() || 'IFSC' }}
            </div>
          </div>
        </div>

        <div class="card">
          <h3 class="font-space mb-6" style="font-size: 1.2rem; font-weight: 700;">GST Report</h3>
          <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 24px;">
            Download monthly 7-sheet GST compliance report.
          </p>
          <div class="flex gap-3 mb-6">
            <select v-model.number="reportYear" class="form-input" style="flex: 1;">
              <option v-for="y in Array.from({length: 5}, (_, i) => new Date().getFullYear() - 2 + i)" :key="y" :value="y">{{ y }}</option>
            </select>
            <select v-model.number="reportMonth" class="form-input" style="flex: 1;">
              <option v-for="(m, i) in ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']" :key="i+1" :value="i+1">{{ m }}</option>
            </select>
          </div>
          <button class="btn btn-sm btn-primary w-full" @click="handleDownload" :disabled="downloading">
            {{ downloading ? 'Generating...' : 'Download Excel' }}
          </button>
        </div>
      </div>

      <div v-if="data.daily_breakdown?.length" class="card">
        <h3 class="font-space mb-6" style="font-size: 1.2rem; font-weight: 700;">Daily Breakdown</h3>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr><th>Date</th><th class="text-center">Orders</th><th class="text-right">Gross</th><th class="text-right">Tax</th><th class="text-right">Net</th></tr>
            </thead>
            <tbody>
              <tr v-for="(day, i) in data.daily_breakdown" :key="i">
                <td class="font-space" style="font-weight: 600;">{{ new Date(day.date).toLocaleDateString('en-IN', { weekday: 'short', day: 'numeric', month: 'short' }).toUpperCase() }}</td>
                <td class="text-center">{{ day.orders }}</td>
                <td class="text-right font-space" style="font-weight: 700;">{{ parseFloat(day.revenue).toLocaleString('en-IN') }}</td>
                <td class="text-right font-space" style="color: var(--sovereign-orange);">{{ (day.revenue * 0.05).toFixed(2) }}</td>
                <td class="text-right font-space" style="color: var(--sovereign-teal); font-weight: 700;">{{ (day.revenue * 0.9).toLocaleString('en-IN') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>
