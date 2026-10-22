<template>
  <div>
    <h1 class="text-3xl font-serif font-bold text-on-surface mb-2">Rescue Center</h1>
    <p class="text-on-surface-variant mb-8">Monitor abandoned carts and trigger rescue flows with auto-generated discount codes.</p>

    <div v-if="loading" class="py-12 text-center text-on-surface-variant">
      <span class="material-symbols-outlined text-4xl animate-spin mb-4 block">sync</span>
      <p>Fetching abandoned orders...</p>
    </div>

    <div v-else-if="orders.length === 0" class="bg-surface-container-lowest p-12 text-center rounded-xl border border-outline-variant/30 shadow-sm">
      <span class="material-symbols-outlined text-5xl text-primary opacity-50 mb-4 block">check_circle</span>
      <h3 class="text-xl font-bold text-on-surface mb-2">No abandoned orders</h3>
      <p class="text-on-surface-variant">All checkouts are flowing smoothly.</p>
    </div>

    <div v-else class="bg-surface-container-lowest rounded-xl border border-outline-variant/30 shadow-sm overflow-hidden">
      <table class="w-full text-left">
        <thead class="bg-surface-container-low text-on-surface-variant text-xs uppercase font-bold tracking-widest border-b border-outline-variant/30">
          <tr>
            <th class="px-6 py-4">Time Abandoned</th>
            <th class="px-6 py-4">User ID / Restaurant</th>
            <th class="px-6 py-4">Value at Risk</th>
            <th class="px-6 py-4">Rescue Code</th>
            <th class="px-6 py-4">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-outline-variant/30">
          <tr v-for="order in orders" :key="order.id" class="hover:bg-surface-container-low/50 transition-colors">
            <td class="px-6 py-4">
              <div class="text-sm font-medium">{{ new Date(order.created_at).toLocaleString() }}</div>
              <div class="text-xs text-error font-bold mt-1">15+ mins ago</div>
            </td>
            <td class="px-6 py-4">
              <div class="text-sm font-bold text-primary">{{ order.user_id.substring(0,8) }}...</div>
              <div class="text-xs text-on-surface-variant mt-1">{{ order.restaurant?.name || 'Unknown Restaurant' }}</div>
            </td>
            <td class="px-6 py-4">
              <div class="text-sm font-bold">₹{{ parseFloat(order.grand_total).toFixed(2) }}</div>
              <div class="text-xs text-on-surface-variant mt-1">{{ order.items?.length || 0 }} items</div>
            </td>
            <td class="px-6 py-4">
              <span class="bg-primary/10 text-primary font-mono font-bold px-3 py-1 rounded border border-primary/20">
                {{ order.rescue_discount_code }}
              </span>
            </td>
            <td class="px-6 py-4">
              <button class="bg-primary text-white text-xs font-bold px-4 py-2 rounded-lg hover:bg-primary/90 transition-colors shadow-sm">
                Trigger SMS
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getRescueOrders } from '../api'

const loading = ref(true)
const orders = ref([])

onMounted(async () => {
  try {
    orders.value = await getRescueOrders()
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
})
</script>
