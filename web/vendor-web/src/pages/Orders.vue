<script setup>
import { ref, onMounted, watch } from 'vue'
import { getAllOrders, acceptOrder, rejectOrder, updateOrderStatus } from '../api'

const orders = ref([])
const tab = ref('all')
const loading = ref(true)
const page = ref(1)

const tabs = [
  { key: 'all', label: 'All' },
  { key: 'pending', label: 'New' },
  { key: 'confirmed', label: 'Confirmed' },
  { key: 'preparing', label: 'Preparing' },
  { key: 'ready_for_pickup', label: 'Ready' },
  { key: 'delivered', label: 'Delivered' },
  { key: 'cancelled', label: 'Cancelled' },
]

async function fetchOrders() {
  loading.value = true
  try {
    const params = { page: page.value }
    if (tab.value !== 'all') params.status = tab.value
    const data = await getAllOrders(params)
    orders.value = data.orders?.data || []
  } catch (e) { console.error(e) }
  loading.value = false
}

onMounted(fetchOrders)
watch([tab, page], fetchOrders)

async function handleAction(fn, ...args) {
  try { await fn(...args); fetchOrders() }
  catch (e) { alert(e.response?.data?.error || 'Failed') }
}

const statusBadge = (s) => ({
  pending: 'badge-warning', confirmed: 'badge-info', preparing: 'badge-warning',
  ready_for_pickup: 'badge-success', out_for_delivery: 'badge-info',
  delivered: 'badge-success', cancelled: 'badge-danger',
}[s] || 'badge-info')
</script>

<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Orders</h1>
        <p class="page-subtitle">Track and manage all incoming orders</p>
      </div>
      <button class="btn btn-sm btn-secondary" @click="fetchOrders">Refresh</button>
    </div>

    <div class="tabs">
      <button v-for="t in tabs" :key="t.key" class="tab" :class="{ active: tab === t.key }" @click="tab = t.key; page = 1">
        {{ t.label }}
      </button>
    </div>

    <div v-if="loading" class="text-center text-muted" style="padding: 60px;">Loading...</div>
    <div v-else-if="orders.length === 0" class="card text-center" style="padding: 60px;">
      <div style="font-size: 3rem; margin-bottom: 12px;">📦</div>
      <p class="text-muted">No orders in this category</p>
    </div>
    <div v-else class="table-wrapper">
      <table>
        <thead>
          <tr>
            <th>Order ID</th><th>Customer</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th>Time</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="order in orders" :key="order.id">
            <td><span class="font-space" style="font-weight: 700;">#{{ order.id?.slice(0, 8) }}</span></td>
            <td>{{ order.user?.name || '–' }}</td>
            <td style="max-width: 200px; color: var(--text-muted); font-size: 0.82rem;">
              {{ order.items?.map(i => `${i.quantity}× ${i.menu_item?.name}`).join(', ') }}
            </td>
            <td style="font-weight: 700; color: var(--success);">₹{{ order.subtotal }}</td>
            <td><span class="badge" :class="order.payment_mode === 'cod' ? 'badge-warning' : 'badge-success'">{{ order.payment_mode?.toUpperCase() }}</span></td>
            <td><span class="badge" :class="statusBadge(order.status)">{{ order.status?.replace(/_/g, ' ') }}</span></td>
            <td style="color: var(--text-muted); font-size: 0.82rem;">{{ new Date(order.created_at).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' }) }}</td>
            <td>
              <div class="flex gap-2">
                <template v-if="order.status === 'pending'">
                  <button class="btn btn-xs" style="background: var(--danger); color: white;" @click="handleAction(rejectOrder, order.id, 'Rejected')">✗</button>
                  <button class="btn btn-xs" style="background: var(--success); color: white;" @click="handleAction(acceptOrder, order.id)">✓</button>
                </template>
                <button v-if="order.status === 'confirmed'" class="btn btn-xs btn-primary" @click="handleAction(updateOrderStatus, order.id, 'preparing')">Prepare</button>
                <button v-if="order.status === 'preparing'" class="btn btn-xs" style="background: var(--success); color: white;" @click="handleAction(updateOrderStatus, order.id, 'ready_for_pickup')">Ready</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
