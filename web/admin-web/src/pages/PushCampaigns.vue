<template>
  <div>
    <div class="flex justify-between items-center mb-8">
      <h1 class="font-display text-2xl font-bold">Push Campaigns</h1>
      <button @click="showCreate = true" class="px-4 py-2 rounded-xl sovereign-gradient text-white text-sm font-bold">+ New Campaign</button>
    </div>

    <div class="space-y-4">
      <div v-for="c in campaigns" :key="c.id" class="bg-surface rounded-2xl border border-outline-variant/30 p-6">
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <div class="flex items-center gap-3 mb-2">
              <h3 class="font-bold">{{ c.title }}</h3>
              <span :class="badge(c.status)" class="px-3 py-1 rounded-full text-xs font-bold uppercase">{{ c.status }}</span>
            </div>
            <p class="text-sm text-on-surface-variant mb-2">{{ c.body }}</p>
            <div class="flex gap-4 text-xs text-on-surface-variant">
              <span>{{ c.audience }}</span>
              <span>{{ c.sent }} sent</span>
              <span>{{ c.opened }} opened</span>
            </div>
          </div>
          <button v-if="c.status === 'draft'" class="px-4 py-2 rounded-lg bg-primary text-white text-xs font-bold">Send</button>
        </div>
      </div>
    </div>

    <div v-if="showCreate" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showCreate = false">
      <div class="bg-surface rounded-2xl p-8 w-full max-w-lg">
        <h3 class="text-xl font-bold mb-6">New Campaign</h3>
        <div class="space-y-4">
          <input placeholder="Title" class="w-full px-5 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30" />
          <textarea placeholder="Body" rows="3" class="w-full px-5 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30"></textarea>
          <select class="w-full px-5 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30">
            <option>All Users</option>
            <option>New Users</option>
            <option>Inactive Users</option>
          </select>
          <div class="flex gap-4">
            <button class="flex-1 py-3 rounded-xl border border-primary text-primary font-bold" @click="showCreate = false">Save Draft</button>
            <button class="flex-1 py-3 rounded-xl sovereign-gradient text-white font-bold" @click="showCreate = false">Send Now</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
const showCreate = ref(false)
const campaigns = ref([
  { id: 1, title: 'Weekend Special: 50% Off!', body: 'Enjoy 50% off on all orders this weekend. Use code WEEKEND50.', audience: 'All Users', status: 'sent', sent: 12500, opened: 4200 },
  { id: 2, title: 'Welcome to UtterLazy!', body: 'Get ₹100 off on your first order. Use code WELCOME100.', audience: 'New Users', status: 'sent', sent: 3200, opened: 1800 },
  { id: 3, title: 'We Miss You!', body: 'Come back and enjoy 30% off on your next order.', audience: 'Inactive Users', status: 'draft', sent: 0, opened: 0 },
])
const badge = (s) => ({ sent:'bg-green-100 text-green-700', draft:'bg-stone-100 text-stone-600', failed:'bg-red-100 text-red-700' }[s])
</script>
