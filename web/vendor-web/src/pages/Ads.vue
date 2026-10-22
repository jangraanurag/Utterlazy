<script setup>
import { ref, onMounted } from 'vue'
import { getAds, createAd, updateAd, deleteAd, toggleAd, getAdPerformance, getAdRecommendations } from '../api'

const ads = ref([])
const loading = ref(true)
const showForm = ref(false)
const editItem = ref(null)
const form = ref({ ad_type: 'ppc', placement: 'listings', daily_budget: '', start_date: '', duration_weeks: 1, weekly_budget: '', status: 'active' })
const saving = ref(false)
const perf = ref(null)
const showPerf = ref(false)

async function fetchAds() {
  loading.value = true
  try { const d = await getAds(); ads.value = d.ads || d.data || d }
  catch (e) { ads.value = [] }
  loading.value = false
}

function openCreate() {
  editItem.value = null
  form.value = { ad_type: 'ppc', placement: 'listings', daily_budget: '', start_date: new Date().toISOString().split('T')[0], duration_weeks: 1, weekly_budget: '', status: 'active' }
  showForm.value = true
}

function openEdit(ad) {
  editItem.value = ad
  form.value = { ad_type: ad.ad_type, placement: ad.placement || 'listings', daily_budget: ad.daily_budget, start_date: ad.start_date, duration_weeks: ad.duration_weeks || 1, weekly_budget: ad.weekly_budget || '', status: ad.status }
  showForm.value = true
}

async function handleSave() {
  saving.value = true
  try {
    if (editItem.value) await updateAd(editItem.value.id, form.value)
    else await createAd(form.value)
    showForm.value = false
    await fetchAds()
  } catch (e) { alert('Failed to save ad') }
  saving.value = false
}

async function handleToggle(ad) {
  try { await toggleAd(ad.id); await fetchAds() }
  catch (e) { alert('Failed to toggle') }
}

async function handleDelete(ad) {
  if (!confirm('Delete this ad?')) return
  try { await deleteAd(ad.id); await fetchAds() }
  catch (e) { alert('Failed to delete') }
}

async function handlePerformance(ad) {
  try { const d = await getAdPerformance(ad.id); perf.value = d; showPerf.value = true }
  catch (e) { alert('No performance data yet') }
}

function statusBadge(s) {
  const m = { active: 'badge-success', paused: 'badge-warning', ended: 'badge-info' }
  return m[s] || 'badge-info'
}

onMounted(fetchAds)
</script>

<template>
  <div>
    <div class="page-header">
      <div>
        <h1 class="page-title">Sponsored Ads</h1>
        <p class="font-space" style="color:var(--text-secondary);font-size:0.85rem;">Promote your restaurant with sponsored placements</p>
      </div>
      <button class="btn btn-primary" @click="openCreate">CREATE AD</button>
    </div>

    <div v-if="loading" class="text-center py-16"><div class="spinner" style="margin:0 auto;"></div><p class="mt-4 font-space" style="color:var(--text-muted)">Loading...</p></div>
    <div v-else-if="ads.length === 0" class="card text-center py-16">
      <div style="font-size:3rem;opacity:0.2;margin-bottom:16px;">📢</div>
      <p class="font-space" style="color:var(--text-muted)">No ads yet. Create your first sponsored listing!</p>
    </div>
    <div v-else style="display:grid;gap:12px;">
      <div v-for="ad in ads" :key="ad.id" class="card">
        <div class="flex items-center justify-between">
          <div>
            <p class="font-space font-bold">{{ ad.ad_type === 'card' ? 'Card Ad' : 'PPC Ad' }} <span v-if="ad.placement">· {{ ad.placement }}</span></p>
            <p class="font-space" style="color:var(--text-secondary);font-size:0.85rem;">Budget: ₹{{ Number(ad.daily_budget||0).toLocaleString('en-IN') }}/day · {{ ad.start_date }} → {{ ad.end_date }}</p>
          </div>
          <div class="text-right flex gap-2 items-center">
            <span class="badge" :class="statusBadge(ad.status)">{{ ad.status?.toUpperCase() }}</span>
            <button class="btn btn-sm" :class="ad.status==='active'?'btn-warning':'btn-success'" @click="handleToggle(ad)">{{ ad.status==='active' ? 'PAUSE' : 'ACTIVATE' }}</button>
            <button class="btn btn-sm btn-secondary" @click="handlePerformance(ad)">📊</button>
            <button class="btn btn-sm btn-secondary" @click="openEdit(ad)">✏️</button>
            <button class="btn btn-sm" style="background:var(--error);color:white;" @click="handleDelete(ad)">🗑️</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Form Modal -->
    <div v-if="showForm" class="modal-overlay" @click.self="showForm=false">
      <div class="modal-card">
        <h2 class="font-space font-bold mb-4">{{ editItem ? 'Edit' : 'Create' }} Ad</h2>
        <div style="display:grid;gap:12px;">
          <div>
            <label class="form-label">Ad Type</label>
            <select v-model="form.ad_type" class="form-input"><option value="ppc">PPC (Pay Per Click)</option><option value="card">Card (Fixed)</option></select>
          </div>
          <div v-if="form.ad_type === 'ppc'">
            <label class="form-label">Placement</label>
            <select v-model="form.placement" class="form-input"><option value="listings">Listings</option><option value="search">Search</option><option value="both">Both</option></select>
          </div>
          <div>
            <label class="form-label">Daily Budget (₹)</label>
            <input type="number" v-model="form.daily_budget" class="form-input" min="1" />
          </div>
          <div>
            <label class="form-label">Start Date</label>
            <input type="date" v-model="form.start_date" class="form-input" />
          </div>
          <div>
            <label class="form-label">Duration (weeks)</label>
            <input type="number" v-model="form.duration_weeks" class="form-input" min="1" />
          </div>
          <button @click="handleSave" class="btn btn-primary w-full" :disabled="saving">{{ saving ? 'Saving...' : 'Save Ad' }}</button>
        </div>
      </div>
    </div>

    <!-- Performance Modal -->
    <div v-if="showPerf && perf" class="modal-overlay" @click.self="showPerf=false">
      <div class="modal-card">
        <div class="flex justify-between items-center mb-4">
          <h2 class="font-space font-bold">Performance</h2>
          <button class="btn btn-sm btn-secondary" @click="showPerf=false">✕</button>
        </div>
        <div style="display:grid;gap:12px;">
          <div class="flex justify-between"><span style="color:var(--text-secondary)">Impressions</span><span>{{ perf.impressions || 0 }}</span></div>
          <div class="flex justify-between"><span style="color:var(--text-secondary)">Clicks</span><span>{{ perf.clicks || 0 }}</span></div>
          <div class="flex justify-between"><span style="color:var(--text-secondary)">CTR</span><span>{{ perf.ctr || '0%' }}</span></div>
          <div class="flex justify-between"><span style="color:var(--text-secondary)">Orders</span><span>{{ perf.attributed_orders || 0 }}</span></div>
          <div class="flex justify-between"><span style="color:var(--text-secondary)">Spend</span><span>₹{{ Number(perf.spend || 0).toLocaleString('en-IN') }}</span></div>
          <div class="flex justify-between"><span style="color:var(--text-secondary)">Revenue</span><span>₹{{ Number(perf.revenue || 0).toLocaleString('en-IN') }}</span></div>
        </div>
      </div>
    </div>
  </div>
</template>
