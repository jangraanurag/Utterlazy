<template>
  <div>
    <div class="flex justify-between items-center mb-8">
      <h1 class="font-display text-2xl font-bold">Banner Management</h1>
      <button @click="openCreate" class="px-4 py-2 rounded-xl sovereign-gradient text-white text-sm font-bold">+ Create Banner</button>
    </div>

    <div v-if="loading" class="text-center py-12 text-on-surface-variant">
      <span class="material-symbols-outlined text-4xl block mb-2">sync</span>
      Loading banners...
    </div>

    <div v-else-if="error" class="text-center py-12">
      <span class="material-symbols-outlined text-4xl block mb-2 text-red-400">error</span>
      <p class="text-red-400 font-bold mb-2">Failed to load banners</p>
      <p class="text-on-surface-variant text-sm mb-4">{{ error }}</p>
      <button @click="fetchBanners" class="px-4 py-2 rounded-xl sovereign-gradient text-white text-sm font-bold">Retry</button>
    </div>

    <div v-else-if="banners.length === 0" class="text-center py-16 bg-surface rounded-2xl border border-outline-variant/30">
      <span class="material-symbols-outlined text-5xl block mb-3 text-on-surface-variant">view_carousel</span>
      <p class="text-on-surface-variant font-bold mb-1">No banners yet</p>
      <p class="text-sm text-on-surface-variant/60">Create your first banner to get started.</p>
    </div>

    <div v-else class="space-y-4">
      <div v-for="banner in banners" :key="banner.id" class="bg-surface rounded-2xl border border-outline-variant/30 p-6 flex items-center gap-6">
        <img :src="banner.image_url" class="w-32 h-20 rounded-xl object-cover bg-surface-container-lowest flex-shrink-0" />
        <div class="flex-1 min-w-0">
          <h3 class="font-bold truncate">{{ banner.title }}</h3>
          <p class="text-sm text-on-surface-variant mt-1">{{ banner.position }} &bull; {{ banner.target_audience }}</p>
          <p v-if="banner.sort_order !== undefined" class="text-xs text-on-surface-variant/50 mt-0.5">Sort: {{ banner.sort_order }}</p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
          <span :class="banner.is_active ? 'bg-green-500/10 text-green-400 border-green-500/30' : 'bg-stone-500/10 text-stone-400 border-stone-500/30'" class="px-3 py-1 rounded-full text-xs font-bold border">
            {{ banner.is_active ? 'Active' : 'Inactive' }}
          </span>
          <button @click="openEdit(banner)" class="p-2 rounded-lg hover:bg-white/5 transition-colors">
            <span class="material-symbols-outlined text-[18px]">edit</span>
          </button>
          <button @click="confirmDelete(banner)" class="p-2 rounded-lg hover:bg-white/5 transition-colors text-red-400">
            <span class="material-symbols-outlined text-[18px]">delete</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Create/Edit Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50" @click.self="closeModal">
      <div class="bg-surface rounded-2xl p-8 w-full max-w-lg border border-outline-variant/30 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
          <h3 class="text-xl font-bold">{{ editing ? 'Edit Banner' : 'Create Banner' }}</h3>
          <button @click="closeModal" class="p-1 rounded-lg hover:bg-white/5 transition-colors">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="space-y-4">
          <div>
            <label class="text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1.5 block">Title</label>
            <input v-model="form.title" placeholder="Banner Title" class="w-full px-5 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-on-surface placeholder-on-surface-variant/40 focus:outline-none focus:border-primary/50 transition-colors" />
          </div>
          <div>
            <label class="text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1.5 block">Image URL</label>
            <input v-model="form.image_url" placeholder="https://..." class="w-full px-5 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-on-surface placeholder-on-surface-variant/40 focus:outline-none focus:border-primary/50 transition-colors" />
          </div>
          <div>
            <label class="text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1.5 block">Link URL <span class="text-on-surface-variant/50 font-normal normal-case">(optional)</span></label>
            <input v-model="form.link_url" placeholder="https://..." class="w-full px-5 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-on-surface placeholder-on-surface-variant/40 focus:outline-none focus:border-primary/50 transition-colors" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1.5 block">Position</label>
              <select v-model="form.position" class="w-full px-5 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-on-surface focus:outline-none focus:border-primary/50 transition-colors">
                <option value="home_top">Home Top</option>
                <option value="home_mid">Home Mid</option>
                <option value="restaurant_page">Restaurant Page</option>
              </select>
            </div>
            <div>
              <label class="text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1.5 block">Target Audience</label>
              <select v-model="form.target_audience" class="w-full px-5 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-on-surface focus:outline-none focus:border-primary/50 transition-colors">
                <option value="all">All Users</option>
                <option value="new_users">New Users</option>
                <option value="returning_users">Returning Users</option>
              </select>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1.5 block">Sort Order</label>
              <input v-model.number="form.sort_order" type="number" min="0" placeholder="0" class="w-full px-5 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-on-surface placeholder-on-surface-variant/40 focus:outline-none focus:border-primary/50 transition-colors" />
            </div>
            <div class="flex items-end pb-3">
              <label class="flex items-center gap-3 cursor-pointer">
                <input v-model="form.is_active" type="checkbox" class="w-5 h-5 rounded accent-primary" />
                <span class="text-sm font-bold">Active</span>
              </label>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1.5 block">Start Date</label>
              <input v-model="form.start_date" type="date" class="w-full px-5 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-on-surface focus:outline-none focus:border-primary/50 transition-colors" />
            </div>
            <div>
              <label class="text-xs font-bold uppercase tracking-wider text-on-surface-variant mb-1.5 block">End Date</label>
              <input v-model="form.end_date" type="date" class="w-full px-5 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-on-surface focus:outline-none focus:border-primary/50 transition-colors" />
            </div>
          </div>
          <div v-if="formError" class="text-red-400 text-sm font-medium">{{ formError }}</div>
          <button @click="saveBanner" :disabled="saving" class="w-full py-3 rounded-xl sovereign-gradient text-white font-bold disabled:opacity-50 transition-opacity">
            {{ saving ? 'Saving...' : (editing ? 'Update Banner' : 'Create Banner') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation -->
    <div v-if="deleteTarget" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50" @click.self="deleteTarget = null">
      <div class="bg-surface rounded-2xl p-8 w-full max-w-sm border border-outline-variant/30 text-center">
        <span class="material-symbols-outlined text-5xl text-red-400 block mb-3">warning</span>
        <h3 class="text-xl font-bold mb-2">Delete Banner?</h3>
        <p class="text-on-surface-variant text-sm mb-6">Are you sure you want to delete "{{ deleteTarget.title }}"? This cannot be undone.</p>
        <div class="flex gap-3">
          <button @click="deleteTarget = null" class="flex-1 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30 font-bold text-sm">Cancel</button>
          <button @click="deleteBanner" :disabled="deleting" class="flex-1 py-3 rounded-xl bg-red-500/20 text-red-400 font-bold border border-red-500/30 text-sm disabled:opacity-50 transition-opacity">
            {{ deleting ? 'Deleting...' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getBanners, createBanner, updateBanner, deleteBanner as deleteBannerApi } from '../api.js'

const banners = ref([])
const loading = ref(true)
const error = ref(null)
const showModal = ref(false)
const editing = ref(null)
const saving = ref(false)
const deleting = ref(false)
const formError = ref(null)
const deleteTarget = ref(null)

const defaultForm = () => ({
  title: '',
  image_url: '',
  link_url: '',
  position: 'home_top',
  target_audience: 'all',
  sort_order: 0,
  is_active: true,
  start_date: '',
  end_date: '',
})

const form = ref(defaultForm())

const fetchBanners = async () => {
  loading.value = true
  error.value = null
  try {
    banners.value = await getBanners()
  } catch (e) {
    error.value = e.message || 'Unknown error'
  } finally {
    loading.value = false
  }
}

const openCreate = () => {
  editing.value = null
  form.value = defaultForm()
  formError.value = null
  showModal.value = true
}

const openEdit = (banner) => {
  editing.value = banner
  form.value = {
    title: banner.title || '',
    image_url: banner.image_url || '',
    link_url: banner.link_url || '',
    position: banner.position || 'home_top',
    target_audience: banner.target_audience || 'all',
    sort_order: banner.sort_order ?? 0,
    is_active: banner.is_active ?? true,
    start_date: banner.start_date ? banner.start_date.slice(0, 10) : '',
    end_date: banner.end_date ? banner.end_date.slice(0, 10) : '',
  }
  formError.value = null
  showModal.value = true
}

const closeModal = () => {
  showModal.value = false
  editing.value = null
}

const saveBanner = async () => {
  formError.value = null
  if (!form.value.title.trim()) { formError.value = 'Title is required'; return }
  if (!form.value.image_url.trim()) { formError.value = 'Image URL is required'; return }

  saving.value = true
  try {
    const payload = { ...form.value }
    if (!payload.link_url) payload.link_url = null
    if (!payload.start_date) payload.start_date = null
    if (!payload.end_date) payload.end_date = null

    if (editing.value) {
      const updated = await updateBanner(editing.value.id, payload)
      const idx = banners.value.findIndex(b => b.id === editing.value.id)
      if (idx !== -1) banners.value[idx] = updated
    } else {
      const created = await createBanner(payload)
      banners.value.push(created)
    }
    closeModal()
  } catch (e) {
    formError.value = e.message || e?.title?.[0] || 'Failed to save banner'
  } finally {
    saving.value = false
  }
}

const confirmDelete = (banner) => {
  deleteTarget.value = banner
}

const deleteBanner = async () => {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await deleteBannerApi(deleteTarget.value.id)
    banners.value = banners.value.filter(b => b.id !== deleteTarget.value.id)
    deleteTarget.value = null
  } catch (e) {
    alert('Failed to delete banner: ' + (e.message || 'Unknown error'))
  } finally {
    deleting.value = false
  }
}

onMounted(fetchBanners)
</script>
