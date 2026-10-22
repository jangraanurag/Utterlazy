<template>
  <div class="min-h-screen bg-background p-8">
    <div class="max-w-2xl mx-auto">
      <h1 class="font-serif text-3xl font-bold mb-8">Notification Settings</h1>
      <div class="space-y-4">
        <div v-for="pref in preferences" :key="pref.key" class="bg-surface rounded-2xl border border-outline-variant/30 p-6 flex items-center justify-between">
          <div>
            <h3 class="font-bold">{{ pref.title }}</h3>
            <p class="text-sm text-on-surface-variant">{{ pref.description }}</p>
          </div>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" v-model="pref.enabled" class="sr-only peer">
            <div class="w-11 h-6 bg-stone-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-stone-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
          </label>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive } from 'vue'
const preferences = reactive([
  { key: 'order_updates', title: 'Order Updates', description: 'Get notified about order status changes', enabled: true },
  { key: 'offers', title: 'Offers & Deals', description: 'Receive notifications about offers and discounts', enabled: true },
  { key: 'promotions', title: 'Promotions', description: 'Get updates about promotions and campaigns', enabled: true },
  { key: 'new_restaurants', title: 'New Restaurants', description: 'Be notified when new restaurants join UtterLazy', enabled: false },
  { key: 'rating_reminders', title: 'Rating Reminders', description: 'Reminders to rate your orders', enabled: true },
])
</script>
