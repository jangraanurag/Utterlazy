<template>
  <div class="p-8 max-w-2xl">
    <div class="mb-10">
      <h1 class="font-display text-5xl font-bold tracking-tighter text-white mb-2">Create <span class="text-primary">Offer</span></h1>
      <p class="font-display text-xs font-bold uppercase tracking-[0.3em] text-on-surface-variant opacity-60">New promotional offer</p>
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
        <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Offer Title</label>
        <input v-model="form.title" required class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white" placeholder="e.g., Weekend Special"/>
      </div>

      <div>
        <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Description</label>
        <textarea v-model="form.description" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white" rows="3" placeholder="Offer description..."></textarea>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Discount Type</label>
          <select v-model="form.type" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white">
            <option value="percentage">Percentage</option>
            <option value="flat">Flat Amount</option>
          </select>
        </div>
        <div>
          <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Discount Value</label>
          <input v-model="form.discount_value" type="number" step="0.01" min="0" required class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white"/>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Max Discount</label>
          <input v-model="form.max_discount" type="number" step="0.01" min="0" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white" placeholder="No cap"/>
        </div>
        <div>
          <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Min Order Value</label>
          <input v-model="form.min_order_value" type="number" step="0.01" min="0" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white" placeholder="0"/>
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
import { amCreateOffer, amGetMyRestaurants } from '../api'

const router = useRouter()
const route = useRoute()
const restaurants = ref([])
const saving = ref(false)
const success = ref('')
const error = ref('')

const form = reactive({
  restaurant_id: route.query.restaurant_id || '',
  type: 'percentage',
  title: '',
  description: '',
  discount_value: '',
  max_discount: '',
  min_order_value: '',
  start_date: '',
  end_date: ''
})

onMounted(async () => {
  try {
    restaurants.value = await amGetMyRestaurants()
  } catch (e) { console.error(e) }
})

const handleSubmit = async () => {
  saving.value = true; success.value = ''; error.value = ''
  try {
    await amCreateOffer(form)
    success.value = 'Offer created successfully! Pending restaurant approval.'
    setTimeout(() => router.push('/am'), 1500)
  } catch (e) {
    error.value = e.errors ? Object.values(e.errors).flat().join(' ') : (e.message || 'Failed to create offer')
  }
  finally { saving.value = false }
}
</script>
