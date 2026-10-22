<template>
  <div class="min-h-screen flex flex-col items-center justify-center bg-background text-on-surface p-8 text-center">
    <span class="material-symbols-outlined text-7xl text-primary/30 block mb-6">wifi_off</span>
    <h1 class="font-serif text-3xl font-bold mb-2">You're Offline</h1>
    <p class="text-on-surface-variant mb-8 max-w-md">Don't worry! Your previous searches and restaurant data are still available. Connect to the internet to place new orders.</p>
    <div class="grid grid-cols-2 gap-4 w-full max-w-sm">
      <router-link to="/" class="bg-surface border border-outline-variant/30 rounded-2xl p-4 hover:border-primary/30 transition-all">
        <span class="material-symbols-outlined text-primary block mb-2">home</span>
        <span class="text-sm font-bold">Home</span>
      </router-link>
      <router-link to="/search" class="bg-surface border border-outline-variant/30 rounded-2xl p-4 hover:border-primary/30 transition-all">
        <span class="material-symbols-outlined text-primary block mb-2">search</span>
        <span class="text-sm font-bold">Search</span>
      </router-link>
    </div>
    <button @click="window.location.reload()" class="mt-8 px-6 py-3 bg-primary text-white rounded-2xl font-bold text-sm hover:opacity-90 transition-all">
      Try Again
    </button>
  </div>
</template>

<script setup>
</script>
