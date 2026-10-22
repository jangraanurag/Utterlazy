<script setup>
import { ref } from 'vue'
import { downloadGstReport } from '../api'

const selectedYear = ref(new Date().getFullYear())
const selectedMonth = ref(new Date().getMonth() + 1)
const dlBusy = ref(false)
const years = [2024, 2025, 2026, 2027, 2028]
const months = [
  { value: 1, label: 'January' }, { value: 2, label: 'February' }, { value: 3, label: 'March' },
  { value: 4, label: 'April' }, { value: 5, label: 'May' }, { value: 6, label: 'June' },
  { value: 7, label: 'July' }, { value: 8, label: 'August' }, { value: 9, label: 'September' },
  { value: 10, label: 'October' }, { value: 11, label: 'November' }, { value: 12, label: 'December' },
]

async function handleDownload() {
  dlBusy.value = true
  try {
    await downloadGstReport(selectedYear.value, selectedMonth.value, `GST_Report_${selectedYear.value}_${selectedMonth.value}.xlsx`)
  } catch (e) { alert('Download failed') }
  dlBusy.value = false
}
</script>

<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">GST Reports</h1>
        <p class="font-space" style="color:var(--text-secondary);font-size:0.85rem;">Download monthly GST reports with order-level breakdown</p>
      </div>
    </div>

    <div class="card" style="max-width:480px;">
      <div style="display:grid;gap:16px;">
        <div>
          <label class="form-label">Year</label>
          <select v-model="selectedYear" class="form-input">
            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>
        <div>
          <label class="form-label">Month</label>
          <select v-model="selectedMonth" class="form-input">
            <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </div>
        <button class="btn btn-primary w-full" @click="handleDownload" :disabled="dlBusy">
          {{ dlBusy ? 'Downloading...' : '⬇ Download GST Report (XLSX)' }}
        </button>
      </div>
    </div>
  </div>
</template>
