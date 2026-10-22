<template>
  <div class="min-h-screen bg-background flex items-center justify-center p-8">
    <div class="w-full max-w-md">
      <div class="text-center mb-12">
        <div class="mb-2">
          <img src="/logo-large.png" alt="UtterLazy" class="h-16 mx-auto object-contain" />
        </div>
        <span class="text-[11px] font-bold uppercase tracking-[0.2em] text-primary">Command Center Login</span>
      </div>

      <form @submit.prevent="handleLogin" class="bg-surface rounded-3xl p-10 border border-white/5">
        <div v-if="error" class="mb-6 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm">
          {{ error }}
        </div>

        <div class="mb-6">
          <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">Email</label>
          <input
            v-model="email"
            type="email"
            required
            class="w-full px-5 py-4 rounded-xl bg-surface-variant border border-white/5 text-white text-sm focus:outline-none focus:border-primary transition-colors"
            placeholder="admin@utterlazy.com"
          />
        </div>

        <div class="mb-8">
          <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-3">Password</label>
          <input
            v-model="password"
            type="password"
            required
            class="w-full px-5 py-4 rounded-xl bg-surface-variant border border-white/5 text-white text-sm focus:outline-none focus:border-primary transition-colors"
            placeholder="••••••••"
          />
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="w-full py-4 rounded-xl sovereign-gradient text-on-primary font-display font-bold uppercase tracking-widest text-sm disabled:opacity-50 transition-opacity"
        >
          {{ loading ? 'AUTHENTICATING...' : 'ACCESS VAULT' }}
        </button>
      </form>

      <p class="text-center mt-6 text-[10px] text-on-surface-variant/50 uppercase tracking-widest">
        Authorized Personnel Only
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { adminLogin } from '../api'

const router = useRouter()
const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

const handleLogin = async () => {
  loading.value = true
  error.value = ''

  try {
    const data = await adminLogin(email.value, password.value)
    localStorage.setItem('admin_user', JSON.stringify(data.user))
    router.push('/')
  } catch (e) {
    error.value = e.message || e.error || 'Login failed'
  } finally {
    loading.value = false
  }
}
</script>
