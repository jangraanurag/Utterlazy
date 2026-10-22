<template>
  <div class="bg-background text-on-surface min-h-screen">
    <nav class="fixed top-0 left-0 w-full flex justify-between items-center px-8 py-4 bg-[#fdf9f4]/60 dark:bg-stone-950/60 backdrop-blur-xl z-50">
      <div class="flex items-center gap-6">
        <router-link to="/" class="font-serif text-3xl italic font-bold text-[#c04828]">UtterLazy</router-link>
        <router-link to="/" class="flex items-center gap-1 text-on-surface-variant hover:text-primary transition-colors font-medium">
          <span class="material-symbols-outlined text-[18px]">arrow_back</span> Continue Shopping
        </router-link>
      </div>
    </nav>

    <main class="pt-28 px-8 max-w-3xl mx-auto pb-16">
      <header class="mb-10">
        <h1 class="font-serif text-4xl font-bold text-on-surface mb-2">Your Cart</h1>
        <p v-if="cart.items.length > 0" class="text-on-surface-variant font-medium">
          {{ cart.items.length }} item{{ cart.items.length > 1 ? 's' : '' }} from {{ cart.restaurantName || 'restaurant' }}
        </p>
      </header>

      <!-- Empty Cart -->
      <div v-if="cart.items.length === 0" class="py-20 text-center">
        <div class="w-24 h-24 rounded-full bg-surface-container-high mx-auto flex items-center justify-center mb-6">
          <span class="material-symbols-outlined text-5xl text-on-surface-variant opacity-50">shopping_basket</span>
        </div>
        <h2 class="font-serif text-2xl font-bold text-on-surface mb-2">Your cart is empty</h2>
        <p class="text-on-surface-variant mb-8">Add items from a restaurant to get started.</p>
        <router-link to="/" class="primary-gradient text-on-primary font-bold py-3 px-8 rounded-xl shadow-lg shadow-primary/20 inline-flex items-center gap-2">
          <span class="material-symbols-outlined">explore</span> Discover Restaurants
        </router-link>
      </div>

      <!-- Cart Items -->
      <div v-else class="space-y-4">
        <div v-for="item in cart.items" :key="item.id"
          class="bg-surface rounded-2xl p-6 border border-outline-variant/30 flex items-center gap-4">
          <img :src="item.image_url || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=100&q=80'"
            class="w-16 h-16 rounded-xl object-cover" />
          <div class="flex-1">
            <h3 class="font-bold">{{ item.name }}</h3>
            <p class="text-sm text-on-surface-variant">₹{{ item.price }}</p>
          </div>
          <div class="flex items-center gap-3 bg-surface-container-high rounded-xl px-2 py-1">
            <button @click="decrementItem(item)" class="w-8 h-8 rounded-lg flex items-center justify-center hover:bg-white transition-colors">
              <span class="material-symbols-outlined text-lg">remove</span>
            </button>
            <span class="font-bold text-sm w-6 text-center">{{ item.quantity }}</span>
            <button @click="incrementItem(item)" class="w-8 h-8 rounded-lg flex items-center justify-center hover:bg-white transition-colors">
              <span class="material-symbols-outlined text-lg">add</span>
            </button>
          </div>
          <p class="font-bold text-primary w-20 text-right">₹{{ (item.price * item.quantity).toFixed(0) }}</p>
          <button @click="removeItem(item.id)" class="text-on-surface-variant hover:text-red-500 transition-colors">
            <span class="material-symbols-outlined text-lg">delete_outline</span>
          </button>
        </div>

        <!-- Total & Checkout -->
        <div class="bg-surface rounded-2xl p-8 border border-outline-variant/30 mt-8">
          <div class="flex justify-between mb-4">
            <span class="text-on-surface-variant">Subtotal</span>
            <span class="font-bold">₹{{ total.toFixed(0) }}</span>
          </div>
          <div class="flex justify-between mb-4">
            <span class="text-on-surface-variant">Delivery Fee</span>
            <span class="font-bold">₹30</span>
          </div>
          <div class="border-t border-outline-variant/20 pt-4 flex justify-between">
            <span class="text-lg font-black">Grand Total</span>
            <span class="text-lg font-black text-primary">₹{{ (total + 30).toFixed(0) }}</span>
          </div>
          <router-link to="/checkout"
            class="mt-6 w-full py-4 rounded-xl sovereign-gradient text-on-primary font-serif font-bold uppercase tracking-widest text-sm flex items-center justify-center gap-2 shadow-lg shadow-primary/20">
            <span class="material-symbols-outlined text-[18px]">lock</span> Proceed to Checkout
          </router-link>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { state as cart, removeFromCart as removeItem } from '../store/cart'

const total = computed(() => {
  return cart.items.reduce((sum, item) => sum + (item.price * item.quantity), 0)
})

const incrementItem = (item) => {
  item.quantity += 1
}

const decrementItem = (item) => {
  if (item.quantity > 1) {
    item.quantity -= 1
  } else {
    removeItem(item.id)
  }
}
</script>
