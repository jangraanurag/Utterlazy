<template>
  <div class="h-screen flex flex-col bg-[#0a0e1a]">
    <!-- Header -->
    <header class="flex items-center justify-between px-8 py-4 bg-[#141a2b] border-b border-white/5">
      <div class="flex items-center gap-6">
        <h1 class="font-display text-xl font-bold text-white tracking-tighter">WAR ROOM</h1>
        <div class="flex gap-3">
          <span class="px-3 py-1 rounded-lg bg-orange-500/10 text-orange-400 text-xs font-bold">{{ stats.total_active }} ACTIVE</span>
          <span class="px-3 py-1 rounded-lg bg-yellow-500/10 text-yellow-400 text-xs font-bold">{{ stats.waiting_pickup }} WAITING</span>
          <span class="px-3 py-1 rounded-lg bg-green-500/10 text-green-400 text-xs font-bold">{{ stats.out_for_delivery }} ON WAY</span>
          <span class="px-3 py-1 rounded-lg bg-red-500/10 text-red-400 text-xs font-bold">{{ stats.unassigned }} UNASSIGNED</span>
        </div>
      </div>
      <div class="flex items-center gap-4 text-xs text-white/40">
        <div class="flex items-center gap-2">
          <span class="w-3 h-3 rounded-full bg-orange-500"></span> Restaurant
          <span class="w-3 h-3 rounded-full bg-blue-500"></span> Rider
          <span class="w-3 h-3 rounded-full bg-green-500"></span> Customer
        </div>
        <span>🔄 Auto-refreshes every 15s</span>
        <button @click="fetchData" class="px-3 py-1 rounded-lg bg-white/5 hover:bg-white/10 text-white/60 hover:text-white transition-all">REFRESH</button>
      </div>
    </header>

    <!-- Map -->
    <div ref="mapContainer" class="flex-1 relative">
      <div v-if="loading" class="absolute inset-0 flex items-center justify-center bg-[#0a0e1a]/80 z-[1000]">
        <div class="text-center">
          <div class="w-10 h-10 border-2 border-orange-500/30 border-t-orange-500 rounded-full animate-spin mx-auto mb-4"></div>
          <p class="text-white/50 text-sm font-bold uppercase tracking-widest">Loading War Room...</p>
        </div>
      </div>
    </div>

    <!-- Order Info Panel -->
    <div v-if="selectedOrder" class="fixed right-4 top-24 w-96 bg-[#141a2b] rounded-2xl border border-white/10 p-6 z-[1000] shadow-2xl">
      <div class="flex justify-between items-start mb-4">
        <div>
          <p class="text-xs text-white/40 font-bold uppercase tracking-widest">Order #{{ selectedOrder.id?.slice(0,8) }}</p>
          <p class="text-lg font-bold text-white mt-1">{{ selectedOrder.restaurant?.name }}</p>
        </div>
        <button @click="selectedOrder = null" class="text-white/40 hover:text-white text-xl">&times;</button>
      </div>
      <div class="space-y-3 text-sm">
        <div class="flex justify-between">
          <span class="text-white/40">Status</span>
          <span class="font-bold" :class="statusColor(selectedOrder.status)">{{ selectedOrder.status.replace(/_/g,' ') }}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-white/40">Amount</span>
          <span class="font-bold text-orange-400">₹{{ selectedOrder.total }}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-white/40">Wait Time</span>
          <span class="font-bold" :class="selectedOrder.wait_time_minutes > 20 ? 'text-red-400' : 'text-green-400'">{{ selectedOrder.wait_time_minutes }} min</span>
        </div>
        <div v-if="selectedOrder.rider" class="border-t border-white/5 pt-3 mt-3">
          <p class="text-xs text-white/40 font-bold uppercase tracking-widest mb-2">Rider</p>
          <p class="text-white font-bold">{{ selectedOrder.rider.name }}</p>
          <p class="text-white/60 text-xs">{{ selectedOrder.rider.vehicle }} • {{ selectedOrder.rider.phone }}</p>
        </div>
        <div v-else class="border-t border-white/5 pt-3 mt-3">
          <p class="text-red-400 font-bold text-xs">⚠️ No rider assigned</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import { getOrderMap } from '../api'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const mapContainer = ref(null)
const loading = ref(true)
const orders = ref([])
const stats = ref({})
const selectedOrder = ref(null)
let map = null
let markersLayer = null
let pollInterval = null

const statusOrder = ['pending', 'confirmed', 'preparing', 'ready_for_pickup', 'picked_up', 'out_for_delivery', 'delivered']
const statusColor = (s) => ({
  pending: 'text-red-400', confirmed: 'text-yellow-400', preparing: 'text-orange-400',
  ready_for_pickup: 'text-blue-400', out_for_delivery: 'text-green-400', delivered: 'text-emerald-400',
}[s] || 'text-white/60')

function getMarkerIcon(type, status) {
  const colors = { restaurant: '#ff6b00', rider: '#3b82f6', customer: '#22c55e' }
  const s = type === 'restaurant' ? colors.restaurant : type === 'rider' ? colors.rider : colors.customer
  const size = type === 'rider' ? [20, 20] : [14, 14]

  return L.divIcon({
    className: '',
    html: `<div style="width:${size[0]}px;height:${size[1]}px;background:${s};border:2px solid white;border-radius:50%;box-shadow:0 0 10px ${s}80;${type === 'rider' ? 'animation:pulse 2s infinite;' : ''}"></div>`,
    iconSize: size,
    iconAnchor: [size[0]/2, size[1]/2],
  })
}

async function fetchData() {
  try {
    const data = await getOrderMap()
    orders.value = data.orders || []
    stats.value = data.stats || { total_active: 0, waiting_pickup: 0, out_for_delivery: 0, unassigned: 0 }
    updateMarkers()
  } catch (e) { console.error(e) }
  finally { loading.value = false }
}

function updateMarkers() {
  if (!map || !markersLayer) return
  markersLayer.clearLayers()

  orders.value.forEach(order => {
    // Restaurant marker
    if (order.restaurant?.lat && order.restaurant?.lng) {
      markersLayer.addLayer(L.marker([order.restaurant.lat, order.restaurant.lng], {
        icon: getMarkerIcon('restaurant'),
      }).bindPopup(`<b>${order.restaurant.name}</b><br>Status: ${order.status}`))
    }

    // Rider marker
    if (order.rider?.lat && order.rider?.lng) {
      markersLayer.addLayer(L.marker([order.rider.lat, order.rider.lng], {
        icon: getMarkerIcon('rider'),
      }).bindPopup(`<b>${order.rider.name}</b><br>${order.rider.vehicle}`))
    }

    // Customer marker
    if (order.customer?.lat && order.customer?.lng) {
      markersLayer.addLayer(L.marker([order.customer.lat, order.customer.lng], {
        icon: getMarkerIcon('customer'),
      }).bindPopup(`<b>${order.customer.name}</b>`))
    }

    // Line between restaurant and customer
    if (order.restaurant?.lat && order.customer?.lat) {
      const color = order.status === 'out_for_delivery' ? '#22c55e' : '#ff6b0060'
      markersLayer.addLayer(L.polyline([
        [order.restaurant.lat, order.restaurant.lng],
        [order.customer.lat, order.customer.lng],
      ], { color, weight: 1.5, opacity: order.status === 'out_for_delivery' ? 0.8 : 0.3, dashArray: '5, 10' }))
    }
  })
}

onMounted(async () => {
  await fetchData()
  await nextTick()
  if (!mapContainer.value) return

  map = L.map(mapContainer.value, {
    center: [28.7942, 76.1384],
    zoom: 12,
    zoomControl: true,
    attributionControl: false,
  })

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
  }).addTo(map)

  markersLayer = L.layerGroup().addTo(map)

  // Click on map to close panel
  map.on('click', () => { selectedOrder.value = null })

  // Use map click to select nearest order
  map.on('click', (e) => {
    const nearest = orders.value.reduce((best, order) => {
      const olat = order.restaurant?.lat || 0
      const olng = order.restaurant?.lng || 0
      const dist = Math.sqrt((olat - e.latlng.lat)**2 + (olng - e.latlng.lng)**2)
      return dist < best.dist ? { order, dist } : best
    }, { order: null, dist: Infinity })
    if (nearest.order && nearest.dist < 0.05) {
      selectedOrder.value = nearest.order
    }
  })

  updateMarkers()
  pollInterval = setInterval(fetchData, 15000)
})

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval)
  if (map) { map.remove(); map = null }
})
</script>

<style>
/* Leaflet container needs explicit height */
.leaflet-container { width: 100%; height: 100%; }
@keyframes pulse { 50% { transform: scale(1.3); opacity: 0.7; } }
</style>
