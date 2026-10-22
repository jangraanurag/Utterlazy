<template>
  <div>
    <div class="flex justify-between items-center mb-8">
      <div>
        <h1 class="font-display text-2xl font-bold text-white">Global Categories</h1>
        <p class="text-xs text-on-surface-variant uppercase tracking-widest mt-1">Super Admin food classifications</p>
      </div>
      <button @click="openCreateModal" class="px-5 py-3 rounded-xl sovereign-gradient text-white text-sm font-bold flex items-center gap-2 shadow-lg hover:scale-105 active:scale-95 transition-all">
        <span class="material-symbols-outlined text-[18px]">add_circle</span>
        Add Category
      </button>
    </div>

    <!-- Error Alert -->
    <div v-if="error" class="mb-6 bg-red-950/20 border border-red-500/30 text-red-400 p-4 rounded-xl flex items-center gap-3">
      <span class="material-symbols-outlined text-[20px]">error</span>
      <span class="text-sm font-bold">{{ error }}</span>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="py-20 text-center">
      <span class="material-symbols-outlined text-4xl animate-spin text-primary">progress_activity</span>
    </div>

    <!-- Empty State -->
    <div v-else-if="categories.length === 0" class="bg-surface rounded-2xl border border-white/5 p-12 text-center">
      <span class="material-symbols-outlined text-5xl text-on-surface-variant opacity-30 mb-3 block">category</span>
      <h3 class="font-bold text-lg text-white mb-1">No Categories Found</h3>
      <p class="text-sm text-on-surface-variant mb-6">Create global food classifications to filter and list catalog items.</p>
    </div>

    <!-- Grid List -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-for="cat in categories" :key="cat.id" class="bg-surface rounded-2xl border border-white/5 overflow-hidden flex flex-col hover:border-primary/20 transition-all group">
        <div class="h-44 w-full relative overflow-hidden bg-surface-container">
          <img :src="cat.image_url || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=600&q=80'" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
          <div class="absolute inset-0 bg-gradient-to-t from-surface to-transparent opacity-60"></div>
          <div class="absolute top-4 right-4 bg-surface/80 backdrop-blur-md px-3 py-1 rounded-full text-[10px] font-bold text-primary border border-white/5 uppercase tracking-wider">
            Order: {{ cat.sort_order }}
          </div>
        </div>
        <div class="p-6 flex-1 flex flex-col">
          <h3 class="font-display text-lg font-bold text-white mb-2">{{ cat.name }}</h3>
          <p class="text-sm text-on-surface-variant line-clamp-2 mb-6 flex-1">{{ cat.description || 'No description provided.' }}</p>
          <div class="flex items-center justify-between border-t border-white/5 pt-4">
            <span :class="cat.is_active ? 'bg-green-500/10 text-green-400 border border-green-500/20' : 'bg-stone-500/10 text-stone-400 border border-stone-500/20'" class="px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider">
              {{ cat.is_active ? 'Active' : 'Inactive' }}
            </span>
            <div class="flex items-center gap-2">
              <button @click="openEditModal(cat)" class="p-2.5 rounded-xl bg-white/5 hover:bg-white/10 text-on-surface hover:text-white transition-all"><span class="material-symbols-outlined text-[18px]">edit</span></button>
              <button @click="handleDelete(cat.id)" class="p-2.5 rounded-xl bg-red-500/5 hover:bg-red-500/10 text-red-400 transition-all"><span class="material-symbols-outlined text-[18px]">delete</span></button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Add/Edit Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center z-50 p-6" @click.self="closeModal">
      <div class="bg-surface rounded-3xl p-8 w-full max-w-lg border border-white/10 shadow-2xl relative">
        <button @click="closeModal" class="absolute top-6 right-6 p-2 rounded-xl hover:bg-white/5 text-on-surface-variant hover:text-white transition-all">
          <span class="material-symbols-outlined text-[20px]">close</span>
        </button>
        <h3 class="text-xl font-display font-bold text-white mb-6">{{ editMode ? 'Edit Category' : 'Create Category' }}</h3>
        
        <div class="space-y-4">
          <div>
            <label class="block text-[10px] font-black uppercase tracking-wider text-on-surface-variant mb-2">Category Name</label>
            <input v-model="form.name" placeholder="e.g. Biryani, Pizza, Rolls" class="w-full px-5 py-3.5 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-white font-medium focus:outline-none focus:border-primary transition-all" />
          </div>

          <div>
            <label class="block text-[10px] font-black uppercase tracking-wider text-on-surface-variant mb-2">Description</label>
            <textarea v-model="form.description" rows="3" placeholder="Brief details about the category items..." class="w-full px-5 py-3.5 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-white font-medium focus:outline-none focus:border-primary transition-all resize-none"></textarea>
          </div>

          <div>
            <label class="block text-[10px] font-black uppercase tracking-wider text-on-surface-variant mb-2">Image URL</label>
            <input v-model="form.image_url" placeholder="https://unsplash.com/... or similar" class="w-full px-5 py-3.5 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-white font-medium focus:outline-none focus:border-primary transition-all" />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-[10px] font-black uppercase tracking-wider text-on-surface-variant mb-2">Sort Order</label>
              <input v-model.number="form.sort_order" type="number" class="w-full px-5 py-3.5 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-white font-medium focus:outline-none focus:border-primary transition-all" />
            </div>
            <div>
              <label class="block text-[10px] font-black uppercase tracking-wider text-on-surface-variant mb-2">Status</label>
              <div class="flex items-center h-[54px] px-5 bg-surface-container-lowest border border-outline-variant/30 rounded-xl">
                <label class="flex items-center cursor-pointer gap-3">
                  <input v-model="form.is_active" type="checkbox" class="accent-primary w-4 h-4 cursor-pointer" />
                  <span class="text-sm font-bold text-white">{{ form.is_active ? 'Active' : 'Inactive' }}</span>
                </label>
              </div>
            </div>
          </div>

          <button class="w-full py-4 rounded-xl sovereign-gradient text-white font-black text-xs tracking-widest uppercase shadow-lg shadow-primary/20 hover:scale-[1.02] active:scale-[0.98] transition-all mt-4" @click="handleSave">
            {{ editMode ? 'SAVE CHANGES' : 'CREATE CATEGORY' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getGlobalCategories, createGlobalCategory, updateGlobalCategory, deleteGlobalCategory } from '../api'

const categories = ref([])
const loading = ref(true)
const error = ref('')
const showModal = ref(false)
const editMode = ref(false)
const selectedId = ref(null)

const form = ref({
  name: '',
  description: '',
  image_url: '',
  sort_order: 0,
  is_active: true
})

const fetchCategories = async () => {
  loading.value = true
  error.value = ''
  try {
    categories.value = await getGlobalCategories()
  } catch (err) {
    console.error(err)
    error.value = 'Failed to load global categories. Please try again.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchCategories)

const openCreateModal = () => {
  editMode.value = false
  selectedId.value = null
  form.value = {
    name: '',
    description: '',
    image_url: '',
    sort_order: categories.value.length + 1,
    is_active: true
  }
  showModal.value = true
}

const openEditModal = (cat) => {
  editMode.value = true
  selectedId.value = cat.id
  form.value = { ...cat, is_active: !!cat.is_active }
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
}

const handleSave = async () => {
  if (!form.value.name.trim()) {
    error.value = 'Category name is required.'
    return
  }

  error.value = ''
  try {
    if (editMode.value) {
      await updateGlobalCategory(selectedId.value, form.value)
    } else {
      await createGlobalCategory(form.value)
    }
    showModal.value = false
    await fetchCategories()
  } catch (err) {
    console.error(err)
    error.value = err.message || 'Failed to save category.'
  }
}

const handleDelete = async (id) => {
  if (!confirm('Are you sure you want to delete this category? All mapped items will be unmapped.')) return
  error.value = ''
  try {
    await deleteGlobalCategory(id)
    await fetchCategories()
  } catch (err) {
    console.error(err)
    error.value = err.message || 'Failed to delete category.'
  }
}
</script>
