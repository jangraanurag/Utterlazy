<template>
  <div class="min-h-screen bg-background p-8">
    <div class="max-w-3xl mx-auto">
      <h1 class="font-serif text-3xl font-bold mb-8">My Reviews</h1>
      <div v-if="reviews.length === 0" class="bg-surface rounded-2xl border border-outline-variant/30 p-12 text-center">
        <span class="material-symbols-outlined text-6xl text-on-surface-variant opacity-30 block mb-4">rate_review</span>
        <h2 class="text-xl font-bold mb-2">No Reviews Yet</h2>
        <p class="text-on-surface-variant">Rate your orders to help others discover great food!</p>
      </div>
      <div v-else class="space-y-4">
        <div v-for="review in reviews" :key="review.id" class="bg-surface rounded-2xl border border-outline-variant/30 p-6">
          <div class="flex justify-between items-start mb-3">
            <div>
              <h3 class="font-bold">{{ review.restaurant }}</h3>
              <p class="text-sm text-on-surface-variant">{{ review.date }}</p>
            </div>
            <div class="flex items-center gap-1">
              <span v-for="s in 5" :key="s" class="material-symbols-outlined text-[18px]" :class="s <= review.rating ? 'text-yellow-500' : 'text-gray-300'">star</span>
            </div>
          </div>
          <p v-if="review.title" class="font-bold mb-1">{{ review.title }}</p>
          <p class="text-sm text-on-surface-variant">{{ review.comment }}</p>
          <div v-if="review.photos && review.photos.length" class="flex gap-2 mt-3">
            <img v-for="photo in review.photos" :key="photo" :src="photo" class="w-16 h-16 rounded-lg object-cover" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
const reviews = ref([
  { id: 1, restaurant: 'The Saffron Palace', rating: 5, title: 'Amazing Butter Chicken!', comment: 'Best butter chicken I have ever had. Rich creamy texture with perfect spice level.', date: '2 days ago', photos: [] },
  { id: 2, restaurant: 'Dragon Wok', rating: 4, title: 'Good Chinese food', comment: 'Decent portions, could be more flavorful.', date: '1 week ago', photos: [] },
])
</script>
