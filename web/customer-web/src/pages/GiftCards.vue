<template>
  <div class="min-h-screen bg-background p-8">
    <div class="max-w-4xl mx-auto">
      <h1 class="font-serif text-3xl font-bold mb-8">Gift Cards</h1>

      <!-- Balance -->
      <div class="bg-surface rounded-2xl border border-outline-variant/30 p-8 flex justify-between items-center mb-8">
        <div>
          <p class="text-on-surface-variant text-sm">Gift Card Balance</p>
          <p class="text-3xl font-bold text-primary">₹0</p>
        </div>
        <button class="px-6 py-3 rounded-xl bg-primary text-white font-bold" @click="showPurchase = true">Buy Gift Card</button>
      </div>

      <!-- Quick amounts -->
      <h2 class="text-xl font-bold mb-4">Choose Amount</h2>
      <div class="grid grid-cols-5 gap-4 mb-8">
        <button v-for="amount in [200,500,1000,2000,5000]" :key="amount"
          class="py-4 rounded-xl border border-primary/30 text-primary font-bold hover:bg-primary/5 transition-colors">
          ₹{{ amount }}
        </button>
      </div>

      <!-- Redeem -->
      <h2 class="text-xl font-bold mb-4">Have a gift card?</h2>
      <div class="flex gap-4">
        <input type="text" placeholder="Enter gift card code" class="flex-1 px-5 py-4 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-on-surface" />
        <button class="px-6 py-4 rounded-xl bg-primary text-white font-bold">Redeem</button>
      </div>

      <!-- Purchase Modal -->
      <div v-if="showPurchase" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showPurchase = false">
        <div class="bg-surface rounded-2xl p-8 w-full max-w-md">
          <h3 class="text-xl font-bold mb-6">Buy Gift Card</h3>
          <div class="space-y-4">
            <input type="number" placeholder="Amount (₹500)" class="w-full px-5 py-4 rounded-xl bg-surface-container-lowest border border-outline-variant/30" />
            <input type="email" placeholder="Recipient email" class="w-full px-5 py-4 rounded-xl bg-surface-container-lowest border border-outline-variant/30" />
            <input type="text" placeholder="Message (optional)" class="w-full px-5 py-4 rounded-xl bg-surface-container-lowest border border-outline-variant/30" />
            <button class="w-full py-4 rounded-xl sovereign-gradient text-white font-bold" @click="showPurchase = false">Purchase</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
const showPurchase = ref(false)
</script>
