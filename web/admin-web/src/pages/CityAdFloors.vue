<template>
  <div>
    <h1 class="text-3xl font-serif font-bold text-on-surface mb-2">City Ad Floors</h1>
    <p class="text-on-surface-variant mb-8">
      Minimum CPC and slot cap per city. Floor changes are audited and never rewrite the pinned floor of an in-flight placement.
    </p>

    <div v-if="loading" class="py-12 text-center text-on-surface-variant">Loading...</div>

    <div v-else class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <div v-for="city in cities" :key="city.id" class="bg-surface-container-lowest rounded-xl border border-outline-variant/30 p-5">
        <h3 class="text-lg font-bold mb-1">{{ city.name }}</h3>
        <p class="text-xs text-on-surface-variant mb-4">{{ city.state || '—' }}</p>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs uppercase tracking-wider text-on-surface-variant mb-1">Floor CPC (₹)</label>
            <input type="number" min="0.01" max="1000" step="0.01" v-model.number="draft[city.id].floor_cpc"
              class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs uppercase tracking-wider text-on-surface-variant mb-1">Max slots (1–20)</label>
            <input type="number" min="1" max="20" step="1" v-model.number="draft[city.id].max_slots"
              class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-3 py-2 text-sm" />
          </div>
        </div>

        <div class="flex items-center gap-3 mt-4">
          <button @click="save(city.id)" class="px-4 py-2 rounded-lg bg-primary text-white text-sm font-bold">Save</button>
          <span v-if="saved[city.id]" class="text-xs text-green-400">Saved</span>
          <span v-if="errors[city.id]" class="text-xs text-red-400">{{ errors[city.id] }}</span>
        </div>
      </div>

      <div v-if="!cities.length" class="text-center py-12 text-on-surface-variant col-span-2">No cities configured.</div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { getWaFloors, updateWaFloor, getCities } from '../api'

const cities = ref([])
const draft = reactive({})
const saved = reactive({})
const errors = reactive({})
const loading = ref(true)

async function load() {
  loading.value = true
  try {
    const res = await getWaFloors()
    const rows = Array.isArray(res) ? res : (res.data || [])
    const populated = new Map(rows.map(r => [r.city_id, r]))
    const cityRes = await getCities()
    const list = cityRes.data || cityRes || []
    cities.value = (Array.isArray(list) ? list : []).map(c => ({
      id: c.id, name: c.name, state: c.state,
    }))
    for (const c of cities.value) {
      const existing = populated.get(c.id)
      draft[c.id] = {
        floor_cpc: existing ? Number(existing.floor_cpc) : 5,
        max_slots: existing ? Number(existing.max_slots) : 5,
      }
    }
  } finally {
    loading.value = false
  }
}

async function save(cityId) {
  saved[cityId] = false
  errors[cityId] = ''
  try {
    await updateWaFloor(cityId, Number(draft[cityId].floor_cpc), Number(draft[cityId].max_slots))
    saved[cityId] = true
  } catch (e) {
    errors[cityId] = e.error || e.message || 'Save failed'
  }
}

onMounted(load)
</script>
