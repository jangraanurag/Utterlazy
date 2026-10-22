<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import api from '../api'

const orders = ref([])
const loading = ref(true)
const soundEnabled = ref(true)
const audioCtx = ref(null)
let pollInterval = null

const statusFlow = ['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'out_for_delivery', 'delivered']

async function fetchOrders() {
  try {
    const res = await api.get('/vendor/orders/active')
    orders.value = (res.data.orders || []).sort((a, b) => new Date(a.created_at) - new Date(b.created_at))
    if (orders.value.some(o => o.status === 'pending')) playAlert()
  } catch (e) { console.error(e) }
  loading.value = false
}

onMounted(() => {
  fetchOrders()
  pollInterval = setInterval(fetchOrders, 10000)
  audioCtx.value = new (window.AudioContext || window.webkitAudioContext)()
})
onUnmounted(() => clearInterval(pollInterval))

function playAlert() {
  if (!soundEnabled.value || !audioCtx.value) return
  try {
    const ctx = audioCtx.value
    const osc = ctx.createOscillator()
    const gain = ctx.createGain()
    osc.connect(gain)
    gain.connect(ctx.destination)
    osc.type = 'sine'
    osc.frequency.setValueAtTime(880, ctx.currentTime)
    osc.frequency.setValueAtTime(660, ctx.currentTime + 0.15)
    gain.gain.setValueAtTime(0.3, ctx.currentTime)
    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3)
    osc.start(ctx.currentTime)
    osc.stop(ctx.currentTime + 0.3)
  } catch (e) { /* silent fallback */ }
}

async function advanceStatus(order) {
  const nextIdx = statusFlow.indexOf(order.status) + 1
  if (nextIdx >= statusFlow.length) return
  const nextStatus = statusFlow[nextIdx]
  try {
    await api.patch(`/vendor/orders/${order.id}/status`, { status: nextStatus })
    fetchOrders()
  } catch (e) { alert(e.response?.data?.error || 'Failed') }
}

function waitTime(createdAt) {
  const mins = Math.floor((Date.now() - new Date(createdAt).getTime()) / 60000)
  return mins
}

function urgencyColor(mins) {
  if (mins < 10) return { bg: '#1a3a1a', border: '#22c55e', text: '#86efac' }
  if (mins < 20) return { bg: '#3a2a1a', border: '#f59e0b', text: '#fde68a' }
  return { bg: '#3a1a1a', border: '#ef4444', text: '#fca5a5' }
}

const activeCount = computed(() => orders.value.filter(o => !['delivered', 'cancelled'].includes(o.status)).length)
const pendingCount = computed(() => orders.value.filter(o => o.status === 'pending').length)
</script>

<template>
  <div class="kds-container">
    <!-- Header -->
    <header class="kds-header">
      <div class="kds-brand">UTTERLAZY <span class="kds-brand-dot">●</span> KDS</div>
      <div class="kds-stats">
        <span class="kds-stat pending">{{ pendingCount }} NEW</span>
        <span class="kds-stat active">{{ activeCount }} ACTIVE</span>
        <span class="kds-stat total">{{ orders.length }} TOTAL</span>
      </div>
      <button class="kds-sound-btn" @click="soundEnabled = !soundEnabled">
        {{ soundEnabled ? '🔊 ON' : '🔇 OFF' }}
      </button>
    </header>

    <!-- Loading -->
    <div v-if="loading" class="kds-loading">
      <div class="kds-spinner"></div>
      <p>CONNECTING TO KITCHEN...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="orders.length === 0" class="kds-empty">
      <div class="kds-empty-icon">✅</div>
      <h2>ALL CLEAR</h2>
      <p>No pending orders. Great work, chef!</p>
    </div>

    <!-- Order Grid -->
    <div v-else class="kds-grid">
      <div v-for="order in orders" :key="order.id"
        class="kds-card"
        :style="order.status === 'pending' ? { borderColor: '#ef4444', boxShadow: '0 0 20px rgba(239,68,68,0.2)' } : {}"
        @click="['pending', 'confirmed', 'preparing'].includes(order.status) && advanceStatus(order)">

        <!-- Timer + Order ID -->
        <div class="kds-card-header">
          <div class="kds-timer" :style="order.status === 'delivered' ? {} : { color: urgencyColor(waitTime(order.created_at)).text }">
            <span class="kds-timer-icon">⏱</span>
            {{ waitTime(order.created_at) }}m
          </div>
          <span class="kds-order-id">#{{ order.id?.slice(0, 6) }}</span>
          <span v-if="order.status === 'pending'" class="kds-badge kds-badge-new">NEW</span>
          <span v-else class="kds-status">{{ order.status.replace(/_/g, ' ') }}</span>
        </div>

        <!-- Restaurant Info -->
        <div class="kds-restaurant">
          <span class="kds-customer">{{ order.user?.name || 'Customer' }}</span>
          <span class="kds-payment">{{ order.payment_mode?.toUpperCase() }}</span>
        </div>

        <!-- Items -->
        <div class="kds-items">
          <div v-for="item in order.items" :key="item.id" class="kds-item">
            <span class="kds-qty">{{ item.quantity }}×</span>
            <span class="kds-name">{{ item.menu_item?.name?.toUpperCase() }}</span>
            <span v-if="item.special_instructions" class="kds-instructions">{{ item.special_instructions }}</span>
          </div>
        </div>

        <!-- Action Button -->
        <div class="kds-actions">
          <button v-if="order.status === 'pending'" class="kds-btn kds-btn-accept" @click.stop="advanceStatus(order)">
            ✅ ACCEPT ORDER
          </button>
          <button v-else-if="order.status === 'confirmed'" class="kds-btn kds-btn-cook" @click.stop="advanceStatus(order)">
            🔥 START COOKING
          </button>
          <button v-else-if="order.status === 'preparing'" class="kds-btn kds-btn-ready" @click.stop="advanceStatus(order)">
            🍽️ MARK READY
          </button>
          <span v-else-if="order.status === 'ready_for_pickup'" class="kds-ready-text">WAITING FOR RIDER</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.kds-container {
  min-height: 100vh;
  background: #0a0e1a;
  color: #e8e6e1;
  font-family: 'Space Grotesk', 'Inter', sans-serif;
  padding: 20px;
}
.kds-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 24px;
  background: #141a2b;
  border-radius: 20px;
  margin-bottom: 24px;
  border: 1px solid rgba(255,255,255,0.06);
}
.kds-brand {
  font-size: 1.8rem;
  font-weight: 900;
  letter-spacing: -1px;
}
.kds-brand-dot { color: #ff6b00; }
.kds-stats { display: flex; gap: 12px; }
.kds-stat {
  padding: 6px 16px;
  border-radius: 10px;
  font-size: 0.8rem;
  font-weight: 800;
  letter-spacing: 1px;
}
.kds-stat.pending { background: rgba(239,68,68,0.15); color: #fca5a5; }
.kds-stat.active { background: rgba(34,197,94,0.15); color: #86efac; }
.kds-stat.total { background: rgba(255,255,255,0.05); color: #9ca3af; }
.kds-sound-btn {
  background: rgba(255,255,255,0.05);
  border: 1px solid rgba(255,255,255,0.1);
  color: #e8e6e1;
  padding: 8px 16px;
  border-radius: 12px;
  cursor: pointer;
  font-weight: 700;
  font-size: 0.8rem;
}
.kds-loading, .kds-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 60vh;
  gap: 16px;
  color: #6b7280;
}
.kds-spinner {
  width: 40px;
  height: 40px;
  border: 3px solid rgba(255,107,0,0.2);
  border-top: 3px solid #ff6b00;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.kds-empty-icon { font-size: 4rem; }
.kds-empty h2 { font-size: 1.5rem; color: #86efac; font-weight: 700; }
.kds-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
  gap: 20px;
}
.kds-card {
  background: #141a2b;
  border-radius: 20px;
  padding: 24px;
  border: 1px solid rgba(255,255,255,0.06);
  cursor: pointer;
  transition: all 0.2s;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.kds-card:hover { border-color: rgba(255,107,0,0.3); transform: translateY(-2px); }
.kds-card-header {
  display: flex;
  align-items: center;
  gap: 12px;
}
.kds-timer {
  font-size: 1.8rem;
  font-weight: 900;
  display: flex;
  align-items: center;
  gap: 4px;
}
.kds-timer-icon { font-size: 1.2rem; }
.kds-order-id {
  font-size: 0.8rem;
  color: #6b7280;
  font-weight: 600;
  letter-spacing: 1px;
}
.kds-badge {
  margin-left: auto;
  padding: 4px 12px;
  border-radius: 8px;
  font-size: 0.7rem;
  font-weight: 800;
  letter-spacing: 1px;
}
.kds-badge-new { background: rgba(239,68,68,0.2); color: #fca5a5; animation: pulse 1s infinite; }
@keyframes pulse { 50% { opacity: 0.7; } }
.kds-status {
  margin-left: auto;
  font-size: 0.7rem;
  font-weight: 600;
  color: #9ca3af;
  text-transform: uppercase;
  letter-spacing: 1px;
}
.kds-restaurant {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.kds-customer { font-weight: 700; font-size: 0.95rem; }
.kds-payment {
  font-size: 0.65rem;
  font-weight: 800;
  padding: 4px 10px;
  border-radius: 6px;
  background: rgba(0,123,138,0.1);
  color: #5eead4;
  letter-spacing: 1px;
}
.kds-items { display: flex; flex-direction: column; gap: 8px; }
.kds-item {
  display: flex;
  align-items: baseline;
  gap: 8px;
  flex-wrap: wrap;
}
.kds-qty { font-weight: 900; color: #ff6b00; font-size: 1.1rem; min-width: 30px; }
.kds-name { font-weight: 700; font-size: 0.95rem; }
.kds-instructions {
  width: 100%;
  font-size: 0.75rem;
  color: #f59e0b;
  margin-left: 30px;
  font-style: italic;
}
.kds-actions { margin-top: auto; padding-top: 8px; }
.kds-btn {
  width: 100%;
  padding: 14px;
  border: none;
  border-radius: 14px;
  font-weight: 800;
  font-size: 0.9rem;
  letter-spacing: 1px;
  cursor: pointer;
  transition: all 0.2s;
}
.kds-btn:hover { transform: scale(1.02); }
.kds-btn-accept { background: linear-gradient(135deg, #dc2626, #ef4444); color: white; }
.kds-btn-cook { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
.kds-btn-ready { background: linear-gradient(135deg, #22c55e, #16a34a); color: white; }
.kds-ready-text {
  display: block;
  text-align: center;
  padding: 12px;
  font-size: 0.75rem;
  font-weight: 700;
  letter-spacing: 1px;
  color: #5eead4;
  background: rgba(0,123,138,0.1);
  border-radius: 12px;
}
</style>
