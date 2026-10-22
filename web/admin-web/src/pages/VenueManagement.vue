<template>
  <div>
    <div class="flex justify-between items-center mb-8">
      <h1 class="font-display text-2xl font-bold">Venue Management</h1>
      <div class="flex gap-3">
        <div class="relative">
          <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">search</span>
          <input v-model="searchQuery" placeholder="Search by name, email or phone..."
            class="w-72 pl-10 pr-4 py-2 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-sm placeholder:text-on-surface-variant/50 focus:outline-none focus:border-primary/50" />
        </div>
        <button @click="generatePayments" :disabled="generating"
          class="px-4 py-2 rounded-lg bg-primary/10 text-primary border border-primary/20 text-xs font-bold disabled:opacity-50">
          {{ generating ? 'Generating...' : 'Generate Weekly Payments' }}
        </button>
      </div>
    </div>

    <div v-if="loading" class="text-center py-12 text-on-surface-variant">Loading...</div>
    <div v-else-if="error" class="text-center py-12 text-red-500">{{ error }}</div>
    <div v-else-if="restaurants.length === 0" class="text-center py-12 text-on-surface-variant">No restaurants found.</div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <div v-for="r in restaurants" :key="r.id" @click="selectRestaurant(r)"
        class="bg-surface rounded-2xl border border-outline-variant/30 p-6 cursor-pointer hover:border-primary/30 transition-colors">
        <div class="flex items-start justify-between mb-3">
          <div class="min-w-0">
            <h3 class="font-bold text-base truncate">{{ r.name }}</h3>
            <p class="text-xs text-on-surface-variant truncate">{{ r.email }}</p>
          </div>
          <span :class="r.is_active ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'"
            class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase shrink-0 ml-2">
            {{ r.is_active ? 'active' : 'inactive' }}
          </span>
        </div>
        <p class="text-xs text-on-surface-variant mb-3 truncate">{{ r.phone }}</p>
        <div class="flex items-center gap-4 text-xs">
          <span class="text-on-surface-variant">Commission: <span class="text-white font-semibold">{{ r.commission_rate }}%</span></span>
          <span class="text-on-surface-variant">Rating: <span class="text-amber-400 font-semibold">{{ r.avg_rating ?? 'N/A' }}</span></span>
          <span class="text-on-surface-variant">Orders: <span class="text-white font-semibold">{{ r.orders_count ?? 0 }}</span></span>
        </div>
      </div>
    </div>

    <div v-if="meta" class="flex justify-center gap-4 mt-8">
      <button :disabled="!meta.prev_page_url" @click="loadPage(meta.current_page - 1)"
        class="px-4 py-2 rounded-lg bg-surface border border-outline-variant/30 text-sm disabled:opacity-30">Prev</button>
      <span class="px-4 py-2 text-sm text-on-surface-variant">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
      <button :disabled="!meta.next_page_url" @click="loadPage(meta.current_page + 1)"
        class="px-4 py-2 rounded-lg bg-surface border border-outline-variant/30 text-sm disabled:opacity-30">Next</button>
    </div>

    <div v-if="selected" class="fixed inset-0 z-50">
      <div class="absolute inset-0 bg-black/50" @click="closePanel"></div>
      <div class="absolute right-0 top-0 h-full w-full max-w-lg bg-surface border-l border-outline-variant/30 overflow-y-auto">
        <div class="p-6">
          <div class="flex items-center justify-between mb-6">
            <h2 class="font-bold text-lg truncate">{{ selected.name }}</h2>
            <button @click="closePanel" class="text-on-surface-variant hover:text-white">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <div class="space-y-4 mb-6">
            <div class="flex items-center justify-between text-sm">
              <span class="text-on-surface-variant">Status</span>
              <span :class="selected.is_active ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'"
                class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase">
                {{ selected.is_active ? 'active' : 'inactive' }}
              </span>
            </div>
            <div class="flex items-center justify-between text-sm">
              <span class="text-on-surface-variant">Commission Rate</span>
              <span class="text-white font-semibold">{{ detail?.commission_rate ?? selected.commission_rate }}%</span>
            </div>
            <div class="flex items-center justify-between text-sm">
              <span class="text-on-surface-variant">Avg Rating</span>
              <span class="text-amber-400 font-semibold">{{ detail?.avg_rating ?? selected.avg_rating ?? 'N/A' }}</span>
            </div>
            <div class="flex items-center justify-between text-sm">
              <span class="text-on-surface-variant">Total Orders</span>
              <span class="text-white font-semibold">{{ detail?.orders_count ?? selected.orders_count ?? 0 }}</span>
            </div>
            <div v-if="detail?.email" class="text-sm"><span class="text-on-surface-variant">Email:</span> {{ detail.email }}</div>
            <div v-if="detail?.phone" class="text-sm"><span class="text-on-surface-variant">Phone:</span> {{ detail.phone }}</div>
          </div>

          <hr class="border-outline-variant/20 mb-6" />

          <h3 class="font-bold text-sm mb-4">Edit Venue</h3>
          <div class="space-y-4">
            <div>
              <label class="text-xs text-on-surface-variant block mb-1">Name</label>
              <input v-model="editForm.name" class="w-full px-4 py-2.5 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-sm" />
            </div>
            <div>
              <label class="text-xs text-on-surface-variant block mb-1">Phone</label>
              <input v-model="editForm.phone" class="w-full px-4 py-2.5 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-sm" />
            </div>
            <div>
              <label class="text-xs text-on-surface-variant block mb-1">Commission Rate (%)</label>
              <input v-model.number="editForm.commission_rate" type="number" min="0" max="100" step="0.1"
                class="w-full px-4 py-2.5 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-sm" />
            </div>
            <div class="flex items-center gap-3">
              <label class="text-xs text-on-surface-variant">Active</label>
              <button @click="editForm.is_active = !editForm.is_active"
                :class="editForm.is_active ? 'bg-green-500' : 'bg-white/20'"
                class="w-10 h-5 rounded-full relative transition-colors">
                <span :class="editForm.is_active ? 'translate-x-5' : 'translate-x-0.5'"
                  class="absolute top-0.5 left-0 w-4 h-4 bg-white rounded-full transition-transform"></span>
              </button>
            </div>
            <div class="flex items-center gap-3">
              <input v-model="editForm.is_visible" type="checkbox" id="is_visible" class="rounded" />
              <label for="is_visible" class="text-xs text-on-surface-variant">Visible on platform</label>
            </div>
            <div>
              <label class="text-xs text-on-surface-variant block mb-1">Description</label>
              <textarea v-model="editForm.description" rows="3"
                class="w-full px-4 py-2.5 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-sm resize-none"></textarea>
            </div>
            <p v-if="editError" class="text-red-500 text-xs">{{ editError }}</p>
            <button @click="saveVenue" :disabled="saving"
              class="w-full py-2.5 rounded-xl bg-primary/10 text-primary border border-primary/20 text-sm font-bold disabled:opacity-50">
              {{ saving ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { getRestaurants, getRestaurantDetail, updateRestaurant, generateWeeklyPayments } from '../api.js'

const restaurants = ref([])
const loading = ref(true)
const error = ref(null)
const meta = ref(null)
const searchQuery = ref('')
const selected = ref(null)
const detail = ref(null)
const saving = ref(false)
const editError = ref('')
const generating = ref(false)

const editForm = ref({
  name: '',
  phone: '',
  commission_rate: 0,
  is_active: true,
  is_visible: true,
  description: ''
})

let searchTimeout = null
watch(searchQuery, () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => loadPage(1), 400)
})

const loadPage = async (page) => {
  loading.value = true
  error.value = null
  try {
    const data = await getRestaurants(searchQuery.value, page)
    restaurants.value = data.data || []
    meta.value = { current_page: data.current_page, last_page: data.last_page, next_page_url: data.next_page_url, prev_page_url: data.prev_page_url }
  } catch (e) {
    error.value = e.message || 'Failed to load'
    restaurants.value = []
  } finally {
    loading.value = false
  }
}

const selectRestaurant = async (r) => {
  selected.value = r
  detail.value = null
  editForm.value = {
    name: r.name || '',
    phone: r.phone || '',
    commission_rate: r.commission_rate || 0,
    is_active: r.is_active ?? true,
    is_visible: r.is_visible ?? true,
    description: r.description || ''
  }
  try {
    const d = await getRestaurantDetail(r.id)
    detail.value = d
    editForm.value.name = d.name || editForm.value.name
    editForm.value.phone = d.phone || editForm.value.phone
    editForm.value.commission_rate = d.commission_rate ?? editForm.value.commission_rate
    editForm.value.is_active = d.is_active ?? editForm.value.is_active
    editForm.value.is_visible = d.is_visible ?? editForm.value.is_visible
    editForm.value.description = d.description || editForm.value.description
  } catch { /* use card data */ }
}

const closePanel = () => {
  selected.value = null
  detail.value = null
}

const saveVenue = async () => {
  saving.value = true
  editError.value = ''
  try {
    await updateRestaurant(selected.value.id, {
      name: editForm.value.name,
      phone: editForm.value.phone,
      commission_rate: editForm.value.commission_rate,
      is_active: editForm.value.is_active,
      is_visible: editForm.value.is_visible,
      description: editForm.value.description
    })
    selected.value = null
    detail.value = null
    await loadPage(meta.value?.current_page || 1)
  } catch (e) {
    editError.value = e.message || 'Failed to update'
  } finally {
    saving.value = false
  }
}

const generatePayments = async () => {
  generating.value = true
  try {
    await generateWeeklyPayments()
    alert('Weekly payments generated successfully.')
  } catch (e) {
    alert(e.message || 'Failed to generate payments')
  } finally {
    generating.value = false
  }
}

onMounted(() => loadPage(1))
</script>
