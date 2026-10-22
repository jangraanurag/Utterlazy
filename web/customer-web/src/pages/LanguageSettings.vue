<template>
  <div class="bg-background text-on-surface min-h-screen">
    <nav class="fixed top-0 left-0 w-full flex justify-between items-center px-8 py-4 bg-[#fdf9f4]/60 dark:bg-stone-950/60 backdrop-blur-xl z-50">
      <div class="flex items-center gap-6">
        <router-link to="/" class="font-serif text-3xl italic font-bold text-[#c04828]">UtterLazy</router-link>
        <router-link to="/settings" class="flex items-center gap-1 text-on-surface-variant hover:text-primary transition-colors font-medium">
          <span class="material-symbols-outlined text-[18px]">arrow_back</span> Back to Settings
        </router-link>
      </div>
    </nav>

    <main class="pt-28 px-8 max-w-2xl mx-auto pb-16">
      <header class="mb-10">
        <h1 class="font-serif text-4xl font-bold text-on-surface mb-2">Language</h1>
        <p class="text-on-surface-variant font-medium">Choose your preferred language.</p>
      </header>

      <div class="space-y-3">
        <button v-for="lang in languages" :key="lang.code"
          @click="selectLanguage(lang.code)"
          class="w-full flex items-center justify-between p-5 rounded-2xl border transition-all"
          :class="selected === lang.code ? 'border-primary bg-primary/5 shadow-md' : 'border-outline-variant/30 bg-surface hover:border-outline-variant'">
          <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold"
              :class="selected === lang.code ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface-variant'">
              {{ lang.code.toUpperCase() }}
            </div>
            <div class="text-left">
              <p class="font-bold">{{ lang.name }}</p>
              <p class="text-sm text-on-surface-variant">{{ lang.native }}</p>
            </div>
          </div>
          <span v-if="selected === lang.code" class="material-symbols-outlined text-primary" style="font-variation-settings: 'FILL' 1;">check_circle</span>
        </button>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const selected = ref(localStorage.getItem('utterlazy_language') || 'en')

const languages = [
  { code: 'en', name: 'English', native: 'English' },
  { code: 'hi', name: 'Hindi', native: 'हिन्दी' },
  { code: 'bn', name: 'Bengali', native: 'বাংলা' },
  { code: 'ta', name: 'Tamil', native: 'தமிழ்' },
  { code: 'te', name: 'Telugu', native: 'తెలుగు' },
  { code: 'mr', name: 'Marathi', native: 'मराठी' },
  { code: 'gu', name: 'Gujarati', native: 'ગુજરાતી' },
  { code: 'kn', name: 'Kannada', native: 'ಕನ್ನಡ' },
  { code: 'ml', name: 'Malayalam', native: 'മലയാളം' },
  { code: 'pa', name: 'Punjabi', native: 'ਪੰਜਾਬੀ' },
]

const selectLanguage = (code) => {
  selected.value = code
  localStorage.setItem('utterlazy_language', code)
}
</script>
