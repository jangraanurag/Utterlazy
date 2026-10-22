<template>
  <div class="bg-background text-on-surface min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-md">
      <!-- Logo -->
      <div class="text-center mb-10">
        <img src="/logo-large.png" alt="UtterLazy" class="h-20 mx-auto mb-3 object-contain" />
        <p class="text-primary text-xs font-bold uppercase tracking-[0.3em] mb-4">Too Lazy? Just Order.</p>
        <p class="text-on-surface-variant font-medium">Welcome back, food lover.</p>
      </div>

      <!-- Login Card -->
      <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 p-8 shadow-lg">
        <h2 class="font-serif text-2xl font-bold text-on-surface mb-6">Sign In</h2>

        <div v-if="errorMessage" class="mb-4 p-3 rounded-xl bg-error-container text-on-error-container text-sm font-medium flex items-center gap-2">
          <span class="material-symbols-outlined text-[18px]">error</span>
          {{ errorMessage }}
        </div>

        <form @submit.prevent="handleLogin" class="space-y-5">
          <div>
            <label class="block text-sm font-bold text-on-surface-variant mb-2">Email</label>
            <input v-model="email" type="email" required placeholder="anurag@welo.com"
              class="w-full px-4 py-3 rounded-xl bg-surface-container border border-outline-variant/30 text-on-surface font-medium focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" />
          </div>
          <div>
            <label class="block text-sm font-bold text-on-surface-variant mb-2">Password</label>
            <input v-model="password" type="password" required placeholder="••••••••"
              class="w-full px-4 py-3 rounded-xl bg-surface-container border border-outline-variant/30 text-on-surface font-medium focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" />
          </div>
          <button type="submit" :disabled="isLoading"
            class="w-full primary-gradient text-on-primary font-bold py-4 rounded-xl shadow-lg shadow-primary/20 flex items-center justify-center gap-2 transition-all active:scale-[0.98] disabled:opacity-70">
            <span v-if="isLoading" class="material-symbols-outlined animate-spin text-[20px]">progress_activity</span>
            <span v-else>Sign In</span>
          </button>
        </form>

        <div class="mt-6 text-center">
          <p class="text-on-surface-variant text-sm">
            <router-link to="/forgot-password" class="text-primary font-bold hover:underline">Forgot Password?</router-link>
          </p>
          <p class="text-on-surface-variant text-sm mt-2">
            Don't have an account?
            <router-link to="/register" class="text-primary font-bold hover:underline">Create one</router-link>
          </p>
        </div>
      </div>

      <!-- Demo credentials hint -->
      <div class="mt-6 text-center bg-surface-container-low rounded-xl p-4 border border-outline-variant/20">
        <p class="text-xs text-on-surface-variant font-medium">
          <span class="font-bold text-primary">Demo:</span> anurag@welo.com / password123
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { login } from '../api'

const router = useRouter()
const email = ref('')
const password = ref('')
const isLoading = ref(false)
const errorMessage = ref('')

const handleLogin = async () => {
  isLoading.value = true
  errorMessage.value = ''
  try {
    await login(email.value, password.value)
    router.push('/')
  } catch (e) {
    errorMessage.value = e.message || e.errors?.email?.[0] || 'Invalid credentials. Please try again.'
  } finally {
    isLoading.value = false
  }
}
</script>
