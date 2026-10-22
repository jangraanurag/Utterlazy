<template>
  <div class="bg-background text-on-surface min-h-screen">
    <nav class="fixed top-0 left-0 w-full flex items-center px-8 py-4 bg-[#fdf9f4]/60 backdrop-blur-xl z-50 gap-4">
      <router-link to="/" class="font-serif text-3xl italic font-bold text-primary shrink-0">UtterLazy</router-link>
      <div class="flex-1 max-w-2xl relative">
        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant">search</span>
        <input ref="searchInput" v-model="query" @input="handleSearch" type="text" placeholder="Search restaurants, dishes, cuisines..."
          class="w-full pl-12 pr-16 py-3 rounded-xl bg-surface-container border border-outline-variant/30 text-on-surface font-medium focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" autofocus />
        <div class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-1">
          <button v-if="query" @click="query = ''; results = null" class="material-symbols-outlined text-on-surface-variant hover:text-primary text-sm">close</button>
          <button @click="toggleVoiceSearch" class="material-symbols-outlined transition-all"
            :class="isListening ? 'text-primary animate-pulse scale-110' : 'text-on-surface-variant hover:text-primary'"
            :title="isListening ? 'Listening...' : 'Voice search'">
            {{ isListening ? 'mic' : 'mic_none' }}
          </button>
        </div>
      </div>
    </nav>

    <main class="pt-24 px-8 max-w-4xl mx-auto pb-16">
      <!-- Initial state -->
      <div v-if="!query" class="py-20 text-center">
        <span class="material-symbols-outlined text-6xl text-on-surface-variant opacity-30 block mb-4">travel_explore</span>
        <h2 class="font-serif text-2xl font-bold text-on-surface mb-2">Discover UtterLazy</h2>
        <p class="text-on-surface-variant">Search for your favorite restaurants, dishes, or cuisines</p>
      </div>

      <!-- Loading -->
      <div v-else-if="isLoading" class="py-20 text-center">
        <span class="material-symbols-outlined text-5xl animate-spin block mb-4 text-primary">progress_activity</span>
      </div>

      <!-- Results -->
      <div v-else-if="results" class="space-y-10">
        <!-- Restaurants -->
        <section v-if="results.restaurants.length">
          <h3 class="font-serif text-xl font-bold mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">restaurant</span> Restaurants
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <router-link v-for="r in results.restaurants" :key="r.id" :to="'/restaurant/' + r.id"
              class="flex gap-4 items-center bg-surface-container-lowest rounded-xl p-4 border border-outline-variant/20 hover:shadow-lg hover:border-primary/30 transition-all group">
              <div class="w-16 h-16 rounded-xl overflow-hidden shrink-0 bg-surface-container">
                <img :src="r.logo_url || 'https://images.unsplash.com/photo-1512152272829-e3139592d56f?auto=format&fit=crop&w=800&q=80'" :alt="r.name" class="w-full h-full object-cover" />
              </div>
              <div>
                <h4 class="font-bold text-on-surface group-hover:text-primary transition-colors">{{ r.name }}</h4>
                <p class="text-sm text-on-surface-variant line-clamp-1">{{ r.description }}</p>
              </div>
            </router-link>
          </div>
        </section>

        <!-- Dishes -->
        <section v-if="results.dishes.length">
          <h3 class="font-serif text-xl font-bold mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">restaurant_menu</span> Dishes
          </h3>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <router-link v-for="d in results.dishes" :key="d.id" :to="'/restaurant/' + d.restaurant_id"
              class="flex gap-4 items-center bg-surface-container-lowest rounded-xl p-4 border border-outline-variant/20 hover:shadow-lg hover:border-primary/30 transition-all group">
              <div class="w-16 h-16 rounded-xl overflow-hidden shrink-0 bg-surface-container">
                <img :src="d.image_url || 'https://images.unsplash.com/photo-1589302168068-964664d93dc0?auto=format&fit=crop&w=300&q=80'" :alt="d.name" class="w-full h-full object-cover" />
              </div>
              <div class="flex-1">
                <h4 class="font-bold text-on-surface group-hover:text-primary transition-colors">{{ d.name }}</h4>
                <p class="text-sm text-on-surface-variant line-clamp-1">{{ d.restaurant?.name || '' }}</p>
              </div>
              <span class="font-bold text-primary shrink-0">₹{{ d.price }}</span>
            </router-link>
          </div>
        </section>

        <!-- No results -->
        <div v-if="results.restaurants.length === 0 && results.dishes.length === 0" class="py-20 text-center">
          <span class="material-symbols-outlined text-5xl text-on-surface-variant opacity-30 block mb-4">search_off</span>
          <h2 class="font-serif text-2xl font-bold text-on-surface mb-2">No results found</h2>
          <p class="text-on-surface-variant">Try a different search term like "biryani" or "chicken"</p>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { searchCatalog, voiceSearch } from '../api'

const query = ref('')
const results = ref(null)
const isLoading = ref(false)
const searchInput = ref(null)
const isListening = ref(false)
let debounceTimer = null
let recognition = null

const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition

onMounted(() => {
  searchInput.value?.focus()
  if (SpeechRecognition) {
    recognition = new SpeechRecognition()
    recognition.continuous = false
    recognition.interimResults = false
    recognition.lang = 'hi-IN'
    recognition.onresult = (event) => {
      const text = event.results[0][0].transcript
      isListening.value = false
      query.value = text
      handleVoiceSearch(text)
    }
    recognition.onerror = () => { isListening.value = false }
    recognition.onend = () => { isListening.value = false }
  }
})

onUnmounted(() => recognition?.abort())

const toggleVoiceSearch = () => {
  if (!recognition) { alert('Voice search not supported in your browser. Try Chrome.'); return }
  if (isListening.value) { recognition.stop(); isListening.value = false; return }
  recognition.lang = 'hi-IN'
  recognition.start()
  isListening.value = true
}

const handleVoiceSearch = async (text) => {
  isLoading.value = true
  try {
    const data = await voiceSearch(text)
    results.value = {
      restaurants: data.restaurants || [],
      dishes: data.menu_items?.map(m => ({ ...m, restaurant: m.restaurant })) || [],
    }
  } catch (e) {
    console.error(e)
    handleSearch()
  } finally {
    isLoading.value = false
  }
}

const handleSearch = () => {
  clearTimeout(debounceTimer)
  if (query.value.length < 2) { results.value = null; return }
  isLoading.value = true
  debounceTimer = setTimeout(async () => {
    try {
      results.value = await searchCatalog(query.value)
    } catch (e) {
      console.error(e)
    } finally {
      isLoading.value = false
    }
  }, 300)
}
</script>
