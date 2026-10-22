<template>
  <div class="bg-background text-on-surface min-h-screen">
    <nav class="fixed top-0 left-0 w-full flex justify-between items-center px-8 py-4 bg-[#fdf9f4]/60 backdrop-blur-xl z-50">
      <div class="flex items-center gap-6">
        <router-link to="/" class="font-serif text-3xl italic font-bold text-primary">UtterLazy</router-link>
        <router-link to="/orders" class="flex items-center gap-1 text-on-surface-variant hover:text-primary transition-colors font-medium">
          <span class="material-symbols-outlined text-[18px]">arrow_back</span> My Orders
        </router-link>
      </div>
    </nav>

    <main class="pt-28 px-8 max-w-3xl mx-auto pb-16">
      <!-- Loading -->
      <div v-if="loading" class="py-20 text-center text-on-surface-variant">
        <span class="material-symbols-outlined text-5xl animate-spin block mb-4">progress_activity</span>
        <p class="text-lg">Loading order details...</p>
      </div>

      <div v-else-if="order">
        <!-- Header -->
        <header class="mb-10">
          <div class="flex items-center gap-3 mb-2">
            <h1 class="font-serif text-3xl font-bold">Order #{{ order.id.slice(0, 8).toUpperCase() }}</h1>
            <span :class="statusBadgeClass" class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider">
              {{ order.status }}
            </span>
          </div>
          <p class="text-on-surface-variant">{{ order.restaurant?.name || 'Restaurant' }} • {{ new Date(order.created_at).toLocaleDateString('en-IN', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
        </header>

        <!-- Static Layout for Completed/Cancelled Orders -->
        <div v-if="isFinalStatus">
          <!-- From/To Details Card -->
          <section class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 p-6 mb-8 grid grid-cols-1 md:grid-cols-2 gap-6 text-left">
            <div>
               <h4 class="text-xs font-black text-primary uppercase tracking-widest mb-2">FROM (RESTAURANT)</h4>
               <p class="font-bold text-on-surface">{{ order.restaurant?.name || 'Restaurant' }}</p>
               <p class="text-sm text-on-surface-variant mt-1">{{ order.restaurant?.address || 'Restaurant Address' }}</p>
            </div>
            <div>
               <h4 class="text-xs font-black text-primary uppercase tracking-widest mb-2">DELIVERED TO</h4>
               <p class="font-bold text-on-surface">{{ order.delivery_address_text || 'Saved Delivery Address' }}</p>
            </div>
          </section>

          <!-- Static Timeline -->
          <section class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 p-8 mb-8 text-left">
            <h3 class="font-serif text-xl font-bold mb-8 flex items-center gap-2">
              <span class="material-symbols-outlined text-primary">timeline</span> Order History Timeline
            </h3>
            <div class="space-y-6">
              <div v-for="step in staticHistorySteps" :key="step.label" class="flex gap-4">
                <div class="flex flex-col items-center">
                  <div class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">{{ step.icon }}</span>
                  </div>
                </div>
                <div>
                  <p class="text-on-surface font-bold">{{ step.label }}</p>
                  <p class="text-xs text-on-surface-variant mt-0.5">{{ step.time }}</p>
                </div>
              </div>
            </div>
          </section>
        </div>

        <!-- Live Layout for Active Orders -->
        <div v-else>
          <!-- Rider Status Card -->
          <section v-if="order.status !== 'cancelled' && order.status !== 'abandoned'" class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 p-6 mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
              <h2 class="text-lg font-bold text-on-surface uppercase tracking-wide">
                {{
                  order.status === 'delivered'
                    ? 'Delivered'
                    : order.status === 'out_for_delivery'
                      ? 'Out for Delivery'
                      : order.status === 'ready_for_pickup'
                        ? 'Food is Ready'
                        : order.status === 'preparing'
                          ? 'Preparing'
                          : 'Order Placed'
                }}
              </h2>
              <p class="text-on-surface-variant mt-1 text-sm">{{ riderStatusText }}</p>
              <div v-if="order.rider_name" class="flex items-center gap-2 mt-3 text-sm">
                <span class="material-symbols-outlined text-[18px] text-primary">directions_bike</span>
                <span class="font-medium text-on-surface">{{ order.rider_name }}</span>
                <span v-if="order.rider_phone" class="text-on-surface-variant font-mono">• {{ order.rider_phone }}</span>
              </div>
            </div>
          </section>

          <!-- Status Timeline -->
          <section class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 p-8 mb-8">
            <h3 class="font-serif text-xl font-bold mb-8 flex items-center gap-2">
              <span class="material-symbols-outlined text-primary">timeline</span> Order Timeline
            </h3>
            <div class="space-y-0">
              <div v-for="(step, index) in timelineSteps" :key="step.key" class="flex gap-4">
                <!-- Dot + Line -->
                <div class="flex flex-col items-center">
                  <div :class="[step.reached ? 'bg-primary text-on-primary scale-110' : 'bg-surface-container-high text-on-surface-variant', step.active ? 'ring-4 ring-primary/20' : '']"
                    class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 transition-all duration-500">
                    <span class="material-symbols-outlined text-[20px]" :style="step.reached ? 'font-variation-settings: FILL 1;' : ''">{{ step.icon }}</span>
                  </div>
                  <div v-if="index < timelineSteps.length - 1" :class="step.reached ? 'bg-primary' : 'bg-outline-variant/30'" class="w-0.5 h-12 transition-colors duration-500"></div>
                </div>
                <!-- Text -->
                <div class="pt-2 pb-6 text-left">
                  <p :class="step.reached ? 'text-on-surface font-bold' : 'text-on-surface-variant'" class="transition-colors">{{ step.label }}</p>
                  <p v-if="step.active && step.key === 'pending'" class="text-xs text-amber-600 font-bold mt-1 animate-pulse">⏳ Waiting for restaurant...</p>
                  <p v-else-if="step.active" class="text-xs text-primary font-bold mt-1 animate-pulse">In Progress...</p>
                  <p v-else-if="step.reached && step.sublabel" class="text-xs text-on-surface-variant mt-0.5">{{ step.sublabel }}</p>
                </div>
              </div>
            </div>
          </section>

          <!-- Delivery OTP Card (shown for prepaid orders during active delivery) -->
          <section v-if="order.payment_mode === 'prepaid' && ['ready_for_pickup', 'out_for_delivery'].includes(order.status)"
            class="rounded-2xl border-2 border-dashed border-primary/40 bg-primary/5 p-6 mb-8 text-center">
            <div class="flex items-center justify-center gap-2 mb-3">
              <span class="material-symbols-outlined text-primary text-[24px]">lock</span>
              <h3 class="font-serif text-lg font-bold text-on-surface">Delivery OTP</h3>
            </div>
            <p class="text-on-surface-variant text-sm mb-4">Share this OTP with the delivery rider to confirm your order</p>
            <div class="flex justify-center gap-3">
              <span v-for="(digit, i) in (order.delivery_otp ? order.delivery_otp.split('') : ['-', '-', '-', '-'])" :key="i"
                class="w-14 h-16 rounded-xl bg-surface-container-lowest border-2 border-primary/30 flex items-center justify-center text-3xl font-black text-primary font-mono shadow-sm">
                {{ digit }}
              </span>
            </div>
            <p class="text-xs text-on-surface-variant mt-4 flex items-center justify-center gap-1">
              <span class="material-symbols-outlined text-[14px]">info</span>
              Do not share this OTP before receiving your order
            </p>
          </section>

          <!-- OTP Verified Banner (after delivery) -->
          <section v-if="order.payment_mode === 'prepaid' && order.status === 'delivered'"
            class="rounded-2xl bg-green-50 border border-green-200 p-4 mb-8 flex items-center gap-3">
            <span class="material-symbols-outlined text-green-600 text-[28px]" style="font-variation-settings: 'FILL' 1;">verified</span>
            <div>
              <p class="font-bold text-green-800">Delivery Verified</p>
              <p class="text-sm text-green-600">OTP was verified by the rider. Enjoy your meal!</p>
            </div>
          </section>

          <!-- Mini Games (shown while waiting) -->
          <MiniGames v-if="['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery'].includes(order.status)" />
        </div>

        <!-- Order Items -->
        <section class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 p-8 mb-8">
          <h3 class="font-serif text-xl font-bold mb-6 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">receipt_long</span> Items
          </h3>
          <div class="space-y-3">
            <div v-for="item in order.items" :key="item.id" class="flex justify-between items-center py-3 border-b border-outline-variant/10 last:border-0">
              <div class="flex items-center gap-3">
                <span class="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center text-primary text-sm font-bold">{{ item.quantity }}x</span>
                <span class="font-medium">{{ item.menu_item?.name || 'Menu Item' }}</span>
              </div>
              <span class="font-bold text-on-surface font-mono">₹{{ parseFloat(item.total_price).toFixed(2) }}</span>
            </div>
          </div>

          <!-- Total -->
          <div class="mt-6 pt-4 border-t border-outline-variant/30 flex justify-between items-center">
            <span class="font-serif text-xl font-bold">Grand Total</span>
            <span class="font-serif text-2xl font-bold text-primary font-mono">₹{{ parseFloat(order.grand_total).toFixed(2) }}</span>
          </div>

          <!-- Report Issue for delivered orders -->
          <router-link v-if="order.status === 'delivered'" :to="'/file-complaint/' + order.id"
            class="mt-6 flex items-center justify-center gap-2 w-full py-3 rounded-xl border-2 border-dashed border-red-200 text-red-600 font-bold text-sm hover:bg-red-50 transition-all active:scale-[0.99]">
            <span class="material-symbols-outlined text-[18px]">report</span>
            Report Issue with This Order
          </router-link>
        </section>

        <!-- Auto-refresh indicator -->
        <div v-if="!isFinalStatus" class="text-center text-xs text-on-surface-variant flex items-center justify-center gap-2">
          <span class="material-symbols-outlined text-[14px] animate-spin">sync</span>
          Auto-refreshing every 15 seconds
        </div>
      </div>

      <!-- Not Found -->
      <div v-else class="py-20 text-center">
        <span class="material-symbols-outlined text-5xl text-on-surface-variant opacity-30 block mb-4">error</span>
        <h2 class="font-serif text-2xl font-bold mb-2">Order Not Found</h2>
        <router-link to="/orders" class="text-primary font-bold hover:underline">Go to Orders</router-link>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import MiniGames from '../components/MiniGames.vue'
import { useRoute } from 'vue-router'
import { getOrder, trackOrder } from '../api'

const route = useRoute()
const order = ref(null)
const tracking = ref(null)
const loading = ref(true)
let pollInterval = null

const statusOrder = ['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery', 'delivered']

const trackingData = computed(() => tracking.value || order.value)

const timelineSteps = computed(() => {
  const currentStatus = trackingData.value?.status || 'pending'
  const currentIndex = statusOrder.indexOf(currentStatus)
  return [
    { key: 'pending', icon: 'shopping_bag', label: 'Order Placed', sublabel: 'Waiting for restaurant to accept', reached: currentIndex >= 0, active: currentIndex === 0 },
    { key: 'confirmed', icon: 'check_circle', label: 'Restaurant Accepted', sublabel: 'Your order has been confirmed', reached: currentIndex >= 1, active: currentIndex === 1 },
    { key: 'preparing', icon: 'skillet', label: 'Being Prepared', sublabel: 'Chef is cooking your food', reached: currentIndex >= 2, active: currentIndex === 2 },
    { key: 'ready_for_pickup', icon: 'takeout_dining', label: 'Order Ready', sublabel: 'Waiting for rider to pick up', reached: currentIndex >= 3, active: currentIndex === 3 },
    { key: 'out_for_delivery', icon: 'two_wheeler', label: 'Rider Picked Up', sublabel: 'On the way to you', reached: currentIndex >= 4, active: currentIndex === 4 },
    { key: 'delivered', icon: 'home_pin', label: 'Delivered', sublabel: 'Enjoy your meal!', reached: currentIndex >= 5, active: false },
  ]
})

const etaText = computed(() => {
  const etas = tracking.value?.etas
  if (etas?.total_eta_min) return `${etas.total_eta_min} min`
  if (etas?.estimated_delivery_time) {
    const d = new Date(etas.estimated_delivery_time)
    return `by ${d.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' })}`
  }
  return null
})

const riderInfo = computed(() => tracking.value?.rider)

const statusBadgeClass = computed(() => {
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
  return map[trackingData.value?.status] || 'bg-stone-100 text-stone-600'
})

const riderStatusText = computed(() => {
  const status = trackingData.value?.status
  const rider = tracking.value?.rider
  const etas = tracking.value?.etas

  if (!status) return ''
  if (status === 'delivered') return 'Hope you enjoyed your meal!'
  if (status === 'out_for_delivery') {
    if (rider) return `${rider.name} is on the way${etas?.restaurant_to_customer_min ? ` • ${etas.restaurant_to_customer_min} min away` : ''}`
    return 'Rider is on the way to your address'
  }
  if (status === 'ready_for_pickup') return rider ? `${rider.name} is heading to the restaurant` : 'Waiting for rider assignment'
  if (status === 'preparing') return rider ? `Restaurant is preparing. ${rider.name} is moving to restaurant` : 'Chef is preparing your delicious meal'
  return 'Waiting for restaurant confirmation'
})

const isFinalStatus = computed(() => ['delivered', 'cancelled', 'abandoned'].includes(trackingData.value?.status))

const staticHistorySteps = computed(() => {
  const timeline = tracking.value?.timeline
  if (timeline && timeline.length > 0) {
    return timeline.filter(s => s.status !== 'pending').map(s => ({
      label: s.label,
      time: s.time ? new Date(s.time).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' }) + ' • ' + new Date(s.time).toLocaleDateString('en-IN', { day: 'numeric', month: 'short' }) : '',
      icon: s.status === 'completed' ? 'check_circle' : s.status === 'active' ? 'pending' : 'radio_button_unchecked',
    }))
  }
  return []
})

const fetchTracking = async () => {
  try {
    const data = await trackOrder(route.params.id)
    tracking.value = data
    if (!order.value) order.value = data
    if (data && ['delivered', 'cancelled'].includes(data.status)) {
      if (pollInterval) { clearInterval(pollInterval); pollInterval = null }
    }
  } catch (e) {
    // Fallback to regular order endpoint
    try {
      const data = await getOrder(route.params.id)
      order.value = data.order
    } catch (_) {}
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchTracking()
  pollInterval = setInterval(fetchTracking, 15000)
})

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval)
})
</script>
