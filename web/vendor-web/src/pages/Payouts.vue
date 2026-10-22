<script setup>
import { ref, onMounted } from 'vue'
import { getPayoutCycles, getPayoutDetail } from '../api'

const cycles = ref([])
const loading = ref(true)
const selectedCycle = ref(null)
const showDetail = ref(false)
const page = ref(1)
const totalPages = ref(1)

async function fetchCycles() {
  loading.value = true
  try {
    const data = await getPayoutCycles({ page: page.value })
    cycles.value = data.cycles || data.data || []
    totalPages.value = data.last_page || 1
  } catch (e) { cycles.value = [] }
  loading.value = false
}

async function openDetail(cycle) {
  try {
    const data = await getPayoutDetail(cycle.id)
    selectedCycle.value = data.cycle || data
    showDetail.value = true
  } catch (e) { alert('Failed to load details') }
}

function statusBadge(s) {
  const m = { generated: 'badge-warning', approved: 'badge-info', paid: 'badge-success' }
  return m[s] || 'badge-info'
}

onMounted(fetchCycles)
</script>

<template>
  <div>
    <div class="page-header">
      <h1 class="page-title">Payout Cycles</h1>
      <button class="btn btn-secondary" @click="fetchCycles">REFRESH</button>
    </div>

    <div v-if="loading" class="text-center py-16"><div class="spinner" style="margin:0 auto;"></div><p class="mt-4 font-space" style="color:var(--text-muted)">Loading...</p></div>
    <div v-else-if="cycles.length === 0" class="card text-center py-16">
      <div style="font-size:3rem;opacity:0.2;margin-bottom:16px;">💰</div>
      <p class="font-space" style="color:var(--text-muted)">No payout cycles</p>
    </div>
    <div v-else style="display:grid;gap:12px;">
      <div v-for="c in cycles" :key="c.id" class="card" style="cursor:pointer;" @click="openDetail(c)">
        <div class="flex items-center justify-between">
          <div>
            <p class="font-space font-bold">{{ c.period_start?.slice(0,10) }} → {{ c.period_end?.slice(0,10) }}</p>
            <p class="font-space" style="color:var(--text-secondary);font-size:0.85rem;">{{ c.description || `${c.total_orders || 0} orders` }}</p>
          </div>
          <div class="text-right">
            <p class="font-space font-bold" style="color:var(--sovereign-orange);font-size:1.3rem;">₹{{ Number(c.net_amount || c.total_amount || 0).toLocaleString('en-IN') }}</p>
            <span class="badge" :class="statusBadge(c.status)">{{ c.status?.toUpperCase() }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Detail Modal -->
    <div v-if="showDetail && selectedCycle" class="modal-overlay" @click.self="showDetail=false">
      <div class="modal-card">
        <div class="flex justify-between items-center mb-4">
          <h2 class="font-space font-bold" style="font-size:1.2rem;">Cycle Details</h2>
          <button class="btn btn-sm btn-secondary" @click="showDetail=false">✕</button>
        </div>
        <div style="display:grid;gap:12px;">
          <div class="flex justify-between"><span style="color:var(--text-secondary)">Period</span><span>{{ selectedCycle.period_start?.slice(0,10) }} → {{ selectedCycle.period_end?.slice(0,10) }}</span></div>
          <div class="flex justify-between"><span style="color:var(--text-secondary)">Orders</span><span>{{ selectedCycle.total_orders || 0 }}</span></div>
          <div class="flex justify-between"><span style="color:var(--text-secondary)">Gross</span><span>₹{{ Number(selectedCycle.gross_amount || 0).toLocaleString('en-IN') }}</span></div>
          <div class="flex justify-between"><span style="color:var(--text-secondary)">Commission</span><span style="color:var(--error)">-₹{{ Number(selectedCycle.total_commission || 0).toLocaleString('en-IN') }}</span></div>
          <div class="flex justify-between"><span style="color:var(--text-secondary)">Deductions</span><span style="color:var(--error)">-₹{{ Number(selectedCycle.total_deductions || 0).toLocaleString('en-IN') }}</span></div>
          <div class="flex justify-between"><span style="color:var(--text-secondary)">Additions</span><span style="color:var(--success)">+₹{{ Number(selectedCycle.total_additions || 0).toLocaleString('en-IN') }}</span></div>
          <hr style="border-color:var(--border);margin:4px 0;">
          <div class="flex justify-between font-bold"><span>Net Settlement</span><span style="color:var(--sovereign-orange);font-size:1.2rem;">₹{{ Number(selectedCycle.net_amount || 0).toLocaleString('en-IN') }}</span></div>
          <div class="flex justify-between"><span style="color:var(--text-secondary)">Status</span><span class="badge" :class="statusBadge(selectedCycle.status)">{{ selectedCycle.status?.toUpperCase() }}</span></div>
          <div v-if="selectedCycle.utr_number" class="flex justify-between"><span style="color:var(--text-secondary)">UTR</span><span class="font-mono">{{ selectedCycle.utr_number }}</span></div>
        </div>
      </div>
    </div>
  </div>
</template>
