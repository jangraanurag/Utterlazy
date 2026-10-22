<template>
  <div>
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-serif font-bold text-on-surface">Order Management</h1>
        <p class="text-on-surface-variant mt-1">Track, manage and progress all orders across the platform.</p>
      </div>
      <button @click="fetchOrders" class="flex items-center gap-2 bg-primary/10 text-primary font-bold px-4 py-2 rounded-lg hover:bg-primary/20 transition-colors text-sm">
        <span class="material-symbols-outlined text-[18px]" :class="loading ? 'animate-spin' : ''">sync</span> Refresh
      </button>
    </div>

    <!-- Status Filter Tabs -->
    <div class="flex gap-2 mb-6 overflow-x-auto pb-2">
      <button v-for="tab in statusTabs" :key="tab.value"
        @click="activeFilter = tab.value; fetchOrders()"
        :class="activeFilter === tab.value
          ? 'bg-primary text-on-primary shadow-md'
          : 'bg-surface-container-lowest text-on-surface border border-outline-variant/30 hover:border-primary/40'"
        class="px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-wider whitespace-nowrap transition-all">
        {{ tab.label }}
        <span v-if="tab.count !== undefined" class="ml-1 opacity-75">({{ tab.count }})</span>
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="py-16 text-center text-on-surface-variant">
      <span class="material-symbols-outlined text-4xl animate-spin mb-4 block">progress_activity</span>
      <p>Loading orders...</p>
    </div>

    <!-- Empty -->
    <div v-else-if="orders.length === 0" class="bg-surface-container-lowest p-12 text-center rounded-xl border border-outline-variant/30 shadow-sm">
      <span class="material-symbols-outlined text-5xl text-primary opacity-50 mb-4 block">inbox</span>
      <h3 class="text-xl font-bold text-on-surface mb-2">No orders found</h3>
      <p class="text-on-surface-variant">No orders matching this filter.</p>
    </div>

    <!-- Orders Table -->
    <div v-else class="bg-surface-container-lowest rounded-xl border border-outline-variant/30 shadow-sm overflow-hidden">
      <table class="w-full text-left">
        <thead class="bg-surface-container-low text-on-surface-variant text-[10px] uppercase font-bold tracking-widest border-b border-outline-variant/30">
          <tr>
            <th class="px-5 py-4">Order</th>
            <th class="px-5 py-4">Customer</th>
            <th class="px-5 py-4">Restaurant</th>
            <th class="px-5 py-4">Amount</th>
            <th class="px-5 py-4">Status</th>
            <th class="px-5 py-4">OTP</th>
            <th class="px-5 py-4">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-outline-variant/15">
          <tr v-for="order in orders" :key="order.id" class="hover:bg-surface-container-low/40 transition-colors">
            <!-- Order ID / Time -->
            <td class="px-5 py-4">
              <div class="font-mono font-bold text-sm text-primary">#{{ order.id.substring(0, 8).toUpperCase() }}</div>
              <div class="text-[10px] text-on-surface-variant mt-1">{{ formatTime(order.created_at) }}</div>
            </td>
            <!-- Customer -->
            <td class="px-5 py-4">
              <div class="text-sm font-bold">{{ order.user?.name || 'Guest' }}</div>
              <div class="text-[10px] text-on-surface-variant">{{ order.user?.phone || order.user?.email || '—' }}</div>
            </td>
            <!-- Restaurant -->
            <td class="px-5 py-4">
              <div class="text-sm font-medium">{{ order.restaurant?.name || 'Unknown' }}</div>
              <div class="text-[10px] text-on-surface-variant">{{ order.items?.length || 0 }} items</div>
            </td>
            <!-- Amount -->
            <td class="px-5 py-4">
              <div class="text-sm font-black">₹{{ parseFloat(order.grand_total).toFixed(0) }}</div>
              <div class="text-[10px] text-on-surface-variant">{{ order.payment_mode || 'prepaid' }}</div>
            </td>
            <!-- Status Badge -->
            <td class="px-5 py-4">
              <span :class="statusBadge(order.status)" class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider">
                {{ statusLabel(order.status) }}
              </span>
            </td>
            <!-- OTP -->
            <td class="px-5 py-4">
              <span v-if="order.delivery_otp && order.status !== 'delivered'" class="font-mono font-bold text-sm text-primary bg-primary/10 px-2 py-1 rounded border border-primary/20">
                {{ order.delivery_otp }}
              </span>
              <span v-else-if="order.status === 'delivered' && order.delivery_otp" class="text-green-600 text-xs font-bold flex items-center gap-1">
                <span class="material-symbols-outlined text-[14px]" style="font-variation-settings: 'FILL' 1;">verified</span> Verified
              </span>
              <span v-else class="text-on-surface-variant text-xs">—</span>
            </td>
            <!-- Actions -->
            <td class="px-5 py-4">
              <div v-if="nextStatus(order.status)" class="flex gap-2">
                <button @click="progressOrder(order.id, nextStatus(order.status))"
                  :disabled="updatingId === order.id"
                  class="bg-primary text-white text-[10px] font-bold px-3 py-1.5 rounded-lg hover:bg-primary/90 transition-colors shadow-sm disabled:opacity-50 flex items-center gap-1">
                  <span v-if="updatingId === order.id" class="material-symbols-outlined text-[14px] animate-spin">sync</span>
                  {{ nextActionLabel(order.status) }}
                </button>
                <button v-if="order.status !== 'delivered' && order.status !== 'cancelled'"
                  @click="progressOrder(order.id, 'cancelled')"
                  :disabled="updatingId === order.id"
                  class="bg-red-50 text-red-600 text-[10px] font-bold px-3 py-1.5 rounded-lg hover:bg-red-100 transition-colors border border-red-200 disabled:opacity-50">
                  Cancel
                </button>
              </div>
              <span v-else class="text-xs text-on-surface-variant italic">Complete</span>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="flex items-center justify-between px-5 py-4 border-t border-outline-variant/20 bg-surface-container-low/50">
        <p class="text-xs text-on-surface-variant">Page {{ currentPage }} of {{ totalPages }}</p>
        <div class="flex gap-2">
          <button @click="currentPage--; fetchOrders()" :disabled="currentPage <= 1"
            class="px-3 py-1 text-xs font-bold rounded border border-outline-variant/30 hover:bg-surface-container transition-colors disabled:opacity-30">Prev</button>
          <button @click="currentPage++; fetchOrders()" :disabled="currentPage >= totalPages"
            class="px-3 py-1 text-xs font-bold rounded border border-outline-variant/30 hover:bg-surface-container transition-colors disabled:opacity-30">Next</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getAllOrders, adminUpdateOrderStatus } from '../api'

const loading = ref(true)
const orders = ref([])
const activeFilter = ref('all')
const currentPage = ref(1)
const totalPages = ref(1)
const updatingId = ref(null)

const statusTabs = [
  { label: 'All', value: 'all' },
  { label: 'Pending', value: 'pending' },
  { label: 'Confirmed', value: 'confirmed' },
  { label: 'Preparing', value: 'preparing' },
  { label: 'Ready', value: 'ready_for_pickup' },
  { label: 'Out for Delivery', value: 'out_for_delivery' },
  { label: 'Delivered', value: 'delivered' },
  { label: 'Cancelled', value: 'cancelled' },
]

const fetchOrders = async () => {
  loading.value = true
  try {
    const data = await getAllOrders(activeFilter.value, currentPage.value)
    orders.value = data.data || []
    totalPages.value = data.last_page || 1
  } catch (e) { console.error(e) }
  finally { loading.value = false }
}

const progressOrder = async (orderId, status) => {
  updatingId.value = orderId
  try {
    await adminUpdateOrderStatus(orderId, status)
    await fetchOrders()
  } catch (e) { console.error(e) }
  finally { updatingId.value = null }
}

const nextStatus = (s) => ({
  pending: 'confirmed',
  confirmed: 'preparing',
  preparing: 'ready_for_pickup',
  ready_for_pickup: 'out_for_delivery',
  out_for_delivery: 'delivered',
}[s] || null)

const nextActionLabel = (s) => ({
  pending: 'Accept',
  confirmed: 'Start Prep',
  preparing: 'Mark Ready',
  ready_for_pickup: 'Picked Up',
  out_for_delivery: 'Delivered',
}[s] || 'Next')

const statusLabel = (s) => ({
  pending: 'Pending',
  confirmed: 'Confirmed',
  preparing: 'Preparing',
  ready_for_pickup: 'Ready',
  out_for_delivery: 'On the Way',
  delivered: 'Delivered',
  cancelled: 'Cancelled',
  abandoned: 'Abandoned',
  rescued: 'Rescued',
}[s] || s)

const statusBadge = (s) => ({
  pending: 'bg-amber-100 text-amber-700',
  confirmed: 'bg-blue-100 text-blue-700',
  preparing: 'bg-purple-100 text-purple-700',
  ready_for_pickup: 'bg-teal-100 text-teal-700',
  out_for_delivery: 'bg-cyan-100 text-cyan-700',
  delivered: 'bg-green-100 text-green-700',
  cancelled: 'bg-red-100 text-red-700',
  abandoned: 'bg-red-50 text-red-500',
  rescued: 'bg-orange-100 text-orange-700',
}[s] || 'bg-stone-100 text-stone-600')

const formatTime = (d) => new Date(d).toLocaleString('en-IN', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })

onMounted(fetchOrders)
</script>
