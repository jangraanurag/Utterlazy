<script setup>
import { ref, onMounted } from 'vue'
import api from '../api'
import { useAuth } from '../auth'

const { restaurant } = useAuth()
const reviews = ref([])
const summary = ref(null)
const loading = ref(true)
const replyingTo = ref(null)
const replyText = ref('')

async function fetchReviews() {
  try {
    const res = await api.get('/vendor/me').then(r => r.data)
    const restaurantId = res.restaurant.id
    const data = await api.get(`/restaurants/${restaurantId}/reviews`).then(r => r.data)
    reviews.value = data.reviews?.data || data.reviews || []
    summary.value = data.summary
  } catch (e) { console.error(e) }
  loading.value = false
}

onMounted(fetchReviews)

async function submitReply(id) {
  try {
    await api.patch(`/vendor/reviews/${id}/reply`, { vendor_reply: replyText.value })
    replyingTo.value = null
    replyText.value = ''
    fetchReviews()
  } catch (e) { alert('Failed') }
}
</script>

<template>
  <div>
    <h1 class="page-title mb-8">Reviews</h1>

    <div v-if="loading" class="text-center" style="padding: 60px;">Loading...</div>
    <div v-else>
      <div v-if="summary" class="card mb-8 flex items-center gap-6">
        <div class="text-center">
          <p class="kpi-value" style="color: var(--sovereign-orange);">{{ summary.average_rating }}</p>
          <p class="text-sm text-muted mt-1">{{ summary.total_reviews }} reviews</p>
        </div>
        <div class="flex-1" style="border-left: 1px solid var(--glass-edge); padding-left: 24px;">
          <h3 class="font-bold mb-2">Rating Breakdown</h3>
          <div v-for="star in [5,4,3,2,1]" :key="star" class="flex items-center gap-3 text-sm">
            <span style="width: 48px;">{{ star }} Star</span>
            <div class="flex-1 h-2 bg-gray-100 rounded overflow-hidden">
              <div class="h-full" style="background: var(--sovereign-orange); width: `${summary.total_reviews > 0 ? (summary.breakdown?.[star] || 0) / summary.total_reviews * 100 : 0}%`" />
            </div>
            <span class="text-right text-muted" style="width: 32px;">{{ summary.breakdown?.[star] || 0 }}</span>
          </div>
        </div>
      </div>

      <div v-if="reviews.length === 0" class="text-center text-muted" style="padding: 40px;">No reviews yet.</div>
      <div v-else style="display: grid; gap: 16px;">
        <div v-for="review in reviews" :key="review.id" class="card">
          <div class="flex-between mb-4">
            <div>
              <p class="font-bold">{{ review.user?.name || 'Customer' }}</p>
              <p class="text-xs text-muted">{{ new Date(review.created_at).toLocaleDateString() }}</p>
            </div>
            <span class="badge badge-warning">{{ review.rating }} ⭐</span>
          </div>
          <p v-if="review.title" class="font-bold mb-1">{{ review.title }}</p>
          <p class="text-sm mb-4" style="color: var(--text-secondary);">{{ review.comment }}</p>

          <div v-if="review.vendor_reply" style="background: var(--sovereign-elevated); border-left: 4px solid var(--sovereign-orange); padding: 16px; border-radius: 0 8px 8px 0;">
            <p class="text-xs font-bold mb-1" style="color: var(--sovereign-orange);">Your Reply</p>
            <p class="text-sm">{{ review.vendor_reply }}</p>
          </div>
          <div v-else-if="replyingTo === review.id" class="mt-4 flex gap-2">
            <input type="text" class="form-input flex-1" placeholder="Write a reply..." v-model="replyText" />
            <button class="btn btn-sm btn-primary" @click="submitReply(review.id)">Send</button>
            <button class="btn btn-sm btn-secondary" @click="replyingTo = null; replyText = ''">Cancel</button>
          </div>
          <button v-else class="text-sm font-bold" style="color: var(--sovereign-orange);" @click="replyingTo = review.id">
            Reply to Customer
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
