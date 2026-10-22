<template>
  <div class="bg-background text-on-surface min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-md">
      <!-- Logo -->
      <div class="text-center mb-10">
        <img src="/logo-large.png" alt="UtterLazy" class="h-20 mx-auto mb-3 object-contain" />
        <p class="text-primary text-xs font-bold uppercase tracking-[0.3em] mb-4">Too Lazy? Just Order.</p>
        <p class="text-on-surface-variant font-medium">Join the culinary revolution.</p>
      </div>

      <!-- Register Card -->
      <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 p-8 shadow-lg">
        <h2 class="font-serif text-2xl font-bold text-on-surface mb-6">Create Account</h2>

        <div v-if="errorMessage" class="mb-4 p-3 rounded-xl bg-error-container text-on-error-container text-sm font-medium flex items-center gap-2">
          <span class="material-symbols-outlined text-[18px]">error</span>
          {{ errorMessage }}
        </div>

        <div v-if="successMessage" class="mb-4 p-3 rounded-xl bg-secondary-container text-on-secondary-container text-sm font-medium flex items-center gap-2">
          <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">stars</span>
          {{ successMessage }}
        </div>

        <form @submit.prevent="handleRegister" class="space-y-4">
          <div>
            <label class="block text-sm font-bold text-on-surface-variant mb-2">Full Name</label>
            <input v-model="form.name" type="text" required placeholder="Your name"
              class="w-full px-4 py-3 rounded-xl bg-surface-container border border-outline-variant/30 text-on-surface font-medium focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" />
          </div>
          <div>
            <label class="block text-sm font-bold text-on-surface-variant mb-2">Email</label>
            <input v-model="form.email" type="email" required placeholder="you@example.com"
              class="w-full px-4 py-3 rounded-xl bg-surface-container border border-outline-variant/30 text-on-surface font-medium focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" />
          </div>
          <div>
            <label class="block text-sm font-bold text-on-surface-variant mb-2">Phone Number</label>
            <input v-model="form.phone" type="tel" placeholder="+91 98765 43210"
              class="w-full px-4 py-3 rounded-xl bg-surface-container border border-outline-variant/30 text-on-surface font-medium focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" />
          </div>
          <div>
            <label class="block text-sm font-bold text-on-surface-variant mb-2">Password</label>
            <input v-model="form.password" type="password" required placeholder="Min 6 characters"
              class="w-full px-4 py-3 rounded-xl bg-surface-container border border-outline-variant/30 text-on-surface font-medium focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" />
          </div>
          <div>
            <label class="block text-sm font-bold text-on-surface-variant mb-2">Confirm Password</label>
            <input v-model="form.password_confirmation" type="password" required placeholder="Repeat password"
              class="w-full px-4 py-3 rounded-xl bg-surface-container border border-outline-variant/30 text-on-surface font-medium focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" />
          </div>
          <button type="submit" :disabled="isLoading"
            class="w-full primary-gradient text-on-primary font-bold py-4 rounded-xl shadow-lg shadow-primary/20 flex items-center justify-center gap-2 transition-all active:scale-[0.98] disabled:opacity-70">
            <span v-if="isLoading" class="material-symbols-outlined animate-spin text-[20px]">progress_activity</span>
            <span v-else>Create Account & Earn 100 Coins 🪙</span>
          </button>
        </form>

        <div class="mt-6 text-center">
          <p class="text-on-surface-variant text-sm">
            Already have an account?
            <router-link to="/login" class="text-primary font-bold hover:underline">Sign In</router-link>
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { register } from '../api'

const router = useRouter()
const isLoading = ref(false)
const errorMessage = ref('')
const successMessage = ref('')

const form = reactive({
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
})

const handleRegister = async () => {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const res = await register(form)
    successMessage.value = res.message
    setTimeout(() => router.push('/'), 1500)
  } catch (e) {
    const errors = e.errors
    if (errors) {
      errorMessage.value = Object.values(errors).flat().join(' ')
    } else {
      errorMessage.value = e.message || 'Registration failed. Please try again.'
    }
  } finally {
    isLoading.value = false
  }
}
</script>
