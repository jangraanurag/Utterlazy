<template>
  <div class="p-8">
    <div class="flex justify-between items-center mb-8">
      <div>
        <h1 class="text-3xl font-bold text-white">Opt-In Campaigns</h1>
        <p class="text-white/50 text-sm mt-1">Create and manage admin promotional campaigns</p>
      </div>
      <button @click="showForm = true" class="px-6 py-3 rounded-xl sovereign-gradient text-white font-bold text-sm uppercase tracking-wider">
        + New Campaign
      </button>
    </div>

    <div v-if="loading" class="text-center py-20 text-white/50">Loading...</div>

    <div v-else-if="campaigns.length === 0" class="text-center py-20 text-white/50">
      <p class="text-xl">No campaigns yet</p>
    </div>

    <div v-else class="grid gap-6">
      <div v-for="c in campaigns" :key="c.id"
        class="bg-surface rounded-2xl border border-white/5 p-6 hover:border-primary/20 transition-colors">
        <div class="flex items-start justify-between">
          <div>
            <div class="flex items-center gap-3">
              <h3 class="text-xl font-bold text-white">{{ c.name }}</h3>
              <span :class="c.is_active ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'"
                class="px-3 py-1 rounded-full text-xs font-bold">{{ c.is_active ? 'Active' : 'Inactive' }}</span>
            </div>
            <p v-if="c.description" class="text-white/50 text-sm mt-2">{{ c.description }}</p>
          </div>
          <div class="text-right text-sm text-white/50">
            <span class="font-bold text-primary">{{ c.restaurant_opt_ins_count || 0 }}</span> restaurants opted in
          </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
          <div class="bg-white/5 rounded-xl p-4">
            <p class="text-[10px] uppercase tracking-widest text-white/40">Discount</p>
            <p class="text-lg font-bold text-white mt-1">{{ c.discount_type === 'percentage' ? c.discount_value + '%' : '₹' + c.discount_value }}</p>
          </div>
          <div class="bg-white/5 rounded-xl p-4">
            <p class="text-[10px] uppercase tracking-widest text-white/40">Max Discount</p>
            <p class="text-lg font-bold text-white mt-1">{{ c.max_discount ? '₹' + c.max_discount : 'No cap' }}</p>
          </div>
          <div class="bg-white/5 rounded-xl p-4">
            <p class="text-[10px] uppercase tracking-widest text-white/40">Min Order</p>
            <p class="text-lg font-bold text-white mt-1">{{ c.min_order_value ? '₹' + c.min_order_value : 'None' }}</p>
          </div>
          <div class="bg-white/5 rounded-xl p-4">
            <p class="text-[10px] uppercase tracking-widest text-white/40">Share (Platform/Rest.)</p>
            <p class="text-lg font-bold text-white mt-1">{{ c.share_platform || 0 }}% / {{ c.share_restaurant || 0 }}%</p>
          </div>
        </div>

        <div class="flex gap-2 mt-4">
          <button @click="editCampaign(c)" class="px-4 py-2 rounded-xl bg-white/5 text-white/70 text-sm hover:bg-white/10">Edit</button>
        </div>
      </div>
    </div>

    <!-- Campaign Form Modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black/60 backdrop-blur-md z-50 flex items-center justify-center p-6" @click.self="showForm = false">
      <div class="bg-surface rounded-3xl p-8 w-full max-w-lg border border-white/5">
        <h2 class="text-2xl font-bold text-white mb-6">{{ editingId ? 'Edit Campaign' : 'New Campaign' }}</h2>

        <div v-if="formError" class="mb-4 p-3 rounded-xl bg-red-500/10 text-red-400 text-sm">{{ formError }}</div>

        <div class="space-y-4">
          <div>
            <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Campaign Name</label>
            <input v-model="form.name" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white focus:outline-none focus:border-primary/50" placeholder="e.g., Diwali Sale"/>
          </div>
          <div>
            <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Description</label>
            <textarea v-model="form.description" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white focus:outline-none focus:border-primary/50" rows="2"></textarea>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Discount Type</label>
              <select v-model="form.discount_type" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white">
                <option value="flat">Flat Amount</option>
                <option value="percentage">Percentage</option>
              </select>
            </div>
            <div>
              <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Discount Value</label>
              <input v-model="form.discount_value" type="number" step="0.01" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white"/>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Max Discount</label>
              <input v-model="form.max_discount" type="number" step="0.01" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white" placeholder="No cap"/>
            </div>
            <div>
              <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Min Order</label>
              <input v-model="form.min_order_value" type="number" step="0.01" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white" placeholder="0"/>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Platform Share (%)</label>
              <input v-model="form.share_platform" type="number" min="0" max="100" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white"/>
            </div>
            <div>
              <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Restaurant Share (%)</label>
              <input v-model="form.share_restaurant" type="number" min="0" max="100" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white"/>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Start Date</label>
              <input v-model="form.start_date" type="date" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white"/>
            </div>
            <div>
              <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">End Date</label>
              <input v-model="form.end_date" type="date" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white"/>
            </div>
          </div>
        </div>

        <div class="flex gap-4 mt-8">
          <button @click="showForm = false" class="flex-1 py-3 rounded-xl bg-white/5 text-white/70 hover:bg-white/10">Cancel</button>
          <button @click="saveCampaign" :disabled="saving" class="flex-1 py-3 rounded-xl sovereign-gradient text-white font-bold disabled:opacity-50">
            {{ saving ? 'Saving...' : (editingId ? 'Update' : 'Create') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { adminGetCampaigns, adminCreateCampaign, adminUpdateCampaign } from '../api'

const campaigns = ref([])
const loading = ref(true)
const showForm = ref(false)
const saving = ref(false)
const editingId = ref(null)
const formError = ref('')

const form = reactive({
  name: '', description: '', discount_type: 'flat', discount_value: '', max_discount: '',
  min_order_value: '0', share_platform: '50', share_restaurant: '50', start_date: '', end_date: ''
})

const fetchCampaigns = async () => {
  try { campaigns.value = await adminGetCampaigns() } catch (e) { console.error(e) }
  finally { loading.value = false }
}

const editCampaign = (c) => {
  editingId.value = c.id
  form.name = c.name
  form.description = c.description || ''
  form.discount_type = c.discount_type
  form.discount_value = c.discount_value
  form.max_discount = c.max_discount || ''
  form.min_order_value = c.min_order_value || '0'
  form.share_platform = c.share_platform || '50'
  form.share_restaurant = c.share_restaurant || '50'
  form.start_date = c.start_date ? c.start_date.slice(0, 10) : ''
  form.end_date = c.end_date ? c.end_date.slice(0, 10) : ''
  showForm.value = true
}

const saveCampaign = async () => {
  formError.value = ''
  const platform = parseFloat(form.share_platform) || 0
  const restaurant = parseFloat(form.share_restaurant) || 0
  if (Math.round(platform + restaurant) !== 100) {
    formError.value = 'Platform and restaurant shares must sum to 100%.'
    return
  }
  saving.value = true
  try {
    const data = { ...form }
    if (!data.end_date) delete data.end_date
    if (!data.max_discount) delete data.max_discount
    if (editingId.value) {
      await adminUpdateCampaign(editingId.value, data)
    } else {
      await adminCreateCampaign(data)
    }
    showForm.value = false
    form.name = ''; form.description = ''; form.discount_value = ''
    form.max_discount = ''; form.min_order_value = '0'
    form.share_platform = '50'; form.share_restaurant = '50'
    form.start_date = ''; form.end_date = ''; editingId.value = null
    await fetchCampaigns()
  } catch (e) {
    formError.value = e.errors ? Object.values(e.errors).flat().join(' ') : (e.message || 'Failed to save')
  } finally { saving.value = false }
}

onMounted(fetchCampaigns)
</script>
