<script setup>
import { ref, onMounted } from 'vue'
import { getAnalytics } from '../api'

const data = ref(null)
const loading = ref(true)

onMounted(async () => {
  try { data.value = await getAnalytics() }
  catch (e) { console.error(e) }
  loading.value = false
})

const maxRev = () => Math.max(...(data.value?.hourly_breakdown || []).map(h => h.revenue || 0), 1)
</script>

<template>
  <div>
    <h1 class="page-title mb-8">Analytics</h1>

    <div v-if="loading" class="text-center" style="padding: 60px;">Loading...</div>
    <div v-else-if="!data" class="card text-center" style="padding: 60px;">Failed to load</div>
    <div v-else>
      <div class="grid-kpi" style="margin-bottom: 32px;">
        <div class="card kpi-card">
          <span class="kpi-label" style="color: var(--success);">REVENUE</span>
          <div class="kpi-value">₹{{ data.today?.revenue || 0 }}</div>
        </div>
        <div class="card kpi-card">
          <span class="kpi-label" style="color: var(--sovereign-orange);">ORDERS</span>
          <div class="kpi-value">{{ data.today?.orders || 0 }}</div>
        </div>
        <div class="card kpi-card">
          <span class="kpi-label" style="color: var(--sovereign-teal);">AVG ORDER</span>
          <div class="kpi-value">₹{{ data.today?.avg_order_value || 0 }}</div>
        </div>
        <div class="card kpi-card">
          <span class="kpi-label" style="color: var(--sovereign-gold);">RATING</span>
          <div class="kpi-value">{{ data.today?.avg_rating || 0 }}⭐</div>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 32px; margin-bottom: 32px;">
        <div class="card">
          <h3 class="font-space mb-4" style="font-weight: 700;">Retention Rate</h3>
          <p class="kpi-value" style="color: var(--sovereign-teal);">{{ data.retention?.rate || 0 }}%</p>
          <p class="text-sm text-muted mt-1">{{ data.retention?.returning_customers || 0 }} returning customers</p>
        </div>
        <div class="card">
          <h3 class="font-space mb-4" style="font-weight: 700;">Cancellation Rate (30d)</h3>
          <p class="kpi-value" style="color: var(--danger);">{{ data.cancellations_30d?.rate || 0 }}%</p>
          <p class="text-sm text-muted mt-1">{{ data.cancellations_30d?.cancelled || 0 }} orders cancelled</p>
        </div>
      </div>

      <div class="card mb-8">
        <h3 class="font-space mb-4" style="font-weight: 700;">Peak Hours (Today)</h3>
        <div v-if="!(data.hourly_breakdown || []).length" class="text-muted text-center">No data</div>
        <div v-else class="flex items-end gap-3" style="height: 160px;">
          <div v-for="h in data.hourly_breakdown" :key="h.hour" class="flex-1 flex flex-col items-center">
            <div class="w-full rounded-t" :style="{ height: `${Math.max((h.revenue / maxRev()) * 100, 5)}%`, backgroundColor: h.revenue / maxRev() > 0.5 ? 'var(--sovereign-orange)' : 'rgba(255,107,0,0.4)' }" />
            <span class="text-xs mt-1 text-muted">{{ h.hour?.split(':')[0] }}</span>
          </div>
        </div>
      </div>

      <div class="card">
        <h3 class="font-space mb-4" style="font-weight: 700;">Top Items Today</h3>
        <div v-if="!(data.top_items || []).length" class="text-muted text-center">No items sold</div>
        <div v-else style="display: grid; gap: 12px;">
          <div v-for="(item, i) in data.top_items" :key="i" class="flex-between" style="padding: 8px 0; border-bottom: 1px solid var(--glass-edge);">
            <span style="font-weight: 600;">{{ item.name }}</span>
            <div class="flex gap-6">
              <span class="text-muted">{{ item.quantity }} sold</span>
              <span class="font-bold" style="color: var(--sovereign-orange);">₹{{ item.revenue }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
