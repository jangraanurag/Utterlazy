<template>
  <div>
    <div class="mb-12">
      <h1 class="font-display text-5xl font-bold tracking-tighter text-white mb-2">Command <span class="text-primary">Center</span></h1>
      <p class="font-display text-xs font-bold uppercase tracking-[0.3em] text-on-surface-variant opacity-60">Real-time platform intelligence & mission control</p>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="py-32 text-center">
      <div class="w-12 h-12 border-2 border-primary/20 border-t-primary rounded-full animate-spin mx-auto mb-6"></div>
      <p class="font-display font-bold uppercase tracking-widest text-[10px]">Synchronizing Intel...</p>
    </div>

    <div v-else>
      <!-- KPI Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
        <div v-for="kpi in kpiCards" :key="kpi.label"
          class="glass-card p-8 rounded-[2rem] hover:bg-white/5 transition-all duration-500 group relative overflow-hidden">
          <div class="flex items-center justify-between mb-6">
            <div :class="kpi.iconColor" class="w-12 h-12 rounded-2xl bg-white/5 flex items-center justify-center group-hover:scale-110 transition-transform duration-500">
              <span class="material-symbols-outlined text-[24px]">{{ kpi.icon }}</span>
            </div>
            <span v-if="kpi.trend" :class="kpi.trend > 0 ? 'text-success' : 'text-error'" class="text-[10px] font-bold font-display uppercase tracking-widest">
               {{ kpi.trend > 0 ? '▲' : '▼' }} GROWTH
            </span>
          </div>
          <p class="text-on-surface-variant text-[10px] font-bold uppercase tracking-[0.2em] mb-2">{{ kpi.label }}</p>
          <p class="font-display text-3xl font-bold text-white tracking-tighter">{{ kpi.value }}</p>
          
          <!-- Decorative accent -->
          <div class="absolute bottom-0 right-0 w-24 h-24 bg-primary/5 rounded-full translate-x-12 translate-y-12 blur-2xl"></div>
        </div>
      </div>

      <!-- Financial Health & Mission Pulse -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12">
         <!-- Status Breakdown -->
         <div class="lg:col-span-2 glass-card rounded-[3rem] p-10">
            <h3 class="font-display text-xl font-bold text-white mb-8 uppercase tracking-tighter flex items-center gap-4">
               <span class="w-2 h-2 rounded-full bg-primary shadow-[0_0_10px_#ff6b00]"></span> Mission Pulse
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
               <div v-for="(count, status) in stats.status_breakdown" :key="status"
                  class="p-6 rounded-[2rem] bg-white/5 border border-white/5 hover:border-white/10 transition-colors">
                  <div class="text-[10px] font-bold uppercase tracking-widest opacity-50 mb-3" :class="statusTextColor(status)">
                     {{ statusLabel(status) }}
                  </div>
                  <p class="font-display text-4xl font-bold text-white tracking-tighter">{{ count }}</p>
               </div>
            </div>
         </div>

         <!-- Revenue Health -->
         <div class="glass-card rounded-[3rem] p-10 flex flex-col justify-between relative overflow-hidden">
            <div>
               <h3 class="font-display text-xl font-bold text-white mb-2 uppercase tracking-tighter">Vault Velocity</h3>
               <p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest opacity-60 mb-8">Daily Financial Flow</p>
               
               <div class="space-y-6">
                  <div>
                     <div class="flex justify-between text-[10px] font-bold uppercase tracking-widest mb-2">
                        <span>Revenue Target</span>
                        <span class="text-primary">78%</span>
                     </div>
                     <div class="h-1.5 w-full bg-white/5 rounded-full overflow-hidden">
                        <div class="h-full bg-primary rounded-full shadow-[0_0_10px_#ff6b00]" style="width: 78%"></div>
                     </div>
                  </div>
                  <div>
                     <div class="flex justify-between text-[10px] font-bold uppercase tracking-widest mb-2">
                        <span>Fleet Efficiency</span>
                        <span class="text-secondary">92%</span>
                     </div>
                     <div class="h-1.5 w-full bg-white/5 rounded-full overflow-hidden">
                        <div class="h-full bg-secondary rounded-full shadow-[0_0_10px_#00d4ff]" style="width: 92%"></div>
                     </div>
                  </div>
               </div>
            </div>

            <div class="mt-12">
               <div class="flex items-center gap-4 p-4 rounded-2xl bg-white/5 border border-white/5">
                  <div class="w-10 h-10 rounded-xl bg-orange-500/20 flex items-center justify-center text-orange-500">
                     <span class="material-symbols-outlined text-[20px]">bolt</span>
                  </div>
                  <div>
                     <p class="text-[10px] font-bold text-white uppercase tracking-widest">Surge Mode</p>
                     <p class="text-[12px] font-display font-bold text-orange-500">{{ stats.surge_multiplier || '1.0' }}× ACTIVE</p>
                  </div>
               </div>
            </div>
         </div>
      </div>

      <!-- Funnel Analytics -->
      <div class="glass-card rounded-[3rem] p-10 mb-8">
        <h3 class="font-display text-xl font-bold text-white mb-8 uppercase tracking-tighter flex items-center gap-4">
           <span class="w-2 h-2 rounded-full bg-secondary shadow-[0_0_10px_#00d4ff]"></span> Order Funnel
        </h3>
        <div class="grid grid-cols-6 gap-4">
          <div v-for="(stage, key) in funnelStages" :key="key" class="text-center">
            <div class="text-[10px] font-bold uppercase tracking-widest opacity-50 mb-2">{{ stage.label }}</div>
            <div class="text-2xl font-bold text-white">{{ stage.count }}</div>
            <div class="text-[10px] font-bold text-on-surface-variant mt-1">{{ stage.pct }}%</div>
            <div class="mt-3 h-2 w-full bg-white/5 rounded-full overflow-hidden">
              <div class="h-full rounded-full" :class="stage.barColor" :style="{ width: stage.pct + '%' }"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Service Quality -->
      <div class="grid grid-cols-4 gap-6 mb-8">
        <div v-for="sq in serviceQuality" :key="sq.label" class="glass-card p-6 rounded-[2rem]">
          <p class="text-[10px] font-bold uppercase tracking-widest text-on-surface-variant opacity-60 mb-2">{{ sq.label }}</p>
          <p class="font-display text-2xl font-bold text-white">{{ sq.value }}</p>
        </div>
      </div>

      <!-- Recent Orders (Live) -->
      <div v-if="recentOrders.length" class="glass-card rounded-[3rem] p-10 mb-8">
        <h3 class="font-display text-xl font-bold text-white mb-8 uppercase tracking-tighter flex items-center gap-4">
          <span class="w-2 h-2 rounded-full bg-success animate-pulse shadow-[0_0_10px_#22c55e]"></span> Recent Orders
        </h3>
        <div class="space-y-3">
          <div v-for="order in recentOrders" :key="order.id"
            class="flex items-center justify-between p-4 rounded-2xl bg-white/5 border border-white/5 hover:border-white/10 transition-colors">
            <div class="flex items-center gap-4">
              <span :class="statusTextColor(order.status)" class="text-[10px] font-bold uppercase tracking-widest">{{ statusLabel(order.status) }}</span>
              <span class="text-white font-display font-bold">{{ order.restaurant || 'Unknown' }}</span>
              <span class="text-on-surface-variant text-[10px]">{{ order.customer }}</span>
            </div>
            <div class="flex items-center gap-4">
              <span class="text-white font-bold">₹{{ order.total }}</span>
              <span class="text-[10px] text-on-surface-variant">{{ order.created_at }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Operational Intelligence -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <router-link v-for="action in quickActions" :key="action.to" :to="action.to" 
           class="glass-card p-8 rounded-[2.5rem] hover:bg-white/5 transition-all duration-500 group flex items-center gap-6 border border-white/5 hover:border-primary/20">
          <div :class="action.color" class="w-16 h-16 rounded-[1.5rem] bg-white/5 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform duration-500">
            <span class="material-symbols-outlined text-[28px]">{{ action.icon }}</span>
          </div>
          <div>
            <p class="font-display text-lg font-bold text-white uppercase tracking-tighter group-hover:text-primary transition-colors">{{ action.label }}</p>
            <p class="text-[10px] font-bold text-on-surface-variant uppercase tracking-widest opacity-60">{{ action.desc }}</p>
          </div>
        </router-link>
      </div>

      <!-- System Pulse Footer -->
      <div v-if="lastUpdate" class="mt-16 flex items-center justify-center gap-4">
         <div class="w-2 h-2 rounded-full bg-success animate-pulse shadow-[0_0_8px_#22c55e]"></div>
         <span class="font-display text-[10px] font-bold uppercase tracking-[0.3em] text-on-surface-variant opacity-40">Live • Last sync {{ lastUpdate }}</span>
      </div>
      <div v-else class="mt-16 flex items-center justify-center gap-4">
         <div class="w-2 h-2 rounded-full bg-success animate-pulse shadow-[0_0_8px_#22c55e]"></div>
         <span class="font-display text-[10px] font-bold uppercase tracking-[0.3em] text-on-surface-variant opacity-40">System Operational • Heartbeat Active</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { getStats, getLiveStats } from '../api'

const loading = ref(true)
const stats = ref({})
const liveData = ref(null)
const lastUpdate = ref(null)
let pollInterval = null
let ws = null

const funnelStages = computed(() => {
  const f = stats.value.funnel || {}
  const labels = { pending:'Placed', confirmed:'Confirmed', preparing:'Preparing', ready_for_pickup:'Ready', out_for_delivery:'On Way', delivered:'Delivered' }
  const colors = { pending:'bg-amber-500', confirmed:'bg-blue-500', preparing:'bg-purple-500', ready_for_pickup:'bg-teal-500', out_for_delivery:'bg-secondary', delivered:'bg-success' }
  const keys = ['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery', 'delivered']
  return keys.map(k => ({
    label: labels[k],
    count: f[k]?.count || 0,
    pct: f[k]?.pct || 0,
    barColor: colors[k],
  }))
})

const serviceQuality = computed(() => [
  { label: 'Avg Prep Time', value: stats.value.avg_prep_time != null ? stats.value.avg_prep_time + ' min' : '-' },
  { label: 'Avg Delivery Time', value: stats.value.avg_delivery_time != null ? stats.value.avg_delivery_time + ' min' : '-' },
  { label: 'Avg Fulfillment', value: stats.value.avg_fulfillment_time != null ? stats.value.avg_fulfillment_time + ' min' : '-' },
  { label: 'Avg Rating', value: stats.value.avg_rating != null ? '⭐ ' + stats.value.avg_rating : '-' },
])

const kpiCards = computed(() => {
  const live = liveData.value
  return [
    { label: 'Total Orders', value: (live?.orders?.today ?? stats.value.today_orders ?? 0).toLocaleString(), icon: 'military_tech', iconColor: 'text-primary', trend: 1 },
    { label: 'Today Revenue', value: '₹' + parseFloat(live?.revenue?.today ?? stats.value.today_revenue ?? 0).toLocaleString('en-IN'), icon: 'account_balance', iconColor: 'text-secondary', trend: 1 },
    { label: 'Online Fleet', value: (live?.riders?.online ?? stats.value.online_riders ?? 0) + ' / ' + (live?.riders?.total ?? stats.value.total_riders ?? 0), icon: 'two_wheeler', iconColor: 'text-tertiary', trend: null },
    { label: 'Active Orders', value: (live?.orders?.active ?? 0).toLocaleString(), icon: 'bolt', iconColor: 'text-orange-500', trend: null },
  ]
})

const recentOrders = computed(() => liveData.value?.recent_orders || [])

const quickActions = [
  { to: '/war-room', label: 'War Room', desc: 'Live order map & ops', icon: 'map', color: 'text-red-500' },
  { to: '/orders', label: 'Mission Logs', desc: 'Real-time order orchestration', icon: 'receipt_long', color: 'text-primary' },
  { to: '/restaurants', label: 'Venues', desc: 'Restaurant management & commission', icon: 'storefront', color: 'text-primary' },
  { to: '/rescue', label: 'Rescue Ops', desc: 'Recover abandoned assets', icon: 'healing', color: 'text-orange-500' },
  { to: '/reviews', label: 'Reviews', desc: 'Customer feedback & ratings', icon: 'rate_review', color: 'text-yellow-500' },
  { to: '/complaints', label: 'Complaints', desc: 'Customer issue resolution', icon: 'feedback', color: 'text-red-500' },
  { to: '/payments', label: 'Payouts', desc: 'Vendor weekly settlements', icon: 'account_balance', color: 'text-emerald-500' },
  { to: '/invoices', label: 'Invoices', desc: 'Commission invoice management', icon: 'receipt', color: 'text-purple-500' },
  { to: '/riders', label: 'Fleet Command', desc: 'Rider management & status', icon: 'hub', color: 'text-secondary' },
  { to: '/rider-payouts', label: 'Vault Payouts', desc: 'Fleet financial settlements', icon: 'payments', color: 'text-tertiary' },
  { to: '/configs', label: 'Global Parameters', desc: 'System tax & commission', icon: 'terminal', color: 'text-white' },
]

const statusLabel = (s) => ({
  pending: 'Pending', confirmed: 'Confirmed', preparing: 'Preparing',
  ready_for_pickup: 'Ready', out_for_delivery: 'On Way',
  delivered: 'Complete', cancelled: 'Aborted',
  abandoned: 'Lost', rescued: 'Secured',
}[s] || s)

const statusTextColor = (s) => ({
  pending: 'text-amber-500', confirmed: 'text-blue-500',
  preparing: 'text-purple-500', ready_for_pickup: 'text-teal-500',
  out_for_delivery: 'text-secondary', delivered: 'text-success',
  cancelled: 'text-error', abandoned: 'text-error',
  rescued: 'text-primary',
}[s] || 'text-on-surface-variant')

const fetchStats = async () => {
  try { stats.value = await getStats() }
  catch (e) { console.error(e) }
  finally { loading.value = false }
}

const fetchLiveStats = async () => {
  try {
    const data = await getLiveStats()
    liveData.value = data
    lastUpdate.value = new Date().toLocaleTimeString()
  } catch (e) { console.error(e) }
}

const connectWebSocket = () => {
  const wsUrl = `wss://catalog.utterlazy.com:8080/app/utterlazy_reverb_key_2024`
  try {
    ws = new WebSocket(wsUrl)
    ws.onopen = () => {
      ws.send(JSON.stringify({ event: 'pusher:subscribe', data: { channel: 'admin-dashboard' } }))
    }
    ws.onmessage = (event) => {
      try {
        const msg = JSON.parse(event.data)
        if (msg.event === 'stats.updated' && msg.data?.stats) {
          liveData.value = msg.data.stats
          lastUpdate.value = new Date().toLocaleTimeString()
        }
      } catch (_) {}
    }
    ws.onclose = () => { setTimeout(connectWebSocket, 5000) }
    ws.onerror = () => { ws?.close() }
  } catch (e) { console.error('WS failed:', e) }
}

onMounted(() => {
  fetchStats()
  fetchLiveStats()
  connectWebSocket()
  pollInterval = setInterval(fetchLiveStats, 30000)
})
onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval)
  if (ws) { ws.onclose = null; ws.close() }
})
</script>
