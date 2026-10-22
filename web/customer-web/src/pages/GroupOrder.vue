<template>
  <div class="min-h-screen bg-background p-8">
    <div class="max-w-2xl mx-auto">
      <h1 class="font-serif text-3xl font-bold mb-8">Group Order</h1>

      <div class="bg-surface rounded-2xl border border-outline-variant/30 p-8 text-center mb-8">
        <span class="material-symbols-outlined text-6xl text-primary mb-4 block">group_add</span>
        <h2 class="text-xl font-bold mb-2">Start a Group Order</h2>
        <p class="text-on-surface-variant mb-6">Order together with friends and family</p>
        <button class="px-8 py-3 rounded-xl sovereign-gradient text-white font-bold">Create Group Order</button>
      </div>

      <div class="bg-surface rounded-2xl border border-outline-variant/30 p-8 mb-8">
        <h2 class="text-xl font-bold mb-4">Join a Group Order</h2>
        <div class="flex gap-4">
          <input type="text" placeholder="Enter invite code" class="flex-1 px-5 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30" />
          <button class="px-6 py-3 rounded-xl border border-primary text-primary font-bold">Join</button>
        </div>
      </div>

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
const steps = ['Host creates a group order and shares the invite code', 'Friends join and add their items', 'Host finalizes — each person pays for their own items']
</script>
