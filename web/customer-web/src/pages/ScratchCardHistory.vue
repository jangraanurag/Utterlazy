<template>
  <div class="bg-background text-on-surface min-h-screen">
    <nav class="fixed top-0 left-0 w-full flex justify-between items-center px-8 py-4 bg-[#fdf9f4]/60 backdrop-blur-xl z-50">
      <div class="flex items-center gap-6">
        <router-link to="/" class="font-serif text-3xl italic font-bold text-primary">UtterLazy</router-link>
        <router-link to="/games" class="flex items-center gap-1 text-on-surface-variant hover:text-primary transition-colors font-medium">
          <span class="material-symbols-outlined text-[18px]">arrow_back</span> Arena
        </router-link>
      </div>
    </nav>

    <main class="pt-28 px-8 max-w-3xl mx-auto pb-16">
      <header class="mb-10">
        <h1 class="font-serif text-4xl font-bold text-on-surface mb-2">My Scratch Cards</h1>
        <p class="text-on-surface-variant font-medium">All your mystery rewards in one place.</p>
      </header>

      <div v-if="cards.length === 0" class="py-20 text-center">
        <div class="w-24 h-24 rounded-full bg-surface-container-high mx-auto flex items-center justify-center mb-6">
          <span class="material-symbols-outlined text-5xl text-on-surface-variant opacity-50">card_giftcard</span>
        </div>
        <h2 class="font-serif text-2xl font-bold text-on-surface mb-2">No scratch cards yet</h2>
        <p class="text-on-surface-variant mb-8">Complete an order to receive mystery scratch cards!</p>
        <router-link to="/" class="primary-gradient text-on-primary font-bold py-3 px-8 rounded-xl shadow-lg shadow-primary/20 inline-flex items-center gap-2">
          <span class="material-symbols-outlined">restaurant_menu</span> Explore Restaurants
        </router-link>
      </div>

      <div v-else class="space-y-4">
        <div v-for="(card, i) in cards" :key="i" class="bg-surface-container-lowest rounded-2xl border overflow-hidden hover:shadow-lg transition-all" :class="card.scratched ? 'border-green-200' : 'border-orange-200'">
          <div class="p-5 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0" :class="card.scratched ? 'bg-green-50' : 'bg-orange-50'">
              <span class="material-symbols-outlined" :class="card.scratched ? 'text-green-600' : 'text-orange-500'" style="font-variation-settings: 'FILL' 1;">{{ card.scratched ? 'check_circle' : 'card_giftcard' }}</span>
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1">
                <p class="font-bold text-on-surface">{{ card.brand || 'Mystery Card' }}</p>
                <span v-if="!card.scratched" class="text-[9px] font-black text-orange-600 bg-orange-100 px-2 py-0.5 rounded-full uppercase tracking-widest">Unscratched</span>
                <span v-else class="text-[9px] font-black text-green-600 bg-green-100 px-2 py-0.5 rounded-full uppercase tracking-widest">Scratched</span>
              </div>
              <p class="text-sm text-on-surface-variant truncate">{{ card.title || '' }}</p>
              <p v-if="card.saved_at" class="text-[10px] text-on-surface-variant mt-1">
                {{ card.scratched ? 'Scratched' : 'Received' }}: {{ new Date(card.saved_at).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' }) }}
              </p>
            </div>
            <div v-if="card.scratched && card.code" class="flex items-center gap-2">
              <span class="font-mono font-bold text-sm text-on-surface bg-surface-container px-3 py-2 rounded-lg border border-outline-variant/20 select-all">{{ card.code }}</span>
              <button @click="copyCode(card.code)" class="material-symbols-outlined text-primary hover:scale-125 active:scale-95 transition-all" title="Copy code">content_copy</button>
            </div>
          </div>
          <div v-if="card.description" class="px-5 pb-4">
            <p class="text-xs text-on-surface-variant">{{ card.description }}</p>
          </div>
        </div>
      </div>
    </main>

    <div v-if="toast" class="fixed bottom-12 left-1/2 -translate-x-1/2 bg-on-surface text-surface px-8 py-4 rounded-2xl z-[100] font-bold text-xs tracking-widest uppercase shadow-2xl">
      {{ toast }}
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'

const cards = ref([])
const toast = ref('')

const triggerToast = (msg) => {
  toast.value = msg
  setTimeout(() => toast.value = '', 2500)
}

const copyCode = (code) => {
  if (!code) return
  navigator.clipboard.writeText(code)
  triggerToast('Code copied!')
}

onMounted(() => {
  try {
    const raw = localStorage.getItem('scratch_cards') || '[]'
    cards.value = JSON.parse(raw)
  } catch {
    cards.value = []
  }
})
</script>
