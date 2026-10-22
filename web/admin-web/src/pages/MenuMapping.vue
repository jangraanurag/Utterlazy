<template>
  <div>
    <div class="flex justify-between items-center mb-8">
      <div>
        <h1 class="font-display text-2xl font-bold text-white">Menu Item Mapping</h1>
        <p class="text-xs text-on-surface-variant uppercase tracking-widest mt-1">Map vendor products to global classifications</p>
      </div>
    </div>

    <!-- Feedback Alerts -->
    <div v-if="successMsg" class="mb-6 bg-green-950/20 border border-green-500/30 text-green-400 p-4 rounded-xl flex items-center gap-3">
      <span class="material-symbols-outlined text-[20px]">check_circle</span>
      <span class="text-sm font-bold">{{ successMsg }}</span>
    </div>
    <div v-if="error" class="mb-6 bg-red-950/20 border border-red-500/30 text-red-400 p-4 rounded-xl flex items-center gap-3">
      <span class="material-symbols-outlined text-[20px]">error</span>
      <span class="text-sm font-bold">{{ error }}</span>
    </div>

    <!-- Filters & Search -->
    <div class="bg-surface rounded-2xl border border-white/5 p-6 mb-8 flex flex-col md:flex-row gap-4 items-center justify-between">
      <div class="w-full md:w-96 relative">
        <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">search</span>
        <input v-model="search" type="text" placeholder="Search item or restaurant..." 
          class="w-full pl-12 pr-4 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-white font-medium focus:outline-none focus:border-primary transition-all text-sm" />
      </div>
      
      <div class="flex items-center gap-4 w-full md:w-auto">
        <span class="text-xs text-on-surface-variant uppercase font-bold tracking-wider shrink-0">Filter Category:</span>
        <select v-model="filterCategory" class="w-full md:w-56 px-4 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-white font-medium focus:outline-none focus:border-primary text-sm">
          <option value="all">All Items</option>
          <option value="unmapped">Unmapped Items Only</option>
          <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
        </select>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="py-20 text-center">
      <span class="material-symbols-outlined text-4xl animate-spin text-primary">progress_activity</span>
    </div>

    <!-- Table List -->
    <div v-else class="bg-surface rounded-2xl border border-white/5 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-white/5 bg-white/[0.02] text-[10px] font-black uppercase tracking-wider text-on-surface-variant">
              <th class="py-5 px-6">Product Item</th>
              <th class="py-5 px-6">Restaurant</th>
              <th class="py-5 px-6">Current Mapping</th>
              <th class="py-5 px-6">Modify Mapping</th>
              <th class="py-5 px-6 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/5 text-sm text-white">
            <tr v-for="item in filteredItems" :key="item.id" class="hover:bg-white/[0.01] transition-all">
              <td class="py-5 px-6">
                <div class="flex items-center gap-4">
                  <img :src="item.image_url || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=100&q=80'" class="w-12 h-12 rounded-xl object-cover" />
                  <div>
                    <h3 class="font-bold">{{ item.name }}</h3>
                    <p class="text-xs text-on-surface-variant mt-0.5">₹{{ item.price }}</p>
                  </div>
                </div>
              </td>
              <td class="py-5 px-6 font-bold text-on-surface-variant">{{ item.restaurant?.name || 'N/A' }}</td>
              <td class="py-5 px-6">
                <span v-if="item.global_category" class="bg-primary/10 text-primary border border-primary/20 px-3 py-1 rounded-full text-xs font-bold">
                  {{ item.global_category.name }}
                </span>
                <span v-else class="bg-stone-500/10 text-stone-400 border border-stone-500/20 px-3 py-1 rounded-full text-xs font-bold">
                  Unmapped
                </span>
              </td>
              <td class="py-5 px-6">
                <select v-model="item._selectedCategoryId" class="px-4 py-2.5 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-white focus:outline-none focus:border-primary text-xs w-48 font-bold">
                  <option :value="null">-- Unmapped --</option>
                  <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                </select>
              </td>
              <td class="py-5 px-6 text-right">
                <button @click="handleMap(item)" :disabled="item._isMapping"
                  class="px-4 py-2.5 rounded-xl sovereign-gradient text-white text-xs font-black tracking-widest uppercase hover:scale-105 active:scale-95 transition-all disabled:opacity-50 flex items-center gap-2 inline-flex">
                  <span class="material-symbols-outlined text-[16px]">link</span>
                  {{ item._isMapping ? 'Mapping...' : 'MAP' }}
                </button>
              </td>
            </tr>
            <tr v-if="filteredItems.length === 0">
              <td colspan="5" class="py-12 text-center text-on-surface-variant font-bold">No items match your search or filter selection.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { getGlobalCategories, getAdminMenuItems, updateAdminMenuItem } from '../api'

const categories = ref([])
const menuItems = ref([])
const loading = ref(true)
const search = ref('')
const filterCategory = ref('all')
const successMsg = ref('')
const error = ref('')

const fetchData = async () => {
  loading.value = true
  successMsg.value = ''
  error.value = ''
  try {
    const [cats, items] = await Promise.all([
      getGlobalCategories(),
      getAdminMenuItems()
    ])
    categories.value = cats
    menuItems.value = items.map(item => ({
      ...item,
      _selectedCategoryId: item.global_category_id,
      _isMapping: false
    }))
  } catch (err) {
    console.error(err)
    error.value = 'Failed to load catalog menu items or categories.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchData)

const filteredItems = computed(() => {
  return menuItems.value.filter(item => {
    // Search filter
    const matchesSearch = item.name.toLowerCase().includes(search.value.toLowerCase()) || 
                          (item.restaurant?.name || '').toLowerCase().includes(search.value.toLowerCase())
    
    if (!matchesSearch) return false

    // Category filter
    if (filterCategory.value === 'all') return true
    if (filterCategory.value === 'unmapped') return !item.global_category_id
    return item.global_category_id === filterCategory.value
  })
})

const handleMap = async (item) => {
  item._isMapping = true
  successMsg.value = ''
  error.value = ''
  try {
    const res = await updateAdminMenuItem(item.id, {
      global_category_id: item._selectedCategoryId
    })
    
    // Update local item
    item.global_category_id = res.global_category_id
    item.global_category = res.global_category
    successMsg.value = `Successfully mapped "${item.name}" to global category.`
    
    // Auto clear success message after 3 seconds
    setTimeout(() => {
      if (successMsg.value.includes(item.name)) successMsg.value = ''
    }, 3000)
  } catch (err) {
    console.error(err)
    error.value = err.message || 'Failed to update mapping.'
  } finally {
    item._isMapping = false
  }
}
</script>
