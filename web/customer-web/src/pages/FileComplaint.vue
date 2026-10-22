<template>
  <div class="bg-background text-on-surface min-h-screen">
    <nav class="fixed top-0 left-0 w-full flex justify-between items-center px-8 py-4 bg-[#fdf9f4]/60 backdrop-blur-xl z-50">
      <div class="flex items-center gap-6">
        <router-link to="/" class="font-serif text-3xl italic font-bold text-primary">UtterLazy</router-link>
        <router-link to="/orders" class="flex items-center gap-1 text-on-surface-variant hover:text-primary transition-colors font-medium">
          <span class="material-symbols-outlined text-[18px]">arrow_back</span> My Orders
        </router-link>
      </div>
    </nav>

    <main class="pt-28 px-8 max-w-2xl mx-auto pb-16">
      <header class="mb-10">
        <h1 class="font-serif text-4xl font-bold text-on-surface mb-2">Report Issue</h1>
        <p class="text-on-surface-variant font-medium">We're sorry you had a problem. Please tell us what happened.</p>
      </header>

      <div v-if="submitted" class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 p-12 text-center">
        <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mx-auto mb-6">
          <span class="material-symbols-outlined text-green-600 text-3xl" style="font-variation-settings: 'FILL' 1;">check_circle</span>
        </div>
        <h2 class="font-serif text-2xl font-bold text-on-surface mb-2">Complaint Submitted!</h2>
        <p class="text-on-surface-variant mb-8">Your complaint has been sent to the restaurant. They will respond shortly.</p>
        <div class="flex gap-4 justify-center">
          <router-link to="/my-complaints" class="primary-gradient text-on-primary font-bold py-3 px-8 rounded-xl shadow-lg shadow-primary/20 inline-flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]">list_alt</span> View All Complaints
          </router-link>
          <router-link to="/orders" class="bg-surface-container-high text-on-surface font-bold py-3 px-8 rounded-xl inline-flex items-center gap-2 hover:bg-surface-container-highest transition-colors">
            <span class="material-symbols-outlined text-[18px]">receipt_long</span> My Orders
          </router-link>
        </div>
      </div>

      <div v-else class="space-y-8">
        <div v-if="error" class="p-4 rounded-xl bg-error-container text-on-error-container font-medium flex items-center gap-2">
          <span class="material-symbols-outlined text-[18px]">error</span> {{ error }}
        </div>

        <section class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 p-8">
          <div class="flex items-center gap-3 mb-6 p-4 bg-primary/5 rounded-xl border border-primary/10">
            <span class="material-symbols-outlined text-primary">receipt_long</span>
            <div>
              <p class="text-xs font-black text-primary uppercase tracking-widest">Order ID</p>
              <p class="font-bold text-on-surface font-mono">{{ orderId }}</p>
            </div>
          </div>

          <div class="space-y-6">
            <div>
              <label class="block text-sm font-bold text-on-surface-variant mb-2">Reason for complaint</label>
              <select v-model="reason" class="w-full px-4 py-3 rounded-xl bg-surface-container border border-outline-variant/30 text-on-surface font-medium focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all appearance-none">
                <option value="" disabled>Select a reason</option>
                <option v-for="r in reasons" :key="r" :value="r">{{ r }}</option>
              </select>
            </div>

            <div>
              <label class="block text-sm font-bold text-on-surface-variant mb-2">Description</label>
              <textarea v-model="description" rows="6" maxlength="1000" placeholder="Describe the issue in detail..." class="w-full px-4 py-3 rounded-xl bg-surface-container border border-outline-variant/30 text-on-surface font-medium focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all resize-none"></textarea>
              <p class="text-xs text-on-surface-variant mt-1 text-right">{{ description.length }}/1000</p>
            </div>

            <button @click="handleSubmit" :disabled="submitting || !reason || !description.trim()" class="w-full primary-gradient text-on-primary font-bold py-4 rounded-xl shadow-lg shadow-primary/20 flex items-center justify-center gap-2 transition-all active:scale-[0.99] disabled:opacity-50 disabled:cursor-not-allowed">
              <span class="material-symbols-outlined">report</span>
              {{ submitting ? 'Submitting...' : 'Submit Complaint' }}
            </button>
          </div>
        </section>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { submitComplaint } from '../api'

const route = useRoute()
const router = useRouter()
const orderId = route.params.orderId || ''

const reasons = ['Food Quality', 'Late Delivery', 'Wrong Order', 'Missing Items', 'Packaging Issue', 'Other']

const reason = ref('')
const description = ref('')
const submitting = ref(false)
const submitted = ref(false)
const error = ref('')

const handleSubmit = async () => {
  if (!reason.value || !description.value.trim()) return
  submitting.value = true
  error.value = ''
  try {
    await submitComplaint({
      order_id: orderId,
      reason: reason.value,
      description: description.value.trim(),
    })
    submitted.value = true
  } catch (e) {
    error.value = e.error || e.message || 'Failed to submit complaint.'
  } finally {
    submitting.value = false
  }
}
</script>
