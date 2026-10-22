<template>
  <div class="bg-background text-on-surface min-h-screen">
    <nav class="fixed top-0 left-0 w-full flex justify-between items-center px-8 py-4 bg-[#fdf9f4]/60 backdrop-blur-xl z-50 border-b border-outline-variant/10">
      <div class="flex items-center gap-6">
        <router-link to="/" class="font-serif text-3xl italic font-bold text-primary">UtterLazy</router-link>
        <router-link to="/" class="flex items-center gap-1 text-on-surface-variant hover:text-primary transition-colors font-medium">
          <span class="material-symbols-outlined text-[18px]">arrow_back</span> Home
        </router-link>
      </div>
    </nav>

    <main class="pt-28 px-8 max-w-4xl mx-auto pb-16">
      <header class="mb-10">
        <h1 class="font-serif text-4xl font-bold text-on-surface mb-2">Your Orders</h1>
        <p class="text-on-surface-variant font-medium">Track and review your culinary journey.</p>
      </header>

      <div v-if="loading" class="py-20 text-center text-on-surface-variant">
        <span class="material-symbols-outlined text-5xl animate-spin block mb-4">progress_activity</span>
        <p class="text-lg font-medium">Fetching your orders...</p>
      </div>

      <div v-else-if="orders.length === 0" class="py-20 text-center">
        <div class="w-24 h-24 rounded-full bg-surface-container-high mx-auto flex items-center justify-center mb-6">
          <span class="material-symbols-outlined text-5xl text-on-surface-variant opacity-50">receipt_long</span>
        </div>
        <h2 class="font-serif text-2xl font-bold text-on-surface mb-2">No orders yet</h2>
        <p class="text-on-surface-variant mb-8">Your culinary adventure starts with a single order!</p>
        <router-link to="/" class="primary-gradient text-on-primary font-bold py-3 px-8 rounded-xl shadow-lg shadow-primary/20 inline-flex items-center gap-2">
          <span class="material-symbols-outlined">restaurant_menu</span> Explore Restaurants
        </router-link>
      </div>

      <div v-else class="space-y-6">
        <article v-for="order in orders" :key="order.id"
          class="bg-white rounded-3xl border border-outline-variant/30 overflow-hidden hover:shadow-xl transition-all duration-500 group">
          <!-- Header -->
          <div class="p-6 flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-outline-variant/10">
            <div>
              <div class="flex items-center gap-3 mb-1.5">
                <h3 class="font-serif text-xl font-bold">#{{ order.id.slice(0, 8).toUpperCase() }}</h3>
                <span :class="statusBadgeClass(order.status)" class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest">
                  {{ statusLabel(order.status) }}
                </span>
                <span v-if="order.payment_mode" class="px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest border"
                  :class="order.payment_mode === 'prepaid' ? 'bg-green-50 text-green-700 border-green-100' : 'bg-amber-50 text-amber-700 border-amber-100'">
                  {{ order.payment_mode }}
                </span>
              </div>
              <p class="text-sm text-on-surface-variant">
                <span class="font-bold text-on-surface">{{ order.restaurant?.name || 'Restaurant' }}</span>
                <span class="mx-2 opacity-30">•</span>
                <span class="font-medium">{{ formatDate(order.created_at) }}</span>
              </p>
            </div>
            <div class="flex items-center gap-4">
              <div class="text-right">
                <p class="font-serif text-2xl font-bold text-primary">₹{{ parseFloat(order.grand_total).toFixed(0) }}</p>
              </div>
              <div class="flex gap-2">
                <!-- Pay Online Option for COD -->
                <button v-if="order.payment_mode === 'cod' && isActive(order.status)"
                  @click="handlePayOnline(order)"
                  :disabled="processingOrder === order.id"
                  class="flex items-center gap-2 bg-secondary text-on-secondary px-5 py-3 rounded-2xl font-bold text-xs hover:scale-105 active:scale-95 transition-all shadow-md shadow-secondary/20 disabled:opacity-50">
                  <span class="material-symbols-outlined text-[18px]">payments</span>
                  {{ processingOrder === order.id ? 'PROCESSING...' : 'PAY ONLINE' }}
                </button>
                
                <router-link :to="'/order/' + order.id + '/track'"
                  class="flex items-center gap-2 bg-on-surface text-surface px-5 py-3 rounded-2xl font-bold text-xs hover:bg-primary transition-all shadow-md">
                  <span class="material-symbols-outlined text-[18px]">timeline</span>
                  {{ isActive(order.status) ? 'TRACK' : 'DETAILS' }}
                </router-link>
                <router-link v-if="order.status === 'delivered'" :to="'/invoice/' + order.id"
                  class="flex items-center gap-2 bg-white border border-outline-variant/30 text-on-surface px-5 py-3 rounded-2xl font-bold text-xs hover:border-primary hover:text-primary transition-all shadow-sm">
                  <span class="material-symbols-outlined text-[18px]">download</span>
                  INVOICE
                </router-link>
                <button v-if="order.status === 'delivered' || order.status === 'completed'" @click="handleReorder(order.id)"
                  class="flex items-center gap-2 bg-white border border-outline-variant/30 text-on-surface px-5 py-3 rounded-2xl font-bold text-xs hover:border-primary hover:text-primary transition-all shadow-sm"
                  :disabled="processingId === order.id">
                  <span class="material-symbols-outlined text-[18px]">{{ processingId === order.id ? 'hourglass_top' : 'replay' }}</span>
                  {{ processingId === order.id ? 'ADDING...' : 'ORDER AGAIN' }}
                </button>
              </div>
            </div>
          </div>

          <!-- Items -->
          <div class="px-6 py-6 bg-surface-variant/5">
            <div v-if="order.items && order.items.length" class="space-y-3">
              <div v-for="item in order.items" :key="item.id" class="flex justify-between items-center">
                <div class="flex items-center gap-4">
                  <span class="w-7 h-7 rounded-lg bg-primary/10 flex items-center justify-center text-primary text-[10px] font-black">{{ item.quantity }}x</span>
                  <span class="font-bold text-sm text-on-surface opacity-80">{{ item.menu_item?.name || 'Artisan Dish' }}</span>
                </div>
                <span class="font-black text-sm text-on-surface/60">₹{{ parseFloat(item.total_price).toFixed(0) }}</span>
              </div>
            </div>
          </div>

          <!-- OTP Banner for active prepaid orders -->
          <div v-if="order.delivery_otp && ['ready_for_pickup', 'out_for_delivery'].includes(order.status)"
            class="px-6 py-4 bg-primary text-on-primary flex items-center justify-between">
            <div class="flex items-center gap-3">
              <span class="material-symbols-outlined text-[20px]">verified_user</span>
              <span class="text-xs font-black uppercase tracking-[0.2em]">Secure Delivery OTP</span>
            </div>
            <div class="flex gap-2">
              <span v-for="(d, i) in order.delivery_otp.split('')" :key="i"
                class="w-9 h-11 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center text-xl font-black font-mono shadow-inner">
                {{ d }}
              </span>
            </div>
          </div>
        </article>
      </div>
    </main>

    <Footer />
    
    <!-- Status Toast -->
    <div v-if="toast" class="fixed bottom-12 left-1/2 -translate-x-1/2 bg-on-surface text-surface px-8 py-4 rounded-2xl z-[100] font-bold text-xs tracking-widest uppercase shadow-2xl animate-bounce">
      {{ toast }}
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getMe, payOrderOnline, reorderOrder } from '../api'
import Footer from '../components/Footer.vue'

const orders = ref([])
const loading = ref(true)
const processingOrder = ref(null)
const processingId = ref(null)
const toast = ref('')

const showToast = (msg) => {
  toast.value = msg
  setTimeout(() => toast.value = '', 4000)
}

const fetchOrders = async () => {
  try {
    const data = await getMe()
    orders.value = data.recent_orders || []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(fetchOrders)

const handlePayOnline = async (order) => {
  processingOrder.value = order.id
  try {
    const res = await payOrderOnline(order.id)
    showToast(res.message)
    // Refresh the order list to show updated status/mode
    await fetchOrders()
  } catch (e) {
    showToast(e.message || 'Payment transition failed')
  } finally {
    processingOrder.value = null
  }
}

const handleReorder = async (orderId) => {
  processingId.value = orderId
  try {
    const data = await reorderOrder(orderId)
    showToast(`${data.item_count} items added to cart!`)
    await fetchOrders()
  } catch (e) {
    showToast(e.message || 'Reorder failed')
  } finally {
    processingId.value = null
  }
}

const statusLabel = (s) => ({
  pending: 'Waiting for Restaurant',
  confirmed: 'Accepted',
  preparing: 'Being Prepared',
  ready_for_pickup: 'Ready for Pickup',
  out_for_delivery: 'On the Way',
  delivered: 'Delivered',
  cancelled: 'Cancelled',
  abandoned: 'Abandoned',
  rescued: 'Rescued',
}[s] || s)

const statusBadgeClass = (status) => {
  const map = {
    pending: 'bg-amber-100 text-amber-700',
    confirmed: 'bg-blue-100 text-blue-700',
    preparing: 'bg-purple-100 text-purple-700',
    ready_for_pickup: 'bg-teal-100 text-teal-700',
    out_for_delivery: 'bg-cyan-100 text-cyan-700',
    delivered: 'bg-green-100 text-green-700',
    cancelled: 'bg-red-100 text-red-700',
    abandoned: 'bg-red-100 text-red-700',
    rescued: 'bg-orange-100 text-orange-700',
  }
  return map[status] || 'bg-stone-100 text-stone-600'
}

const isActive = (s) => ['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery'].includes(s)

const formatDate = (d) => new Date(d).toLocaleDateString('en-IN', { weekday: 'short', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
</script>
