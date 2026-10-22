<template>
  <div class="p-8">
    <div class="mb-10">
      <h1 class="font-display text-5xl font-bold tracking-tighter text-white mb-2">My <span class="text-primary">Submissions</span></h1>
      <p class="font-display text-xs font-bold uppercase tracking-[0.3em] text-on-surface-variant opacity-60">Status of everything you've created</p>
    </div>

    <div class="flex gap-4 mb-8">
      <button @click="tab = 'all'" :class="tab === 'all' ? 'text-primary border-primary' : 'text-white/50 border-transparent'" class="pb-2 border-b-2 font-bold text-sm transition-colors">All ({{ submissions.length }})</button>
      <button @click="tab = 'pending'" :class="tab === 'pending' ? 'text-primary border-primary' : 'text-white/50 border-transparent'" class="pb-2 border-b-2 font-bold text-sm transition-colors">Pending ({{ pendingCount }})</button>
      <button @click="tab = 'history'" :class="tab === 'history' ? 'text-primary border-primary' : 'text-white/50 border-transparent'" class="pb-2 border-b-2 font-bold text-sm transition-colors">History</button>
    </div>

    <div v-if="loading" class="text-center py-20 text-white/50">
      <div class="w-12 h-12 border-2 border-primary/20 border-t-primary rounded-full animate-spin mx-auto mb-6"></div>
      <p class="font-display font-bold uppercase tracking-widest text-[10px]">Loading...</p>
    </div>

    <div v-else-if="filtered.length === 0" class="text-center py-20 text-white/50">
      <p class="text-xl">No submissions found</p>
    </div>

    <div v-else class="space-y-4">
      <div v-for="item in filtered" :key="item.id" class="bg-surface rounded-2xl border border-white/5 p-6">
        <div class="flex items-start justify-between mb-3">
          <div>
            <div class="flex items-center gap-3">
              <span :class="item.type === 'ad' ? 'bg-blue-500/20 text-blue-400' : 'bg-purple-500/20 text-purple-400'" class="px-3 py-1 rounded-full text-xs font-bold">{{ item.type === 'ad' ? 'Ad' : 'Offer' }}</span>
              <h3 class="text-lg font-bold text-white">{{ item.title }}</h3>
            </div>
          </div>
          <span v-if="item.approval_status === 'pending'" class="px-3 py-1 rounded-full text-xs font-bold bg-orange-500/20 text-orange-400 whitespace-nowrap">Pending</span>
          <span v-else-if="item.approval_status === 'approved'" class="px-3 py-1 rounded-full text-xs font-bold bg-green-500/20 text-green-400 whitespace-nowrap">Approved</span>
          <span v-else-if="item.approval_status === 'rejected'" class="px-3 py-1 rounded-full text-xs font-bold bg-red-500/20 text-red-400 whitespace-nowrap">Rejected</span>
          <span v-else-if="item.approval_status === 'expired'" class="px-3 py-1 rounded-full text-xs font-bold bg-white/10 text-white/50 whitespace-nowrap">Expired</span>
          <span v-else class="px-3 py-1 rounded-full text-xs font-bold bg-white/10 text-white/50 whitespace-nowrap">{{ item.approval_status }}</span>
        </div>
        <div class="text-[10px] text-white/30 uppercase tracking-widest mb-3">Submitted {{ new Date(item.submitted_at).toLocaleDateString() }}</div>
        <div v-if="item.rejection_reason" class="p-3 rounded-xl bg-red-500/5 border border-red-500/10 text-red-400/70 text-sm">{{ item.rejection_reason }}</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { amGetMySubmissions } from '../api'

const tab = ref('all')
const loading = ref(true)
const submissions = ref([])

const filtered = computed(() => {
  if (tab.value === 'all') return submissions.value
  if (tab.value === 'pending') return submissions.value.filter(s => s.approval_status === 'pending')
  return submissions.value.filter(s => s.approval_status !== 'pending')
})

const pendingCount = computed(() => submissions.value.filter(s => s.approval_status === 'pending').length)

const fetchData = async () => {
  loading.value = true
  try {
    submissions.value = await amGetMySubmissions()
  } catch (e) { console.error(e) }
  finally { loading.value = false }
}

onMounted(fetchData)
</script>
