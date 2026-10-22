<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { vendorLogin } from '../api'
import { useAuth } from '../auth'

const router = useRouter()
const { loadProfile } = useAuth()

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function handleLogin() {
  error.value = ''
  loading.value = true
  try {
    const data = await vendorLogin(email.value, password.value)
    localStorage.setItem('vendor_token', data.token)
    await loadProfile()
    router.push('/')
  } catch (err) {
    error.value = err.response?.data?.error || 'Login failed'
  }
  loading.value = false
}
</script>

<template>
  <div class="login-screen">
    <div class="login-card">
      <div class="login-header">
        <img src="/logo-large.png" alt="UtterLazy" class="h-16 mx-auto mb-2 object-contain" />
        <p class="login-subtitle">Vendor Command Center</p>
      </div>

      <div v-if="error" class="alert-error">{{ error }}</div>

      <form @submit.prevent="handleLogin">
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" class="form-input" placeholder="vendor@utterlazy.in" v-model="email" required />
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" class="form-input" placeholder="••••••••" v-model="password" required />
        </div>
        <button type="submit" class="btn btn-primary w-full" :disabled="loading">
          {{ loading ? 'Signing in...' : 'Sign In to Dashboard' }}
        </button>
      </form>

      <p class="login-footer">Need access? Contact UtterLazy Admin</p>
    </div>
  </div>
</template>
