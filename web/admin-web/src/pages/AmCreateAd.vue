<template>
  <div class="p-8 max-w-2xl">
    <div class="mb-10">
      <h1 class="font-display text-5xl font-bold tracking-tighter text-white mb-2">Create <span class="text-primary">Ad</span></h1>
      <p class="font-display text-xs font-bold uppercase tracking-[0.3em] text-on-surface-variant opacity-60">New advertising campaign</p>
    </div>

    <div v-if="success" class="mb-6 p-4 rounded-xl bg-green-500/10 border border-green-500/20 text-green-400 text-sm">{{ success }}</div>
    <div v-if="error" class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm">{{ error }}</div>

    <form @submit.prevent="handleSubmit" class="bg-surface rounded-3xl p-10 border border-white/5 space-y-6">
      <div>
        <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Restaurant</label>
        <select v-model="form.restaurant_id" required class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white">
          <option value="">Select restaurant</option>
          <option v-for="r in restaurants" :key="r.id" :value="r.id">{{ r.name }}</option>
        </select>
      </div>

      <div>
        <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Ad Type</label>
        <select v-model="form.ad_type" required class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white">
          <option value="ppc">PPC (Pay Per Click)</option>
          <option value="card">Card</option>
        </select>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Daily Budget</label>
          <input v-model="form.daily_budget" type="number" step="0.01" min="0" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white"/>
        </div>
        <div>
          <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Weekly Budget</label>
          <input v-model="form.weekly_budget" type="number" step="0.01" min="0" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white"/>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Duration (weeks)</label>
          <input v-model="form.duration_weeks" type="number" min="1" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white"/>
        </div>
        <div>
          <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Placement</label>
          <select v-model="form.placement" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white">
            <option value="">Select placement</option>
            <option value="listings">Listings</option>
            <option value="search">Search</option>
            <option value="both">Both</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Start Date</label>
          <input v-model="form.start_date" type="date" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white"/>
        </div>
        <div>
          <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">End Date</label>
          <input v-model="form.end_date" type="date" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white"/>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">CPC</label>
          <input v-model="form.cpc" type="number" step="0.01" min="0" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white"/>
        </div>
        <div>
          <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Bid Amount</label>
          <input v-model="form.bid_amount" type="number" step="0.01" min="0" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white"/>
        </div>
      </div>

      <div>
        <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Target Customers</label>
        <select v-model="form.target_customers" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white">
          <option value="all">All Customers</option>
          <option value="new_users">New Users</option>
        </select>
      </div>

      <div>
        <label class="flex items-center gap-3 cursor-pointer mb-4">
          <input v-model="form.all_day" type="checkbox" class="w-4 h-4 rounded bg-white/5 border border-white/10 text-primary" />
          <span class="text-xs uppercase tracking-widest text-white/50">All Day</span>
        </label>
      </div>

      <div v-if="!form.all_day" class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Time From</label>
          <input v-model="form.time_from" type="time" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white"/>
        </div>
        <div>
          <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Time To</label>
          <input v-model="form.time_to" type="time" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white"/>
        </div>
      </div>

      <div class="flex gap-4 pt-4">
        <router-link to="/am" class="flex-1 py-3 rounded-xl bg-white/5 text-white/70 text-center hover:bg-white/10 text-sm font-bold">Cancel</router-link>
        <button type="submit" :disabled="saving" class="flex-1 py-3 rounded-xl sovereign-gradient text-white font-bold text-sm disabled:opacity-50">{{ saving ? 'Submitting...' : 'Submit for Approval' }}</button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { amCreateAd, amGetMyRestaurants } from '../api'

const router = useRouter()
const route = useRoute()
const restaurants = ref([])
const saving = ref(false)
const success = ref('')
const error = ref('')

const form = reactive({
  restaurant_id: route.query.restaurant_id || '',
  ad_type: 'ppc',
  daily_budget: '',
  weekly_budget: '',
  duration_weeks: '',
  start_date: '',
  end_date: '',
  placement: '',
  cpc: '',
  bid_amount: '',
  target_customers: 'all',
  all_day: false,
  time_from: '',
  time_to: ''
})

onMounted(async () => {
  try {
    restaurants.value = await amGetMyRestaurants()
  } catch (e) { console.error(e) }
})

const handleSubmit = async () => {
  saving.value = true; success.value = ''; error.value = ''
  try {
    await amCreateAd(form)
    success.value = 'Ad created successfully! Pending restaurant approval.'
    setTimeout(() => router.push('/am'), 1500)
  } catch (e) {
    error.value = e.errors ? Object.values(e.errors).flat().join(' ') : (e.message || 'Failed to create ad')
  }
  finally { saving.value = false }
}
</script>
