<template>
  <div class="bg-background text-on-surface min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-md">
      <div class="text-center mb-10">
        <img src="/logo-large.png" alt="UtterLazy" class="h-16 mx-auto mb-2 object-contain" />
        <p class="text-on-surface-variant font-medium" v-if="step === 1">Enter your email to receive an OTP</p>
        <p class="text-on-surface-variant font-medium" v-else-if="step === 2">Enter the 6-digit OTP sent to your email</p>
        <p class="text-on-surface-variant font-medium" v-else>Choose a new password</p>
      </div>

      <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 p-8 shadow-lg">
        <div v-if="error" class="mb-4 p-3 rounded-xl bg-error-container text-on-error-container text-sm font-medium flex items-center gap-2">
          <span class="material-symbols-outlined text-[18px]">error</span> {{ error }}
        </div>

        <!-- Step 1: Email -->
        <div v-if="step === 1">
          <form @submit.prevent="requestOtp">
            <div class="mb-6">
              <label class="block text-sm font-bold text-on-surface-variant mb-2">Email</label>
              <input v-model="email" type="email" required placeholder="anurag@welo.com"
                class="w-full px-4 py-3 rounded-xl bg-surface-container border border-outline-variant/30 text-on-surface font-medium focus:outline-none focus:ring-2 focus:ring-primary/30" />
            </div>
            <button type="submit" :disabled="loading"
              class="w-full primary-gradient text-on-primary font-bold py-4 rounded-xl shadow-lg shadow-primary/20 disabled:opacity-70">
              {{ loading ? 'Sending...' : 'Send OTP' }}
            </button>
          </form>
        </div>

        <!-- Step 2: OTP -->
        <div v-else-if="step === 2">
          <form @submit.prevent="verifyOtp">
            <div class="mb-6">
              <label class="block text-sm font-bold text-on-surface-variant mb-2">OTP</label>
              <input v-model="otp" type="text" maxlength="6" required placeholder="123456"
                class="w-full px-4 py-3 rounded-xl bg-surface-container border border-outline-variant/30 text-on-surface font-medium text-center text-2xl tracking-[8px] focus:outline-none focus:ring-2 focus:ring-primary/30" />
            </div>
            <button type="submit" :disabled="loading"
              class="w-full primary-gradient text-on-primary font-bold py-4 rounded-xl shadow-lg shadow-primary/20 disabled:opacity-70">
              {{ loading ? 'Verifying...' : 'Verify OTP' }}
            </button>
          </form>
          <div class="mt-4 text-center">
            <button @click="requestOtp" class="text-primary font-bold text-sm hover:underline">Resend OTP</button>
          </div>
        </div>

        <!-- Step 3: New Password -->
        <div v-else>
          <form @submit.prevent="submitResetPassword">
            <div class="mb-4">
              <label class="block text-sm font-bold text-on-surface-variant mb-2">New Password</label>
              <input v-model="newPassword" type="password" required placeholder="Min 6 characters"
                class="w-full px-4 py-3 rounded-xl bg-surface-container border border-outline-variant/30 text-on-surface font-medium focus:outline-none focus:ring-2 focus:ring-primary/30" />
            </div>
            <div class="mb-6">
              <label class="block text-sm font-bold text-on-surface-variant mb-2">Confirm Password</label>
              <input v-model="confirmPassword" type="password" required placeholder="Confirm new password"
                class="w-full px-4 py-3 rounded-xl bg-surface-container border border-outline-variant/30 text-on-surface font-medium focus:outline-none focus:ring-2 focus:ring-primary/30" />
            </div>
            <button type="submit" :disabled="loading"
              class="w-full primary-gradient text-on-primary font-bold py-4 rounded-xl shadow-lg shadow-primary/20 disabled:opacity-70">
              {{ loading ? 'Resetting...' : 'Reset Password' }}
            </button>
          </form>
        </div>

        <div class="mt-6 text-center">
          <router-link to="/login" class="text-primary font-bold text-sm hover:underline">Back to Login</router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { requestPasswordResetOtp, verifyPasswordResetOtp, resetPassword } from '../api'

const router = useRouter()
const step = ref(1)
const email = ref('')
const otp = ref('')
const newPassword = ref('')
const confirmPassword = ref('')
const resetToken = ref('')
const loading = ref(false)
const error = ref('')

const requestOtp = async () => {
  loading.value = true; error.value = ''
  try {
    await requestPasswordResetOtp(email.value)
    step.value = 2
  } catch (e) {
    error.value = e.message || e.error || 'Failed to send OTP'
  } finally { loading.value = false }
}

const verifyOtp = async () => {
  loading.value = true; error.value = ''
  try {
    const res = await verifyPasswordResetOtp(email.value, otp.value)
    resetToken.value = res.reset_token
    step.value = 3
  } catch (e) {
    error.value = e.error || 'Invalid or expired OTP'
  } finally { loading.value = false }
}

const submitResetPassword = async () => {
  if (newPassword.value !== confirmPassword.value) {
    error.value = 'Passwords do not match'
    return
  }
  if (newPassword.value.length < 6) {
    error.value = 'Password must be at least 6 characters'
    return
  }
  loading.value = true; error.value = ''
  try {
    await resetPassword(email.value, resetToken.value, newPassword.value)
    router.push('/login')
  } catch (e) {
    error.value = e.error || e.message || 'Failed to reset password'
  } finally { loading.value = false }
}
</script>
