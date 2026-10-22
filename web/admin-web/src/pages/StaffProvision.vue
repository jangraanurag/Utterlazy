<template>
  <div>
    <h1 class="text-3xl font-serif font-bold text-on-surface mb-2">Staff Provision</h1>
    <p class="text-on-surface-variant mb-8">
      Super admin only. Deactivation revokes tokens and keeps audit history; it never deletes a user.
    </p>

    <form @submit.prevent="create" class="bg-surface-container-lowest rounded-xl border border-outline-variant/30 p-6 mb-8 grid grid-cols-1 md:grid-cols-5 gap-4">
      <div>
        <label class="block text-xs uppercase tracking-wider text-on-surface-variant mb-1">Name</label>
        <input v-model="form.name" required minlength="2" maxlength="120"
          class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-3 py-2 text-sm" />
      </div>
      <div>
        <label class="block text-xs uppercase tracking-wider text-on-surface-variant mb-1">Email</label>
        <input type="email" v-model="form.email" required
          class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-3 py-2 text-sm" />
      </div>
      <div>
        <label class="block text-xs uppercase tracking-wider text-on-surface-variant mb-1">Phone (+91)</label>
        <input v-model="form.phone" required pattern="^\+91[6-9][0-9]{9}$" placeholder="+919876543210"
          class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-3 py-2 text-sm" />
      </div>
      <div>
        <label class="block text-xs uppercase tracking-wider text-on-surface-variant mb-1">Role</label>
        <select v-model="form.admin_role" required
          class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-3 py-2 text-sm">
          <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
        </select>
      </div>
      <div class="flex items-end">
        <button class="w-full px-4 py-2 rounded-lg bg-primary text-white text-sm font-bold">Create staff</button>
      </div>
    </form>

    <p v-if="message" class="mb-4 text-sm text-green-400">{{ message }}</p>
    <p v-if="error" class="mb-4 text-sm text-red-400">{{ error }}</p>

    <div class="flex items-center gap-3 mb-4">
      <input v-model="q" @keyup.enter="load" placeholder="Search name / email / phone"
        class="bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2 text-sm w-72" />
      <button @click="load" class="px-4 py-2 rounded-lg bg-surface-container border border-outline-variant/50 text-sm font-bold">Search</button>
    </div>

    <div v-if="loading" class="py-12 text-center text-on-surface-variant">Loading...</div>

    <div v-else class="bg-surface-container-lowest rounded-xl border border-outline-variant/30 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-surface-container text-on-surface-variant text-xs uppercase tracking-wider">
          <tr>
            <th class="text-left px-4 py-3">Name</th>
            <th class="text-left px-4 py-3">Email</th>
            <th class="text-left px-4 py-3">Role</th>
            <th class="text-right px-4 py-3">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in staff" :key="s.id" class="border-t border-outline-variant/20">
            <td class="px-4 py-3 font-bold">{{ s.name }}</td>
            <td class="px-4 py-3 text-xs">{{ s.email }}</td>
            <td class="px-4 py-3 text-xs uppercase tracking-wider">{{ s.admin_role }}</td>
            <td class="px-4 py-3 text-right">
              <button @click="deactivate(s)" class="px-3 py-1 rounded bg-red-500/20 text-red-300 text-xs font-bold">Deactivate</button>
            </td>
          </tr>
          <tr v-if="!staff.length">
            <td colspan="4" class="px-4 py-10 text-center text-on-surface-variant">No staff found.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { getStaff, createStaff, deactivateStaff } from '../api'

const roles = [
  'super_admin', 'city_manager', 'support_agent', 'finance_manager', 'account_manager',
  'support_l1', 'support_l2', 'support_lead', 'city_ops', 'rider_ops', 'catalog_ops',
  'onboarding_agent', 'ads_ops', 'whatsapp_ops', 'fraud_analyst', 'growth', 'auditor',
]

const staff = ref([])
const loading = ref(true)
const message = ref('')
const error = ref('')
const q = ref('')
const form = reactive({ name: '', email: '', phone: '', admin_role: 'support_l1' })

async function load() {
  loading.value = true
  try {
    const res = await getStaff({ q: q.value })
    staff.value = res.data || []
  } catch (e) {
    error.value = e.message || 'Failed to load staff'
  } finally {
    loading.value = false
  }
}

async function create() {
  message.value = ''
  error.value = ''
  try {
    await createStaff({ ...form })
    message.value = 'Staff created. Send a password reset from the login flow.'
    form.name = ''
    form.email = ''
    form.phone = ''
    await load()
  } catch (e) {
    error.value = e.error || e.message || 'Create failed'
  }
}

async function deactivate(s) {
  if (!confirm(`Deactivate ${s.name}? Their sessions will be revoked.`)) return
  message.value = ''
  error.value = ''
  try {
    await deactivateStaff(s.id)
    message.value = `${s.name} deactivated.`
    await load()
  } catch (e) {
    error.value = e.error || e.message || 'Deactivate failed'
  }
}

onMounted(load)
</script>
