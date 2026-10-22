<template>
  <div>
    <div class="flex justify-between items-center mb-8">
      <h1 class="font-display text-2xl font-bold">Push Notification Campaigns</h1>
      <button @click="showCreate = true" class="px-4 py-2 rounded-xl sovereign-gradient text-white text-sm font-bold">+ New Campaign</button>
    </div>

    <div class="space-y-4">
      <div v-for="campaign in campaigns" :key="campaign.id" class="bg-surface rounded-2xl border border-outline-variant/30 p-6">
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <div class="flex items-center gap-3 mb-2">
              <h3 class="font-bold">{{ campaign.title }}</h3>
              <span :class="statusBadge(campaign.status)" class="px-3 py-1 rounded-full text-xs font-bold uppercase">{{ campaign.status }}</span>
            </div>
            <p class="text-sm text-on-surface-variant mb-2">{{ campaign.body }}</p>
            <div class="flex gap-4 text-xs text-on-surface-variant">
              <span>Audience: {{ campaign.target_audience }}</span>
              <span>Sent: {{ campaign.sent_count }} users</span>
              <span>Opened: {{ campaign.open_count }}</span>
              <span>{{ campaign.time }}</span>
            </div>
          </div>
          <div class="flex gap-2">
            <button v-if="campaign.status === 'draft'" class="px-4 py-2 rounded-lg bg-primary text-white text-xs font-bold">Send Now</button>
            <button class="p-2 rounded-lg hover:bg-white/5"><span class="material-symbols-outlined text-[18px]">delete</span></button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="showCreate" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showCreate = false">
      <div class="bg-surface rounded-2xl p-8 w-full max-w-lg">
        <h3 class="text-xl font-bold mb-6">Create Campaign</h3>
        <div class="space-y-4">
          <input v-model="form.title" placeholder="Notification Title" class="w-full px-5 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30" />
          <textarea v-model="form.body" placeholder="Notification Body" rows="3" class="w-full px-5 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30"></textarea>
          <select v-model="form.target_audience" class="w-full px-5 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30">
            <option value="all">All Users</option>
            <option value="new_users">New Users</option>
            <option value="returning_users">Returning Users</option>
            <option value="inactive_users">Inactive Users</option>
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
  { id: 1, title: 'Weekend Special: 50% Off!', body: 'Enjoy 50% off on all orders this weekend. Use code WEEKEND50.', target_audience: 'all', status: 'sent', sent_count: 12500, open_count: 4200, time: '2 days ago' },
  { id: 2, title: 'Welcome to UtterLazy!', body: 'Get ₹100 off on your first order. Use code WELCOME100.', target_audience: 'new_users', status: 'sent', sent_count: 3200, open_count: 1800, time: '1 week ago' },
  { id: 3, title: 'We Miss You!', body: 'Come back and enjoy 30% off on your next order.', target_audience: 'inactive_users', status: 'draft', sent_count: 0, open_count: 0, time: 'Today' },
])
const form = ref({ title: '', body: '', target_audience: 'all' })
const statusBadge = (s) => ({ sent:'bg-green-100 text-green-700', scheduled:'bg-blue-100 text-blue-700', draft:'bg-stone-100 text-stone-600', failed:'bg-red-100 text-red-700' }[s])
</script>