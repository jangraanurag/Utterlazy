<script setup>
import { ref, onMounted } from 'vue'
import api from '../api'

const combos = ref([])
const loading = ref(true)
const showCreate = ref(false)
const newCombo = ref({ name: '', description: '', original_price: '', combo_price: '' })

async function fetchCombos() {
  loading.value = true
  try {
    const res = await api.get('/vendor/combos')
    combos.value = res.data || res
  } catch (e) { combos.value = [] }
  loading.value = false
}

onMounted(fetchCombos)

async function createCombo() {
  try {
    await api.post('/vendor/combos', newCombo.value)
    showCreate.value = false
    newCombo.value = { name: '', description: '', original_price: '', combo_price: '' }
    fetchCombos()
  } catch (e) { alert('Failed') }
}

async function toggleCombo(combo) {
  try { await api.patch(`/vendor/combos/${combo.id}`, { is_active: !combo.is_active }); fetchCombos() }
  catch (e) { console.error(e) }
}

const discountPct = (c) => Math.round((1 - c.combo_price / c.original_price) * 100)
</script>

<template>
  <div>
    <div class="page-header">
      <h1 class="page-title">Combo Offers</h1>
      <button class="btn btn-primary" @click="showCreate = true">+ Create Combo</button>
    </div>

    <div v-if="loading" class="text-center" style="padding: 60px;">Loading...</div>
    <div v-else-if="combos.length === 0" class="card text-center" style="padding: 60px;">
      <div style="font-size: 3rem; margin-bottom: 12px;">🎯</div>
      <p class="text-muted">No combo offers yet</p>
    </div>
    <div v-else style="display: grid; gap: 16px;">
      <div v-for="combo in combos" :key="combo.id" class="card">
        <div class="flex-between mb-2">
          <h3 class="font-bold" style="font-size: 1.1rem;">{{ combo.name }}</h3>
          <label class="switch">
            <input type="checkbox" :checked="combo.is_active" @change="toggleCombo(combo)" />
            <span class="slider"></span>
          </label>
        </div>
        <p class="text-sm text-muted mb-3">{{ combo.description }}</p>
        <div class="flex items-center gap-3">
          <span class="font-bold" style="font-size: 1.2rem; color: var(--sovereign-orange);">₹{{ combo.combo_price }}</span>
          <span class="text-sm text-muted" style="text-decoration: line-through;">₹{{ combo.original_price }}</span>
          <span class="badge badge-success">{{ discountPct(combo) }}% OFF</span>
        </div>
      </div>
    </div>

    <div v-if="showCreate" class="modal-overlay" @click="showCreate = false">
      <div class="modal" @click.stop>
        <div class="modal-header">
          <span class="modal-title">Create Combo</span>
          <button class="modal-close" @click="showCreate = false">×</button>
        </div>
        <form @submit.prevent="createCombo" class="p-6">
          <div class="form-group">
            <label class="form-label">Combo Name</label>
            <input class="form-input" v-model="newCombo.name" required placeholder="Family Feast" />
          </div>
          <div class="form-group">
            <label class="form-label">Description</label>
            <input class="form-input" v-model="newCombo.description" placeholder="Butter Chicken + 4 Naan + Biryani" />
          </div>
          <div class="grid-2">
            <div class="form-group">
              <label class="form-label">Original Price (₹)</label>
              <input type="number" class="form-input" v-model="newCombo.original_price" placeholder="850" />
            </div>
            <div class="form-group">
              <label class="form-label">Combo Price (₹)</label>
              <input type="number" class="form-input" v-model="newCombo.combo_price" placeholder="599" />
            </div>
          </div>
          <button type="submit" class="btn btn-primary mt-4 w-full">Create Combo</button>
        </form>
      </div>
    </div>
  </div>
</template>
