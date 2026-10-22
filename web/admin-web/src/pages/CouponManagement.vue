<template>
  <div>
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-serif font-bold text-on-surface">Coupon Management</h1>
        <p class="text-on-surface-variant mt-1">Create, edit and manage discount coupons for customers.</p>
      </div>
      <button @click="openForm()" class="flex items-center gap-2 bg-primary text-white font-bold px-5 py-2.5 rounded-lg hover:bg-primary/90 transition-colors text-sm shadow-sm">
        <span class="material-symbols-outlined text-[18px]">add</span> New Coupon
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="py-16 text-center text-on-surface-variant">
      <span class="material-symbols-outlined text-4xl animate-spin mb-4 block">progress_activity</span>
      <p>Loading coupons...</p>
    </div>

    <!-- Empty -->
    <div v-else-if="coupons.length === 0" class="bg-surface-container-lowest p-12 text-center rounded-xl border border-outline-variant/30 shadow-sm">
      <span class="material-symbols-outlined text-5xl text-primary opacity-50 mb-4 block">confirmation_number</span>
      <h3 class="text-xl font-bold text-on-surface mb-2">No coupons yet</h3>
      <p class="text-on-surface-variant mb-4">Create your first coupon to attract more customers.</p>
      <button @click="openForm()" class="bg-primary text-white font-bold px-6 py-2 rounded-lg text-sm">Create Coupon</button>
    </div>

    <!-- Coupons Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
      <div v-for="coupon in coupons" :key="coupon.id"
        class="bg-surface-container-lowest rounded-xl border border-outline-variant/30 shadow-sm overflow-hidden hover:shadow-md transition-shadow">
        <!-- Header -->
        <div class="p-5 border-b border-outline-variant/20">
          <div class="flex items-center justify-between mb-2">
            <span class="font-mono font-black text-lg text-primary bg-primary/10 px-3 py-1 rounded-lg border border-primary/20">{{ coupon.code }}</span>
            <span :class="coupon.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'" class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase">
              {{ coupon.is_active ? 'Active' : 'Inactive' }}
            </span>
          </div>
          <h3 class="font-bold text-on-surface">{{ coupon.title }}</h3>
          <p class="text-xs text-on-surface-variant mt-1 line-clamp-2">{{ coupon.description || 'No description' }}</p>
        </div>
        <!-- Details -->
        <div class="p-5 space-y-2 text-sm">
          <div class="flex justify-between">
            <span class="text-on-surface-variant">Discount</span>
            <span class="font-bold">{{ coupon.type === 'percentage' ? coupon.value + '%' : '₹' + coupon.value }} {{ coupon.type === 'percentage' ? 'off' : 'flat' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-on-surface-variant">Min Order</span>
            <span class="font-bold">₹{{ coupon.min_order || 0 }}</span>
          </div>
          <div v-if="coupon.max_discount" class="flex justify-between">
            <span class="text-on-surface-variant">Max Discount</span>
            <span class="font-bold">₹{{ coupon.max_discount }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-on-surface-variant">Usage</span>
            <span class="font-bold">{{ coupon.times_used || 0 }} / {{ coupon.usage_limit || '∞' }}</span>
          </div>
          <div v-if="coupon.valid_until" class="flex justify-between">
            <span class="text-on-surface-variant">Expires</span>
            <span class="font-bold" :class="new Date(coupon.valid_until) < new Date() ? 'text-red-500' : ''">
              {{ new Date(coupon.valid_until).toLocaleDateString('en-IN', { day: '2-digit', month: 'short' }) }}
            </span>
          </div>
        </div>
        <!-- Actions -->
        <div class="px-5 py-3 bg-surface-container-low/50 border-t border-outline-variant/20 flex gap-2">
          <button @click="openForm(coupon)" class="flex-1 text-xs font-bold text-primary bg-primary/10 py-2 rounded-lg hover:bg-primary/20 transition-colors">Edit</button>
          <button @click="toggleActive(coupon)" class="flex-1 text-xs font-bold py-2 rounded-lg transition-colors"
            :class="coupon.is_active ? 'text-amber-600 bg-amber-50 hover:bg-amber-100' : 'text-green-600 bg-green-50 hover:bg-green-100'">
            {{ coupon.is_active ? 'Deactivate' : 'Activate' }}
          </button>
          <button @click="handleDelete(coupon.id)" class="text-xs font-bold text-red-500 bg-red-50 px-3 py-2 rounded-lg hover:bg-red-100 transition-colors">
            <span class="material-symbols-outlined text-[16px]">delete</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Form Modal -->
    <div v-if="showForm" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" @click.self="showForm = false">
      <div class="bg-surface-container-lowest w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden">
        <div class="p-6 border-b border-outline-variant/30 flex justify-between items-center">
          <h2 class="font-serif text-xl font-bold">{{ editingId ? 'Edit Coupon' : 'Create Coupon' }}</h2>
          <button @click="showForm = false" class="material-symbols-outlined text-on-surface-variant hover:text-error">close</button>
        </div>
        <form @submit.prevent="handleSubmit" class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-on-surface-variant mb-1">Code *</label>
              <input v-model="form.code" required class="w-full px-3 py-2 rounded-lg bg-surface-container border border-outline-variant/30 text-sm font-mono uppercase focus:outline-none focus:ring-2 focus:ring-primary/30" />
            </div>
            <div>
              <label class="block text-xs font-bold text-on-surface-variant mb-1">Type *</label>
              <select v-model="form.type" class="w-full px-3 py-2 rounded-lg bg-surface-container border border-outline-variant/30 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30">
                <option value="percentage">Percentage</option>
                <option value="flat">Flat Amount</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-xs font-bold text-on-surface-variant mb-1">Title *</label>
            <input v-model="form.title" required class="w-full px-3 py-2 rounded-lg bg-surface-container border border-outline-variant/30 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30" />
          </div>
          <div>
            <label class="block text-xs font-bold text-on-surface-variant mb-1">Description</label>
            <input v-model="form.description" class="w-full px-3 py-2 rounded-lg bg-surface-container border border-outline-variant/30 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30" />
          </div>
          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-bold text-on-surface-variant mb-1">Value *</label>
              <input v-model.number="form.value" type="number" step="0.01" required class="w-full px-3 py-2 rounded-lg bg-surface-container border border-outline-variant/30 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30" />
            </div>
            <div>
              <label class="block text-xs font-bold text-on-surface-variant mb-1">Min Order</label>
              <input v-model.number="form.min_order" type="number" step="1" class="w-full px-3 py-2 rounded-lg bg-surface-container border border-outline-variant/30 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30" />
            </div>
            <div>
              <label class="block text-xs font-bold text-on-surface-variant mb-1">Max Disc.</label>
              <input v-model.number="form.max_discount" type="number" step="1" class="w-full px-3 py-2 rounded-lg bg-surface-container border border-outline-variant/30 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-on-surface-variant mb-1">Usage Limit</label>
              <input v-model.number="form.usage_limit" type="number" class="w-full px-3 py-2 rounded-lg bg-surface-container border border-outline-variant/30 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30" />
            </div>
            <div>
              <label class="block text-xs font-bold text-on-surface-variant mb-1">Per User Limit</label>
              <input v-model.number="form.per_user_limit" type="number" class="w-full px-3 py-2 rounded-lg bg-surface-container border border-outline-variant/30 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-on-surface-variant mb-1">Valid From</label>
              <input v-model="form.valid_from" type="date" class="w-full px-3 py-2 rounded-lg bg-surface-container border border-outline-variant/30 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30" />
            </div>
            <div>
              <label class="block text-xs font-bold text-on-surface-variant mb-1">Valid Until</label>
              <input v-model="form.valid_until" type="date" class="w-full px-3 py-2 rounded-lg bg-surface-container border border-outline-variant/30 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30" />
            </div>
          </div>

          <div v-if="formError" class="p-3 bg-red-50 text-red-600 rounded-lg text-xs font-bold">{{ formError }}</div>

          <button type="submit" :disabled="saving"
            class="w-full bg-primary text-white font-bold py-3 rounded-xl flex items-center justify-center gap-2 hover:bg-primary/90 transition-colors disabled:opacity-50">
            <span v-if="saving" class="material-symbols-outlined animate-spin text-[18px]">sync</span>
            {{ editingId ? 'Update Coupon' : 'Create Coupon' }}
          </button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { getCoupons, createCoupon, updateCoupon, deleteCoupon } from '../api'

const coupons = ref([])
const loading = ref(true)
const showForm = ref(false)
const saving = ref(false)
const editingId = ref(null)
const formError = ref('')

const emptyForm = { code: '', title: '', description: '', type: 'percentage', value: 10, min_order: 0, max_discount: null, usage_limit: null, per_user_limit: 1, valid_from: '', valid_until: '' }
const form = reactive({ ...emptyForm })

const fetchCoupons = async () => {
  loading.value = true
  try { coupons.value = await getCoupons() } catch (e) { console.error(e) }
  finally { loading.value = false }
}

const openForm = (coupon = null) => {
  formError.value = ''
  if (coupon) {
    editingId.value = coupon.id
    Object.assign(form, {
      code: coupon.code, title: coupon.title, description: coupon.description || '',
      type: coupon.type, value: coupon.value, min_order: coupon.min_order || 0,
      max_discount: coupon.max_discount, usage_limit: coupon.usage_limit,
      per_user_limit: coupon.per_user_limit || 1,
      valid_from: coupon.valid_from?.split('T')[0] || '',
      valid_until: coupon.valid_until?.split('T')[0] || '',
    })
  } else {
    editingId.value = null
    Object.assign(form, emptyForm)
  }
  showForm.value = true
}

const handleSubmit = async () => {
  saving.value = true; formError.value = ''
  try {
    const payload = { ...form }
    if (!payload.valid_from) delete payload.valid_from
    if (!payload.valid_until) delete payload.valid_until
    if (!payload.max_discount) delete payload.max_discount
    if (!payload.usage_limit) delete payload.usage_limit

    if (editingId.value) {
      await updateCoupon(editingId.value, payload)
    } else {
      await createCoupon(payload)
    }
    showForm.value = false
    await fetchCoupons()
  } catch (e) {
    formError.value = e.message || Object.values(e.errors || {}).flat().join(' ') || 'Failed to save'
  } finally { saving.value = false }
}

const toggleActive = async (coupon) => {
  try {
    await updateCoupon(coupon.id, { is_active: !coupon.is_active })
    await fetchCoupons()
  } catch (e) { console.error(e) }
}

const handleDelete = async (id) => {
  if (!confirm('Delete this coupon permanently?')) return
  try { await deleteCoupon(id); await fetchCoupons() } catch (e) { console.error(e) }
}

onMounted(fetchCoupons)
</script>
