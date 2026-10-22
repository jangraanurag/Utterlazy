<template>
  <div class="bg-background text-on-surface min-h-screen">
    <nav class="fixed top-0 left-0 w-full flex justify-between items-center px-8 py-4 bg-[#fdf9f4]/60 dark:bg-stone-950/60 backdrop-blur-xl z-50">
      <div class="flex items-center gap-6">
        <router-link to="/" class="font-serif text-3xl italic font-bold text-[#c04828]">UtterLazy</router-link>
        <router-link to="/" class="flex items-center gap-1 text-on-surface-variant hover:text-primary transition-colors font-medium">
          <span class="material-symbols-outlined text-[18px]">arrow_back</span> Back to Home
        </router-link>
      </div>
    </nav>

    <main class="pt-28 px-8 max-w-4xl mx-auto pb-16">
      <header class="mb-10">
        <h1 class="font-serif text-4xl font-bold text-on-surface mb-2">Compare Restaurants</h1>
        <p class="text-on-surface-variant font-medium">Select restaurants to compare side by side.</p>
      </header>

      <div v-if="loading" class="py-20 text-center text-on-surface-variant">
        <span class="material-symbols-outlined text-5xl animate-spin block mb-4">progress_activity</span>
        <p class="text-lg">Loading restaurants...</p>
      </div>

      <div v-else>
        <div class="mb-8">
          <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">Select Restaurants</label>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <label v-for="r in allRestaurants" :key="r.id" class="flex items-center gap-3 p-4 rounded-xl border cursor-pointer transition-all"
              :class="selectedIds.includes(r.id) ? 'border-primary bg-primary/5' : 'border-outline-variant/30 bg-surface hover:border-outline-variant'">
              <input type="checkbox" :value="r.id" v-model="selectedIds" class="accent-[#c04828] w-4 h-4" />
              <div class="flex items-center gap-3">
                <img :src="r.logo_url || 'https://images.unsplash.com/photo-1512152272829-e3139592d56f?auto=format&fit=crop&w=100&q=80'" class="w-10 h-10 rounded-full object-cover" />
                <div>
                  <p class="font-bold text-sm">{{ r.name }}</p>
                  <p class="text-xs text-on-surface-variant">{{ r.cuisine || 'Multi-cuisine' }}</p>
                </div>
              </div>
            </label>
          </div>
        </div>

        <div v-if="selected.length >= 2" class="overflow-x-auto">
          <table class="w-full border-collapse">
            <thead>
              <tr class="bg-surface-container-high">
                <th class="text-left p-4 text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">Feature</th>
                <th v-for="r in selected" :key="r.id" class="text-center p-4">
                  <div class="flex flex-col items-center gap-2">
                    <img :src="r.logo_url || ''" class="w-12 h-12 rounded-full object-cover" />
                    <span class="font-bold text-sm">{{ r.name }}</span>
                  </div>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="feat in features" :key="feat.key" class="border-t border-outline-variant/20">
                <td class="p-4 text-sm font-medium text-on-surface-variant">{{ feat.label }}</td>
                <td v-for="r in selected" :key="r.id" class="p-4 text-center text-sm font-medium">
                  {{ feat.getValue(r) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="py-12 text-center text-on-surface-variant">
          <span class="material-symbols-outlined text-5xl block mb-4 opacity-30">compare_arrows</span>
          <p>Select at least 2 restaurants to compare.</p>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { getRestaurants } from '../api'

const allRestaurants = ref([])
const selectedIds = ref([])
const loading = ref(true)

const selected = computed(() => allRestaurants.value.filter(r => selectedIds.value.includes(r.id)))

const features = [
  { key: 'rating', label: 'Rating', getValue: r => r.rating ? `⭐ ${r.rating}` : 'N/A' },
  { key: 'delivery_time', label: 'Delivery Time', getValue: r => r.delivery_time || 'N/A' },
  { key: 'cuisine', label: 'Cuisine', getValue: r => r.cuisine || 'Multi-cuisine' },
  { key: 'description', label: 'Description', getValue: r => r.description || 'N/A' },
]

onMounted(async () => {
  try {
    allRestaurants.value = await getRestaurants()
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
})
</script>
