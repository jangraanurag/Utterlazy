<template>
  <div class="min-h-screen bg-background p-8">
    <div class="max-w-4xl mx-auto">
      <h1 class="font-serif text-3xl font-bold mb-8">Refer & Earn</h1>

      <!-- Hero Card -->
      <div class="rounded-2xl sovereign-gradient p-8 text-white text-center mb-8">
        <span class="material-symbols-outlined text-6xl mb-4 block">card_giftcard</span>
        <h2 class="text-2xl font-bold mb-2">Invite Friends, Earn Rewards!</h2>
        <p class="text-white/70">You get ₹100, They get ₹50</p>
      </div>

      <!-- Referral Code -->
      <div class="bg-surface rounded-2xl border border-outline-variant/30 p-8 text-center mb-8">
        <p class="text-on-surface-variant text-sm mb-4">Your Referral Code</p>
        <div class="inline-flex items-center gap-4 px-8 py-4 rounded-xl bg-primary/5 border border-primary/20">
          <span class="text-3xl font-bold tracking-widest text-primary">UTTERLAZY123</span>
          <button class="p-2 rounded-lg hover:bg-primary/10" @click="copyCode">
            <span class="material-symbols-outlined text-primary">content_copy</span>
          </button>
        </div>
        <div class="mt-6 flex gap-4 justify-center">
          <button class="px-8 py-3 rounded-xl sovereign-gradient text-white font-bold flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">share</span> Share Code
          </button>
        </div>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-2 gap-6 mb-8">
        <div class="bg-surface rounded-2xl border border-outline-variant/30 p-6 text-center">
          <span class="material-symbols-outlined text-primary text-3xl mb-2 block">people</span>
          <p class="text-2xl font-bold">0</p>
          <p class="text-sm text-on-surface-variant">Total Referrals</p>
        </div>
        <div class="bg-surface rounded-2xl border border-outline-variant/30 p-6 text-center">
          <span class="material-symbols-outlined text-primary text-3xl mb-2 block">monetization_on</span>
          <p class="text-2xl font-bold">₹0</p>
          <p class="text-sm text-on-surface-variant">Total Earned</p>
        </div>
      </div>

      <!-- How it works -->
      <h2 class="text-xl font-bold mb-4">How it works</h2>
      <div class="space-y-4">
        <div v-for="(step, i) in steps" :key="i" class="flex items-center gap-4 bg-surface rounded-xl border border-outline-variant/30 p-4">
          <div class="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-bold text-sm">{{ i+1 }}</div>
          <p class="text-sm">{{ step }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const steps = ['Share your referral code with friends', 'They sign up using your code', 'You both earn rewards!']
const copyCode = () => navigator.clipboard.writeText('UTTERLAZY123')
</script>
