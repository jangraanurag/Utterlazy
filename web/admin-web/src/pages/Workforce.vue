<template>
  <div>
    <h1 class="text-3xl font-serif font-bold text-on-surface mb-2">Workforce</h1>
    <p class="text-on-surface-variant mb-8">Agents, shifts, and queue caps. A ticket with no assignee is a defect, not a backlog.</p>

    <form @submit.prevent="save" class="bg-surface-container-lowest rounded-xl border border-outline-variant/30 p-6 mb-8 grid grid-cols-1 md:grid-cols-5 gap-4">
      <div>
        <label class="block text-xs uppercase tracking-wider text-on-surface-variant mb-1">Agent user ID</label>
        <input v-model="form.user_id" required placeholder="uuid"
          class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-3 py-2 text-sm" />
      </div>
      <div>
        <label class="block text-xs uppercase tracking-wider text-on-surface-variant mb-1">Shift start</label>
        <input type="time" v-model="form.shift_start" required
          class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-3 py-2 text-sm" />
      </div>
      <div>
        <label class="block text-xs uppercase tracking-wider text-on-surface-variant mb-1">Shift end</label>
        <input type="time" v-model="form.shift_end" required
          class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-3 py-2 text-sm" />
      </div>
      <div>
        <label class="block text-xs uppercase tracking-wider text-on-surface-variant mb-1">Queue cap (1–40)</label>
        <input type="number" min="1" max="40" v-model.number="form.queue_cap" required
          class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-3 py-2 text-sm" />
      </div>
      <div class="flex items-end">
        <button class="w-full px-4 py-2 rounded-lg bg-primary text-white text-sm font-bold">Save entry</button>
      </div>
    </form>

    <p v-if="message" class="mb-4 text-sm text-green-400">{{ message }}</p>
    <p v-if="error" class="mb-4 text-sm text-red-400">{{ error }}</p>

    <div v-if="loading" class="py-12 text-center text-on-surface-variant">Loading...</div>

    <div v-else class="bg-surface-container-lowest rounded-xl border border-outline-variant/30 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-surface-container text-on-surface-variant text-xs uppercase tracking-wider">
          <tr>
            <th class="text-left px-4 py-3">Agent</th>
            <th class="text-left px-4 py-3">Role</th>
            <th class="text-left px-4 py-3">Shift</th>
            <th class="text-right px-4 py-3">Queue cap</th>
            <th class="text-right px-4 py-3">Active</th>
            <th class="text-right px-4 py-3">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in entries" :key="row.id" class="border-t border-outline-variant/20">
            <td class="px-4 py-3">
              <p class="font-bold">{{ row.user?.name || row.user_id }}</p>
              <p class="text-xs text-on-surface-variant">{{ row.user?.email }}</p>
            </td>
            <td class="px-4 py-3 text-xs uppercase tracking-wider">{{ row.user?.admin_role }}</td>
            <td class="px-4 py-3 font-mono text-xs">{{ row.shift_start }} – {{ row.shift_end }}</td>
            <td class="px-4 py-3 text-right">
              <input type="number" min="1" max="40" v-model.number="row.queue_cap"
                class="w-20 bg-surface-container border border-outline-variant/50 rounded px-2 py-1 text-sm text-right" />
            </td>
            <td class="px-4 py-3 text-right">
              <input type="checkbox" v-model="row.active" />
            </td>
            <td class="px-4 py-3 text-right">
              <button @click="update(row)" class="px-3 py-1 rounded bg-surface-container border border-outline-variant/50 text-xs font-bold">Update</button>
            </td>
          </tr>
          <tr v-if="!entries.length">
            <td colspan="6" class="px-4 py-10 text-center text-on-surface-variant">No workforce entries.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { getWorkforce, saveWorkforce } from '../api'

const entries = ref([])
const loading = ref(true)
const message = ref('')
const error = ref('')
const form = reactive({ user_id: '', shift_start: '09:00', shift_end: '18:00', queue_cap: 10 })

async function load() {
  loading.value = true
  try {
    const res = await getWorkforce()
    entries.value = res.data || []
  } catch (e) {
    error.value = e.message || 'Failed to load workforce'
  } finally {
    loading.value = false
  }
}

async function save() {
  message.value = ''
  error.value = ''
  try {
    await saveWorkforce({ ...form, queue_cap: Number(form.queue_cap) })
    message.value = 'Workforce entry saved.'
    form.user_id = ''
    await load()
  } catch (e) {
    error.value = e.error || e.validation_error || e.message || 'Save failed'
  }
}

async function update(row) {
  message.value = ''
  error.value = ''
  try {
    await saveWorkforce({ queue_cap: Number(row.queue_cap), active: row.active }, row.id)
    message.value = `Updated ${row.user?.name || row.user_id}.`
  } catch (e) {
    error.value = e.error || e.message || 'Update failed'
  }
}

onMounted(load)
</script>
