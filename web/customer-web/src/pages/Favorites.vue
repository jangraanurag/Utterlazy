<template>
  <div class="bg-background text-on-surface min-h-screen">
    <!-- Top Nav -->
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
        <h1 class="font-serif text-4xl font-bold text-on-surface mb-2">Your Favorites</h1>
        <p class="text-on-surface-variant font-medium">The artisans you love, all in one place.</p>
      </header>

      <!-- Loading State -->
      <div v-if="loading" class="py-20 text-center text-on-surface-variant">
        <span class="material-symbols-outlined text-5xl animate-spin block mb-4">progress_activity</span>
        <p class="text-lg">Loading your favorites...</p>
      </div>

      <!-- Favorites Grid -->
      <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <article v-for="fav in favorites" :key="fav.id" class="group bg-surface-container-lowest rounded-2xl overflow-hidden border border-outline-variant/30 hover:shadow-xl transition-all duration-300 hover:-translate-y-2">
          <div class="relative h-48 overflow-hidden">
            <img :src="fav.logo_url || 'https://images.unsplash.com/photo-1512152272829-e3139592d56f?auto=format&fit=crop&w=800&q=80'" :alt="fav.name" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110" />
            <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
            <button @click="toggleFav(fav.id)" class="absolute top-4 right-4 w-10 h-10 rounded-full bg-white/90 flex items-center justify-center hover:scale-110 transition-transform shadow-md">
              <span class="material-symbols-outlined text-red-500" style="font-variation-settings: 'FILL' 1;">favorite</span>
            </button>
          </div>
          <div class="p-6">
            <h2 class="font-serif text-xl font-bold text-on-surface mb-1">{{ fav.name }}</h2>
            <p class="text-on-surface-variant text-sm line-clamp-2 mb-4">{{ fav.description }}</p>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-1 bg-surface-container-high px-3 py-1 rounded-full">
                <span class="material-symbols-outlined text-tertiary text-sm" style="font-variation-settings: 'FILL' 1;">star</span>
                <span class="font-bold text-sm">4.9</span>
              </div>
              <router-link :to="'/restaurant/' + fav.id" class="text-primary font-bold text-sm hover:underline flex items-center gap-1">
                Order Again <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
              </router-link>
            </div>
          </div>
        </article>
      </div>

      <!-- Empty state after all removed -->
      <div v-if="!loading && favorites.length === 0" class="py-20 text-center">
        <div class="w-24 h-24 rounded-full bg-surface-container-high mx-auto flex items-center justify-center mb-6">
          <span class="material-symbols-outlined text-5xl text-on-surface-variant opacity-50">favorite</span>
        </div>
        <h2 class="font-serif text-2xl font-bold text-on-surface mb-2">No favorites yet</h2>
        <p class="text-on-surface-variant mb-8">Tap the heart icon on any restaurant to save them here.</p>
        <router-link to="/" class="primary-gradient text-on-primary font-bold py-3 px-8 rounded-xl shadow-lg shadow-primary/20 inline-flex items-center gap-2">
          <span class="material-symbols-outlined">explore</span> Discover Artisans
        </router-link>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getRestaurants } from '../api'

const favorites = ref([])
const loading = ref(true)

onMounted(async () => {
  try {
    // Load saved favorites from localStorage
    const savedFavIds = JSON.parse(localStorage.getItem('utterlazy_favorites') || '[]')
    const allRestaurants = await getRestaurants()
    
    if (savedFavIds.length > 0) {
      favorites.value = allRestaurants.filter(r => savedFavIds.includes(r.id))
    } else {
      // If no favorites saved yet, default to first 2 restaurants as suggestions
      favorites.value = allRestaurants.slice(0, 2)
      localStorage.setItem('utterlazy_favorites', JSON.stringify(favorites.value.map(r => r.id)))
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
})

const toggleFav = (id) => {
  favorites.value = favorites.value.filter(f => f.id !== id)
  localStorage.setItem('utterlazy_favorites', JSON.stringify(favorites.value.map(r => r.id)))
}
</script>
