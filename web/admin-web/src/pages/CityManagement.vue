<template>
  <div>
    <div class="flex justify-between items-center mb-8">
      <h1 class="font-display text-2xl font-bold">City & Area Management</h1>
      <button @click="openCreate" class="px-4 py-2 rounded-xl sovereign-gradient text-white text-sm font-bold">+ Add City</button>
    </div>

    <div v-if="loading" class="text-center py-12 text-on-surface-variant">Loading cities...</div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-for="city in cities" :key="city.id" class="bg-surface rounded-2xl border border-outline-variant/30 p-6">
        <div class="flex justify-between items-start mb-4">
          <div>
            <h3 class="font-bold text-lg">{{ city.name }}</h3>
            <p class="text-sm text-on-surface-variant">{{ city.state }}</p>
          </div>
          <span :class="city.is_active ? 'bg-green-100 text-green-700' : 'bg-stone-100 text-stone-500'" class="px-3 py-1 rounded-full text-xs font-bold">{{ city.is_active ? 'Active' : 'Inactive' }}</span>
        </div>
        <div class="space-y-2 text-sm">
          <div class="flex justify-between"><span class="text-on-surface-variant">Base Delivery Fee</span><span class="font-bold">₹{{ city.base_delivery_fee }}</span></div>
          <div class="flex justify-between"><span class="text-on-surface-variant">Commission Rate</span><span class="font-bold">{{ city.base_commission_rate }}%</span></div>
        </div>
        <div class="flex gap-2 mt-4">
          <button @click="openEdit(city)" class="flex-1 py-2 rounded-lg bg-primary/10 text-primary text-xs font-bold">Edit</button>
          <button @click="toggleActive(city)" class="flex-1 py-2 rounded-lg bg-primary/10 text-primary text-xs font-bold">{{ city.is_active ? 'Deactivate' : 'Activate' }}</button>
          <button @click="confirmDelete(city)" class="flex-1 py-2 rounded-lg bg-red-500/10 text-red-400 text-xs font-bold">Delete</button>
        </div>
      </div>
    </div>

    <div v-if="showModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" @click.self="showModal = false">
      <div class="bg-surface rounded-2xl p-8 w-full max-w-md">
        <h3 class="text-xl font-bold mb-6">{{ editing ? 'Edit City' : 'Add City' }}</h3>
        <div class="space-y-4">
          <input v-model="form.name" placeholder="City Name" class="w-full px-5 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30" />
          <input v-model="form.state" placeholder="State" class="w-full px-5 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30" />
          <input v-model.number="form.base_delivery_fee" type="number" placeholder="Base Delivery Fee (₹)" class="w-full px-5 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30" />
          <input v-model.number="form.base_commission_rate" type="number" placeholder="Commission Rate (%)" class="w-full px-5 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30" />
          <label class="flex items-center gap-3 text-sm">
            <input v-model="form.is_active" type="checkbox" class="w-4 h-4" />
            Active
          </label>
          <button @click="saveCity" class="w-full py-3 rounded-xl sovereign-gradient text-white font-bold" :disabled="saving">{{ saving ? 'Saving...' : (editing ? 'Update City' : 'Create City') }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getCities, createCity, updateCity, deleteCity } from '../api.js'

const cities = ref([])
const loading = ref(true)
const showModal = ref(false)
const editing = ref(null)
const saving = ref(false)

const form = ref({ name: '', state: '', base_delivery_fee: 30, base_commission_rate: 15, is_active: true })

async function loadCities() {
  try {
    cities.value = await getCities()
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  form.value = { name: '', state: '', base_delivery_fee: 30, base_commission_rate: 15, is_active: true }
  showModal.value = true
}

function openEdit(city) {
  editing.value = city
  form.value = { name: city.name, state: city.state, base_delivery_fee: city.base_delivery_fee, base_commission_rate: city.base_commission_rate, is_active: city.is_active }
  showModal.value = true
}

async function saveCity() {
  saving.value = true
  try {
    if (editing.value) {
      const updated = await updateCity(editing.value.id, form.value)
      const idx = cities.value.findIndex(c => c.id === editing.value.id)
      if (idx !== -1) cities.value[idx] = updated
    } else {
      const created = await createCity(form.value)
      cities.value.push(created)
    }
    showModal.value = false
  } catch (e) {
    alert(e?.message || 'Failed to save city')
  } finally {
    saving.value = false
  }
}

async function toggleActive(city) {
  try {
    const updated = await updateCity(city.id, { is_active: !city.is_active })
    const idx = cities.value.findIndex(c => c.id === city.id)
    if (idx !== -1) cities.value[idx] = updated
  } catch (e) {
    alert('Failed to toggle status')
  }
}

async function confirmDelete(city) {
  if (!confirm(`Delete city "${city.name}"?`)) return
  try {
    await deleteCity(city.id)
    cities.value = cities.value.filter(c => c.id !== city.id)
  } catch (e) {
    alert('Failed to delete city')
  }
}

onMounted(loadCities)
</script>
