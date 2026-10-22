<script setup>
import { ref, onMounted } from 'vue'
import api from '../api'

const menuItems = ref([])
const loading = ref(true)
const recording = ref(null)
const reels = ref({})
const showReelModal = ref(false)
const selectedItem = ref(null)
const reelUrl = ref('')

onMounted(async () => {
  try {
    const res = await api.get('/vendor/menu')
    menuItems.value = res.data.categories?.flatMap(c => c.menu_items || []) || []
    const reelsRes = await api.get('/vendor/media/reels').catch(() => ({ data: { reels: [] } }))
    reelsRes.data.reels?.forEach(r => { reels.value[r.menu_item_id] = r.url })
  } catch (e) { console.error(e) }
  loading.value = false
})

async function saveReel() {
  if (!selectedItem.value || !reelUrl.value) return
  try {
    await api.post('/vendor/media/reel', { menu_item_id: selectedItem.value, url: reelUrl.value })
    reels.value[selectedItem.value] = reelUrl.value
    showReelModal.value = false
    reelUrl.value = ''
    selectedItem.value = null
  } catch (e) { alert('Failed to save reel') }
}
</script>

<template>
  <div>
    <div class="page-header">
      <h1 class="page-title">Video Menu</h1>
      <p class="page-subtitle">Add short reels for your dishes</p>
    </div>

    <div v-if="loading" class="text-center" style="padding: 60px;">Loading...</div>
    <div v-else-if="menuItems.length === 0" class="card text-center" style="padding: 60px;">
      <div style="font-size: 3rem; margin-bottom: 12px;">🎬</div>
      <p class="text-muted">Add menu items first to create reels</p>
    </div>
    <div v-else class="grid-kpi" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));">
      <div v-for="item in menuItems" :key="item.id" class="card">
        <div style="aspect-ratio: 9/16; background: #141a2b; border-radius: 16px; display: flex; align-items: center; justify-content: center; overflow: hidden; margin-bottom: 12px;">
          <video v-if="reels[item.id]" :src="reels[item.id]" style="width: 100%; height: 100%; object-fit: cover;" controls></video>
          <div v-else style="text-align: center; color: #6b7280;">
            <div style="font-size: 3rem; margin-bottom: 8px;">🎥</div>
            <p style="font-size: 0.8rem;">No reel yet</p>
          </div>
        </div>
        <h3 class="font-space" style="font-weight: 700; font-size: 1rem;">{{ item.name }}</h3>
        <p style="color: var(--sovereign-orange); font-weight: 700;">₹{{ item.price }}</p>
        <button class="btn btn-sm btn-primary mt-2 w-full" @click="selectedItem = item.id; showReelModal = true">
          {{ reels[item.id] ? 'Update Reel' : 'Add Reel' }}
        </button>
      </div>
    </div>

    <div v-if="showReelModal" class="modal-overlay" @click="showReelModal = false">
      <div class="modal" @click.stop>
        <div class="modal-header">
          <span class="modal-title">Add Video Reel</span>
          <button class="modal-close" @click="showReelModal = false">×</button>
        </div>
        <div class="p-6">
          <div class="form-group">
            <label class="form-label">Video URL (YouTube / MP4)</label>
            <input class="form-input" v-model="reelUrl" placeholder="https://youtube.com/shorts/..." />
          </div>
          <p style="font-size: 0.75rem; color: #6b7280; margin-bottom: 16px;">Upload a 15-60 second vertical video of this dish</p>
          <button class="btn btn-primary w-full" @click="saveReel">Save Reel</button>
        </div>
      </div>
    </div>
  </div>
</template>
