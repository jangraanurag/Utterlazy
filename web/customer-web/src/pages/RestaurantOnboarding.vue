<template>
  <div class="min-h-screen bg-background flex items-center justify-center p-8">
    <div class="w-full max-w-2xl">
      <div class="text-center mb-12">
        <div class="mb-2">
          <img src="/logo-large.png" alt="UtterLazy" class="h-18 mx-auto object-contain" />
        </div>
        <span class="text-[11px] font-bold uppercase tracking-[0.2em] text-primary">Partner With Us</span>
        <p class="text-on-surface-variant mt-4">Join 10,000+ restaurants on UtterLazy</p>
      </div>

      <form @submit.prevent="handleSubmit" class="bg-surface rounded-3xl p-10 border border-outline-variant/30">
        <div v-if="success" class="mb-6 p-4 rounded-xl bg-green-50 border border-green-200 text-green-700 text-sm">
          {{ success }}
        </div>

        <div v-if="error" class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-700 text-sm">
          {{ error }}
        </div>

        <div class="grid grid-cols-2 gap-6 mb-6">
          <div>
            <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">Restaurant Name</label>
            <input v-model="form.restaurant_name" required class="w-full px-5 py-4 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-on-surface text-sm focus:outline-none focus:border-primary transition-colors" />
          </div>
          <div>
            <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">Owner Name</label>
            <input v-model="form.owner_name" required class="w-full px-5 py-4 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-on-surface text-sm focus:outline-none focus:border-primary transition-colors" />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-6 mb-6">
          <div>
            <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">Email</label>
            <input v-model="form.email" type="email" required class="w-full px-5 py-4 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-on-surface text-sm focus:outline-none focus:border-primary transition-colors" />
          </div>
          <div>
            <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">Phone</label>
            <input v-model="form.phone" required class="w-full px-5 py-4 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-on-surface text-sm focus:outline-none focus:border-primary transition-colors" />
          </div>
        </div>

        <div class="mb-6">
          <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">Address</label>
          <input v-model="form.address" required class="w-full px-5 py-4 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-on-surface text-sm focus:outline-none focus:border-primary transition-colors" />
        </div>

        <div class="grid grid-cols-3 gap-6 mb-6">
          <div>
            <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">City</label>
            <input v-model="form.city" required class="w-full px-5 py-4 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-on-surface text-sm focus:outline-none focus:border-primary transition-colors" />
          </div>
          <div>
            <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">State</label>
            <input v-model="form.state" required class="w-full px-5 py-4 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-on-surface text-sm focus:outline-none focus:border-primary transition-colors" />
          </div>
          <div>
            <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">Pincode</label>
            <input v-model="form.pincode" required class="w-full px-5 py-4 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-on-surface text-sm focus:outline-none focus:border-primary transition-colors" />
          </div>
        </div>

        <div class="grid grid-cols-3 gap-6 mb-8">
          <div>
            <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">FSSAI Number</label>
            <input v-model="form.fssai_number" class="w-full px-5 py-4 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-on-surface text-sm focus:outline-none focus:border-primary transition-colors" />
          </div>
          <div>
            <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">GST Number</label>
            <input v-model="form.gst_number" class="w-full px-5 py-4 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-on-surface text-sm focus:outline-none focus:border-primary transition-colors" />
          </div>
          <div>
            <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">PAN Number</label>
            <input v-model="form.pan_number" class="w-full px-5 py-4 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-on-surface text-sm focus:outline-none focus:border-primary transition-colors" />
          </div>
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="w-full py-4 rounded-xl sovereign-gradient text-on-primary font-serif font-bold uppercase tracking-widest text-sm disabled:opacity-50 transition-opacity"
        >
          {{ loading ? 'SUBMITTING...' : 'SUBMIT APPLICATION' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { applyAsRestaurant } from '../api'

const form = reactive({
  restaurant_name: '', owner_name: '', email: '', phone: '',
  address: '', city: '', state: '', pincode: '',
  fssai_number: '', gst_number: '', pan_number: '',
})

const loading = ref(false)
const error = ref('')
const success = ref('')

const handleSubmit = async () => {
  loading.value = true
  error.value = ''
  success.value = ''

  try {
    const data = await applyAsRestaurant(form)
    success.value = data.message || 'Application submitted successfully!'
  } catch (e) {
    error.value = e.message || e.error || 'Submission failed'
  } finally {
    loading.value = false
  }
}
</script>
