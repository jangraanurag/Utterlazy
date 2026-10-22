<template>
  <div>
    <div class="flex justify-between items-center mb-2">
      <h1 class="text-3xl font-serif font-bold text-on-surface">Ranking Monitor</h1>
      <button @click="fetchData" class="px-4 py-2 rounded-lg bg-primary/10 text-primary border border-primary/20 text-xs font-bold">
        <span class="material-symbols-outlined text-[16px] align-middle mr-1">refresh</span> Refresh
      </button>
    </div>
    <p class="text-on-surface-variant mb-8">Live restaurant ranking scores, EMA ratings, Thompson Sampling params &amp; auction metrics.</p>

    <div v-if="loading" class="py-12 text-center text-on-surface-variant">
      <span class="material-symbols-outlined text-4xl animate-spin mb-4 block">sync</span>
      <p>Loading ranking data...</p>
    </div>

    <div v-else class="overflow-x-auto rounded-2xl border border-outline-variant/30">
      <table class="w-full text-left text-sm">
        <thead class="bg-surface-container-lowest">
          <tr>
            <th class="p-4 font-bold text-on-surface-variant text-[10px] uppercase tracking-widest">Venue</th>
            <th class="p-4 font-bold text-on-surface-variant text-[10px] uppercase tracking-widest text-center">Active</th>
            <th class="p-4 font-bold text-on-surface-variant text-[10px] uppercase tracking-widest text-center">Consumer EMA</th>
            <th class="p-4 font-bold text-on-surface-variant text-[10px] uppercase tracking-widest text-center">Rider EMA</th>
            <th class="p-4 font-bold text-on-surface-variant text-[10px] uppercase tracking-widest text-center">TS α</th>
            <th class="p-4 font-bold text-on-surface-variant text-[10px] uppercase tracking-widest text-center">TS β</th>
            <th class="p-4 font-bold text-on-surface-variant text-[10px] uppercase tracking-widest text-center">Batching</th>
            <th class="p-4 font-bold text-on-surface-variant text-[10px] uppercase tracking-widest text-center">Prep Var</th>
            <th class="p-4 font-bold text-on-surface-variant text-[10px] uppercase tracking-widest text-center">Impr.</th>
            <th class="p-4 font-bold text-on-surface-variant text-[10px] uppercase tracking-widest text-center">Clicks</th>
            <th class="p-4 font-bold text-on-surface-variant text-[10px] uppercase tracking-widest text-center">Orders</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-outline-variant/20">
          <tr v-for="r in restaurants" :key="r.id" class="hover:bg-surface-container-lowest/50 transition-colors">
            <td class="p-4 font-bold">{{ r.name }}</td>
            <td class="p-4 text-center">
              <span :class="r.is_active ? 'text-green-400' : 'text-red-400'" class="text-lg">
                <span class="material-symbols-outlined">{{ r.is_active ? 'check_circle' : 'cancel' }}</span>
              </span>
            </td>
            <td class="p-4 text-center font-mono">{{ r.ema_consumer_rating ? Number(r.ema_consumer_rating).toFixed(3) : '-' }}</td>
            <td class="p-4 text-center font-mono">{{ r.ema_rider_rating ? Number(r.ema_rider_rating).toFixed(3) : '-' }}</td>
            <td class="p-4 text-center font-mono">{{ r.beta_alpha ? Number(r.beta_alpha).toFixed(3) : '-' }}</td>
            <td class="p-4 text-center font-mono">{{ r.beta_beta ? Number(r.beta_beta).toFixed(3) : '-' }}</td>
            <td class="p-4 text-center font-mono">{{ r.batching_score ? Number(r.batching_score).toFixed(3) : '-' }}</td>
            <td class="p-4 text-center font-mono">{{ r.prep_time_variance ? Number(r.prep_time_variance).toFixed(1) : '-' }}</td>
            <td class="p-4 text-center font-mono">{{ r.total_impressions || 0 }}</td>
            <td class="p-4 text-center font-mono">{{ r.total_clicks || 0 }}</td>
            <td class="p-4 text-center font-mono">{{ r.total_orders || 0 }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="error" class="mt-4 text-red-500 text-sm">{{ error }}</div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getRankingMonitor } from '../api.js'

const restaurants = ref([])
const loading = ref(true)
const error = ref(null)

const fetchData = async () => {
  loading.value = true
  error.value = null
  try {
    const data = await getRankingMonitor()
    restaurants.value = data
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

onMounted(fetchData)
</script>
