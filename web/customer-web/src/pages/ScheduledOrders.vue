<template>
  <div class="min-h-screen bg-background p-8">
    <div class="max-w-3xl mx-auto">
      <h1 class="font-serif text-3xl font-bold mb-8">Scheduled Orders</h1>
      <div class="space-y-4">
        <div v-for="order in scheduledOrders" :key="order.id" class="bg-surface rounded-2xl border border-outline-variant/30 p-6">
          <div class="flex justify-between items-start mb-3">
            <div>
              <h3 class="font-bold text-lg">{{ order.restaurant }}</h3>
              <p class="text-sm text-on-surface-variant">{{ order.items }}</p>
            </div>
            <span class="px-3 py-1 bg-teal-50 text-teal-700 rounded-full text-xs font-bold uppercase">{{ order.status }}</span>
          </div>
          <div class="flex items-center gap-2 mb-3">
            <span class="material-symbols-outlined text-primary text-[18px]">schedule</span>
            <span class="text-primary font-semibold text-sm">{{ order.scheduledAt }}</span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-xl font-bold">{{ order.total }}</span>
            <button class="px-4 py-2 rounded-lg bg-red-50 text-red-600 text-sm font-bold">Cancel</button>
          </div>
        </div>
      </div>
      <div v-if="scheduledOrders.length === 0" class="bg-surface rounded-2xl border border-outline-variant/30 p-12 text-center">
        <span class="material-symbols-outlined text-6xl text-on-surface-variant opacity-30 block mb-4">schedule_send</span>
        <h2 class="text-xl font-bold mb-2">No Scheduled Orders</h2>
        <p class="text-on-surface-variant">Schedule your next meal in advance!</p>
      </div>
    </div>
  </div>
</template>

<script setup>
const scheduledOrders = [
  { id: 1, restaurant: 'The Saffron Palace', items: '2x Butter Chicken, 1x Naan', scheduledAt: 'Tomorrow, 12:30 PM', total: '₹485', status: 'scheduled' },
  { id: 2, restaurant: 'Dragon Wok', items: '1x Veg Biryani, 1x Raita', scheduledAt: 'Jun 15, 7:00 PM', total: '₹320', status: 'scheduled' },
]
</script>
