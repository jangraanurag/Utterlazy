<template>
  <div class="p-8">
    <div class="mb-12">
      <h1 class="font-display text-5xl font-bold tracking-tighter text-white mb-2">My <span class="text-primary">Restaurants</span></h1>
      <p class="font-display text-xs font-bold uppercase tracking-[0.3em] text-on-surface-variant opacity-60">{{ restaurants.length }} venues under management</p>
    </div>

    <div v-if="loading" class="py-32 text-center">
      <div class="w-12 h-12 border-2 border-primary/20 border-t-primary rounded-full animate-spin mx-auto mb-6"></div>
      <p class="font-display font-bold uppercase tracking-widest text-[10px]">Loading venues...</p>
    </div>

    <div v-else-if="restaurants.length === 0" class="text-center py-20 text-white/50">
      <p class="text-xl">No restaurants assigned to you yet</p>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-for="r in restaurants" :key="r.id"
        class="bg-surface rounded-2xl border border-white/5 p-6 hover:border-primary/20 transition-all duration-300">
        <div class="flex items-start justify-between mb-4">
          <div class="flex-1 min-w-0">
            <h3 class="text-lg font-bold text-white truncate">{{ r.name }}</h3>
            <div class="flex items-center gap-2 mt-1">
              <span class="text-yellow-500 text-sm">★</span>
              <span class="text-white/70 text-sm font-bold">{{ r.rating || 'N/A' }}</span>
              <span v-if="r.pending_approvals > 0" class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-orange-500/20 text-orange-400">{{ r.pending_approvals }} pending</span>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-5">
          <div class="bg-white/5 rounded-xl p-3 text-center">
            <p class="text-xl font-bold text-white">{{ r.active_ads || 0 }}</p>
            <p class="text-[9px] uppercase tracking-widest text-white/40">Active Ads</p>
          </div>
          <div class="bg-white/5 rounded-xl p-3 text-center">
            <p class="text-xl font-bold text-white">{{ r.active_offers || 0 }}</p>
            <p class="text-[9px] uppercase tracking-widest text-white/40">Active Offers</p>
          </div>
        </div>

        <div class="flex flex-wrap gap-2">
          <router-link :to="'/am/create-ad?restaurant_id=' + r.id" class="flex-1 py-2.5 px-3 rounded-xl sovereign-gradient text-white font-bold text-[10px] uppercase tracking-widest text-center">Create Ad</router-link>
          <router-link :to="'/am/create-offer?restaurant_id=' + r.id" class="flex-1 py-2.5 px-3 rounded-xl bg-white/5 text-white/70 text-[10px] uppercase tracking-widest font-bold text-center hover:bg-white/10">Create Offer</router-link>
          <router-link to="/am/submissions" class="flex-1 py-2.5 px-3 rounded-xl bg-white/5 text-white/70 text-[10px] uppercase tracking-widest font-bold text-center hover:bg-white/10">Submissions</router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { amGetMyRestaurants } from '../api'

const restaurants = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    restaurants.value = await amGetMyRestaurants()
  } catch (e) { console.error(e) }
  finally { loading.value = false }
})
</script>
