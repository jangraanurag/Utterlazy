<template>
  <div class="min-h-screen bg-background flex items-center justify-center p-8">
    <div v-if="checking" class="text-on-surface-variant">Checking...</div>
    <div v-else-if="!enabled" class="text-center">
      <span class="material-symbols-outlined text-6xl text-on-surface-variant block mb-4">lock</span>
      <h1 class="font-serif text-2xl font-bold mb-2">Not Available</h1>
      <p class="text-on-surface-variant">Membership is currently disabled. Check back later!</p>
    </div>
    <div v-else class="w-full max-w-4xl">
      <h1 class="font-serif text-3xl font-bold mb-8">UtterLazy Membership</h1>
      <div class="bg-surface rounded-2xl border border-outline-variant/30 p-8 mb-8 text-center">
        <span class="material-symbols-outlined text-6xl text-on-surface-variant mb-4 block">card_membership</span>
        <h2 v-if="currentPlan" class="text-xl font-bold mb-2">{{ currentPlan }} Plan</h2>
        <h2 v-else class="text-xl font-bold mb-2">Free Plan</h2>
        <p class="text-on-surface-variant">{{ currentPlan ? 'Active' : 'Upgrade for exclusive benefits' }}</p>
      </div>
      <div class="grid grid-cols-2 gap-6">
        <div class="bg-surface rounded-2xl border border-outline-variant/30 p-8">
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-2xl font-bold text-teal-600">Plus</h3>
            <span class="px-3 py-1 bg-teal-50 text-teal-600 rounded-full text-xs font-bold">₹299/mo</span>
          </div>
          <ul class="space-y-3 mb-8">
            <li v-for="b in plusBenefits" :key="b" class="flex items-center gap-2 text-sm">
              <span class="material-symbols-outlined text-teal-600 text-[18px]">check_circle</span> {{ b }}
            </li>
          </ul>
          <button class="w-full py-3 rounded-xl bg-teal-600 text-white font-bold" @click="subscribe('plus')">Subscribe</button>
        </div>
        <div class="bg-surface rounded-2xl border-2 border-primary p-8 relative">
          <span class="absolute -top-3 right-4 px-3 py-1 bg-primary text-white rounded-full text-[10px] font-bold uppercase">Best Value</span>
          <div class="flex justify-between items-center mb-4">
            <h3 class="text-2xl font-bold text-primary">Pro</h3>
            <span class="px-3 py-1 bg-primary/10 text-primary rounded-full text-xs font-bold">₹499/mo</span>
          </div>
          <ul class="space-y-3 mb-8">
            <li v-for="b in proBenefits" :key="b" class="flex items-center gap-2 text-sm">
              <span class="material-symbols-outlined text-primary text-[18px]">check_circle</span> {{ b }}
            </li>
          </ul>
          <button class="w-full py-3 rounded-xl sovereign-gradient text-white font-bold" @click="subscribe('pro')">Subscribe</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getMembership, subscribeMembership } from '../api'

const checking = ref(true)
const enabled = ref(false)
const currentPlan = ref(null)

const plusBenefits = ['Free delivery on all orders', 'Priority customer support', '50 extra UtterLazy Coins monthly', 'Exclusive Plus offers']
const proBenefits = ['Everything in Plus', '100 extra UtterLazy Coins monthly', 'Early access to new restaurants', 'Exclusive Pro-only deals', 'Priority delivery during peak hours', 'Free dessert on orders above ₹500']

onMounted(async () => {
  try {
    const data = await getMembership()
    enabled.value = data.enabled === true
    currentPlan.value = data.membership?.plan || null
  } catch (_) { enabled.value = false }
  checking.value = false
})

async function subscribe(plan) {
  try {
    const data = await subscribeMembership(plan)
    currentPlan.value = plan
    alert(data.message || 'Subscribed!')
  } catch (e) { alert(e.message || 'Failed') }
}
</script>
