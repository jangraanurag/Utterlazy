<template>
  <div class="min-h-screen bg-background p-8">
    <div class="max-w-2xl mx-auto">
      <h1 class="font-serif text-3xl font-bold mb-8">Write a Review</h1>

      <div class="bg-surface rounded-2xl border border-outline-variant/30 p-8">
        <div class="text-center mb-8">
          <h2 class="text-xl font-bold mb-2">How was your order from {{ restaurant }}?</h2>
          <p class="text-on-surface-variant">Your feedback helps others discover great food</p>
        </div>

        <!-- Rating -->
        <div class="text-center mb-8">
          <div class="flex justify-center gap-2 mb-4">
            <button v-for="s in 5" :key="s" @click="rating = s" class="p-1">
              <span class="material-symbols-outlined text-5xl" :class="s <= rating ? 'text-yellow-500' : 'text-gray-300'">star</span>
            </button>
          </div>
          <p class="text-on-surface-variant text-sm">{{ ratingLabels[rating - 1] || 'Tap to rate' }}</p>
        </div>

        <!-- Sub-ratings -->
        <div class="grid grid-cols-3 gap-4 mb-8">
          <div class="text-center">
            <p class="text-sm font-bold mb-2">Food</p>
            <div class="flex justify-center gap-1">
              <button v-for="s in 5" :key="s" @click="foodRating = s" class="p-0.5">
                <span class="material-symbols-outlined text-xl" :class="s <= foodRating ? 'text-yellow-500' : 'text-gray-300'">star</span>
              </button>
            </div>
          </div>
          <div class="text-center">
            <p class="text-sm font-bold mb-2">Delivery</p>
            <div class="flex justify-center gap-1">
              <button v-for="s in 5" :key="s" @click="deliveryRating = s" class="p-0.5">
                <span class="material-symbols-outlined text-xl" :class="s <= deliveryRating ? 'text-yellow-500' : 'text-gray-300'">star</span>
              </button>
            </div>
          </div>
          <div class="text-center">
            <p class="text-sm font-bold mb-2">Packaging</p>
            <div class="flex justify-center gap-1">
              <button v-for="s in 5" :key="s" @click="packagingRating = s" class="p-0.5">
                <span class="material-symbols-outlined text-xl" :class="s <= packagingRating ? 'text-yellow-500' : 'text-gray-300'">star</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Title & Comment -->
        <input v-model="title" placeholder="Review title (optional)" class="w-full px-5 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30 mb-4" />
        <textarea v-model="comment" placeholder="Tell us about your experience..." rows="4" class="w-full px-5 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30 mb-4"></textarea>

        <!-- Photos -->
        <div class="mb-8">
          <p class="text-sm font-bold mb-2">Add Photos (optional)</p>
          <div class="flex gap-2">
            <div v-for="(photo, i) in photos" :key="i" class="w-20 h-20 rounded-xl bg-gray-100 flex items-center justify-center">
              <span class="material-symbols-outlined text-gray-400">image</span>
            </div>
            <button v-if="photos.length < 5" class="w-20 h-20 rounded-xl border-2 border-dashed border-gray-300 flex items-center justify-center">
              <span class="material-symbols-outlined text-gray-400">add_photo_alternate</span>
            </button>
          </div>
        </div>

        <button class="w-full py-4 rounded-xl sovereign-gradient text-white font-bold" :disabled="!rating || !comment">Submit Review</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
const restaurant = 'The Saffron Palace'
const rating = ref(0)
const foodRating = ref(0)
const deliveryRating = ref(0)
const packagingRating = ref(0)
const title = ref('')
const comment = ref('')
const photos = ref([])
const ratingLabels = ['Terrible', 'Bad', 'Okay', 'Good', 'Excellent']
</script>
