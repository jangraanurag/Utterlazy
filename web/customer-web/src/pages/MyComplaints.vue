<template>
  <div class="bg-background text-on-surface min-h-screen">
    <nav class="fixed top-0 left-0 w-full flex justify-between items-center px-8 py-4 bg-[#fdf9f4]/60 backdrop-blur-xl z-50">
      <div class="flex items-center gap-6">
        <router-link to="/" class="font-serif text-3xl italic font-bold text-primary">UtterLazy</router-link>
        <router-link to="/settings" class="flex items-center gap-1 text-on-surface-variant hover:text-primary transition-colors font-medium">
          <span class="material-symbols-outlined text-[18px]">arrow_back</span> Settings
        </router-link>
      </div>
    </nav>

    <main class="pt-28 px-8 max-w-3xl mx-auto pb-16">
      <header class="mb-10">
        <h1 class="font-serif text-4xl font-bold text-on-surface mb-2">My Complaints</h1>
        <p class="text-on-surface-variant font-medium">Track and review your reported issues.</p>
      </header>

      <div v-if="loading && complaints.length === 0" class="py-20 text-center text-on-surface-variant">
        <span class="material-symbols-outlined text-5xl animate-spin block mb-4">progress_activity</span>
        <p class="text-lg">Loading complaints...</p>
      </div>

      <div v-else-if="complaints.length === 0" class="py-20 text-center">
        <div class="w-24 h-24 rounded-full bg-surface-container-high mx-auto flex items-center justify-center mb-6">
          <span class="material-symbols-outlined text-5xl text-on-surface-variant opacity-50">report_problem</span>
        </div>
        <h2 class="font-serif text-2xl font-bold text-on-surface mb-2">No Complaints</h2>
        <p class="text-on-surface-variant mb-8">You haven't filed any complaints yet.</p>
        <router-link to="/orders" class="primary-gradient text-on-primary font-bold py-3 px-8 rounded-xl shadow-lg shadow-primary/20 inline-flex items-center gap-2">
          <span class="material-symbols-outlined">receipt_long</span> View Orders
        </router-link>
      </div>

      <div v-else class="space-y-4">
        <div v-for="c in complaints" :key="c.id" class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 overflow-hidden hover:shadow-lg transition-all">
          <div class="p-6">
            <div class="flex justify-between items-start mb-3">
              <div>
                <p class="text-xs font-black text-primary uppercase tracking-widest mb-1">
                  Order #{{ String(c.order_id).slice(0, 8).toUpperCase() }}
                </p>
                <p class="font-bold text-on-surface">{{ c.restaurant?.name || 'Restaurant' }}</p>
              </div>
              <span :class="statusClass(c.status)" class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-widest shrink-0">
                {{ c.status }}
              </span>
            </div>

            <div class="flex items-center gap-2 mb-2">
              <span class="material-symbols-outlined text-[16px] text-on-surface-variant">category</span>
              <span class="text-sm font-bold text-on-surface-variant">{{ c.reason }}</span>
            </div>

            <p class="text-sm text-on-surface-variant leading-relaxed">{{ c.description }}</p>

            <div v-if="c.vendor_response" class="mt-4 p-4 bg-blue-50 rounded-xl border border-blue-100">
              <p class="text-xs font-bold text-blue-700 mb-1">Restaurant Response</p>
              <p class="text-sm text-blue-900">{{ c.vendor_response }}</p>
            </div>

            <div v-if="c.refund_amount > 0" class="mt-3 flex items-center gap-2 text-green-700">
              <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">account_balance_wallet</span>
              <span class="font-bold text-sm">Refund: ₹{{ parseFloat(c.refund_amount).toFixed(2) }}</span>
            </div>
          </div>
        </div>

        <div v-if="hasMore" class="text-center pt-4">
          <button @click="loadMore" :disabled="loadingMore" class="text-primary font-bold text-sm hover:underline">
            {{ loadingMore ? 'Loading...' : 'Load More' }}
          </button>
        </div>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getMyComplaints } from '../api'

const complaints = ref([])
const loading = ref(true)
const loadingMore = ref(false)
const hasMore = ref(true)
const page = ref(1)

const loadComplaints = async (reset = false) => {
  if (reset) {
    page.value = 1
    hasMore.value = true
  }
  try {
    const res = await getMyComplaints(page.value)
    const data = res.data || []
    if (reset || page.value === 1) {
      complaints.value = data
    } else {
      complaints.value = [...complaints.value, ...data]
    }
    hasMore.value = data.length >= 15
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

const loadMore = () => {
  if (!hasMore.value || loadingMore.value) return
  loadingMore.value = true
  page.value++
  loadComplaints()
}

const statusClass = (status) => {
  const map = {
    pending: 'bg-amber-100 text-amber-700',
    responded: 'bg-green-100 text-green-700',
    closed: 'bg-stone-100 text-stone-600',
  }
  return map[status] || 'bg-stone-100 text-stone-600'
}

onMounted(() => loadComplaints(true))
</script>
