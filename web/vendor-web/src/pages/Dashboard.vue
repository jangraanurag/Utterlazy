<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useAuth } from '../auth'
import { getActiveOrders, acceptOrder, updateOrderStatus, getReliability } from '../api'

const { restaurant, stats, loadProfile } = useAuth()
const orders = ref([])
const counts = ref({})
const loading = ref(true)
const reliability = ref(null)
let interval

async function fetchOrders() {
  try {
    const data = await getActiveOrders()
    orders.value = data.orders || []
    counts.value = data.counts || {}
  } catch (e) { console.error(e) }
  loading.value = false
}

async function fetchReliability() {
  try { reliability.value = await getReliability() } catch (e) { /* non-blocking */ }
}

onMounted(() => {
  fetchOrders()
  fetchReliability()
  interval = setInterval(fetchOrders, 15000)
})
onUnmounted(() => clearInterval(interval))

async function handleAccept(id) {
  try { await acceptOrder(id); fetchOrders(); loadProfile() }
  catch (e) { alert(e.response?.data?.error || 'Failed') }
}

async function handleProgress(id, status) {
  try { await updateOrderStatus(id, status); fetchOrders(); loadProfile() }
  catch (e) { alert(e.response?.data?.error || 'Failed') }
}

const kpis = () => [
  { title: 'Daily Revenue', value: `₹${stats.value?.today_revenue || 0}`, label: 'REVENUE', color: 'var(--sovereign-orange)' },
  { title: 'Active Orders', value: counts.value.total || 0, label: 'ACTIVE', color: 'var(--sovereign-teal)' },
  { title: 'Completed', value: stats.value?.today_completed || 0, label: 'DONE', color: 'var(--sovereign-gold)' },
  { title: 'Rating', value: stats.value?.avg_rating || '–', label: 'RATING', color: 'var(--success)' },
]

const statusBadge = (s) => ({
  pending: 'badge-warning', confirmed: 'badge-info', preparing: 'badge-warning',
  ready_for_pickup: 'badge-success', out_for_delivery: 'badge-info',
}[s] || 'badge-info')

const statusLabel = (s) => s?.toUpperCase().replace(/_/g, ' ')

const formatTime = (d) => {
  const mins = Math.round((Date.now() - new Date(d).getTime()) / 60000)
  return mins < 1 ? 'JUST NOW' : `${mins}M AGO`
}
</script>

<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="font-space" style="color: var(--text-secondary); letter-spacing: 1px;">
          STATUS: {{ restaurant?.is_accepting_orders ? 'ACTIVE' : 'STANDBY' }}
        </p>
      </div>
      <button class="btn btn-secondary" @click="fetchOrders">REFRESH</button>
    </div>

    <div class="grid-kpi">
      <div v-for="(kpi, i) in kpis()" :key="i" class="card kpi-card">
        <span class="kpi-label" :style="{ color: kpi.color }">{{ kpi.label }}</span>
        <div class="kpi-value">{{ kpi.value }}</div>
        <span style="font-size: 0.8rem; color: var(--text-muted); margin-top: 8px;">{{ kpi.title }}</span>
      </div>
    </div>

    <div v-if="reliability" class="card mb-4" style="padding: 20px; display: flex; gap: 32px; flex-wrap: wrap;">
      <div>
        <span class="kpi-label">MISS RATE (30D)</span>
        <div class="font-space" style="font-size: 1.4rem; font-weight: 700;"
          :style="{ color: reliability.miss_rate_percent > 5 ? 'var(--danger, #e53935)' : 'var(--success)' }">
          {{ reliability.miss_rate_percent }}%
        </div>
      </div>
      <div>
        <span class="kpi-label">TIMEOUTS</span>
        <div class="font-space" style="font-size: 1.4rem; font-weight: 700;">{{ reliability.timeouts }}</div>
      </div>
      <div>
        <span class="kpi-label">REJECTS</span>
        <div class="font-space" style="font-size: 1.4rem; font-weight: 700;">{{ reliability.rejects }}</div>
      </div>
      <div>
        <span class="kpi-label">LATE PREP</span>
        <div class="font-space" style="font-size: 1.4rem; font-weight: 700;">{{ reliability.late_prep }}</div>
      </div>
      <div style="opacity: .6; font-size: .8rem; align-self: flex-end;">
        Miss rate = (timeouts + rejects) / total orders. Ads never change this.
      </div>
    </div>

    <div class="flex-between mb-4">
      <h2 class="font-space" style="font-size: 1.5rem; font-weight: 700;">
        ACTIVE ORDERS
        <span v-if="counts.total > 0" class="badge badge-info" style="margin-left: 16px;">{{ counts.total }}</span>
      </h2>
    </div>

    <div v-if="loading" class="text-center" style="padding: 80px;">
      <div class="spinner" style="margin: 0 auto;"></div>
      <p class="font-space mt-4">Loading...</p>
    </div>
    <div v-else-if="orders.length === 0" class="card text-center" style="padding: 100px;">
      <div style="font-size: 4rem; margin-bottom: 24px; opacity: 0.2;">📦</div>
      <p class="font-space" style="color: var(--text-muted); font-size: 1.2rem;">No active orders</p>
    </div>
    <div v-else style="display: grid; gap: 20px;">
      <div v-for="order in orders" :key="order.id" class="order-card">
        <div style="flex: 1;">
          <div class="flex gap-4 items-center mb-2">
            <span class="font-space" style="font-weight: 700; font-size: 1.2rem;">#{{ order.id?.slice(0, 8) }}</span>
            <span class="badge" :class="statusBadge(order.status)">{{ statusLabel(order.status) }}</span>
          </div>
          <div class="font-space" style="font-size: 0.9rem; color: var(--text-secondary);">
            {{ order.user?.name?.toUpperCase() || 'CUSTOMER' }} • {{ order.payment_mode?.toUpperCase() }} • {{ formatTime(order.created_at) }}
          </div>
          <div class="mt-2" style="color: var(--text-muted); font-size: 0.85rem;">
            {{ order.items?.map(item => `${item.quantity}× ${item.menu_item?.name}`).join(' | ') }}
          </div>
        </div>
        <div class="text-right" style="min-width: 140px;">
          <div class="font-space mb-4" style="font-size: 1.5rem; font-weight: 700; color: var(--sovereign-orange);">
            ₹{{ order.subtotal }}
          </div>
          <div class="flex gap-2 justify-end">
            <button v-if="order.status === 'pending'" class="btn btn-sm btn-primary" @click="handleAccept(order.id)">ACCEPT</button>
            <button v-if="order.status === 'confirmed'" class="btn btn-sm btn-primary" @click="handleProgress(order.id, 'preparing')">PREPARE</button>
            <button v-if="order.status === 'preparing'" class="btn btn-sm" style="background: var(--success); color: white;" @click="handleProgress(order.id, 'ready_for_pickup')">READY</button>
            <span v-if="order.status === 'ready_for_pickup'" class="font-space" style="color: var(--sovereign-teal); font-weight: 700; font-size: 0.8rem;">
              WAITING FOR RIDER...
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
