<template>
  <div>
    <h1 class="text-3xl font-serif font-bold text-on-surface mb-2">WhatsApp Ops</h1>
    <p class="text-on-surface-variant mb-8">
      Live conversation sessions, consent state, and support handoffs. No free-text blasts from this screen.
    </p>

    <div class="flex flex-wrap items-center gap-3 mb-6">
      <input v-model="waFilter" placeholder="Filter by WA ID" @keyup.enter="load"
        class="bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2 text-sm w-56" />
      <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" v-model="handoffOnly" @change="load" /> Handoff only
      </label>
      <button @click="load" class="px-4 py-2 rounded-lg bg-surface-container border border-outline-variant/50 text-sm font-bold">Refresh</button>
      <span v-if="!available" class="text-xs text-amber-400">WhatsApp service unreachable — {{ error }}</span>
    </div>

    <div v-if="loading" class="py-12 text-center text-on-surface-variant">Loading...</div>

    <div v-else class="bg-surface-container-lowest rounded-xl border border-outline-variant/30 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-surface-container text-on-surface-variant text-xs uppercase tracking-wider">
          <tr>
            <th class="text-left px-4 py-3">WA ID</th>
            <th class="text-left px-4 py-3">State</th>
            <th class="text-left px-4 py-3">Lang</th>
            <th class="text-left px-4 py-3">Consent</th>
            <th class="text-left px-4 py-3">Handoff</th>
            <th class="text-left px-4 py-3">Assign</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in filtered" :key="s.wa_id" class="border-t border-outline-variant/20">
            <td class="px-4 py-3 font-mono text-xs">{{ s.wa_id }}</td>
            <td class="px-4 py-3">
              <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full border border-outline-variant/40">{{ s.state }}</span>
            </td>
            <td class="px-4 py-3">{{ s.language || '—' }}</td>
            <td class="px-4 py-3 text-xs">
              <span :class="s.utility ? 'text-green-400' : 'text-red-400'">Utility</span> ·
              <span :class="s.marketing ? 'text-green-400' : 'text-on-surface-variant'">Marketing</span>
            </td>
            <td class="px-4 py-3 text-xs">
              <template v-if="s.handoff">
                <span class="text-amber-400">{{ s.handoff.status }}</span>
                <p class="text-on-surface-variant mt-0.5 max-w-[220px] truncate" :title="s.handoff.reason">{{ s.handoff.reason }}</p>
              </template>
              <span v-else class="text-on-surface-variant">—</span>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2" v-if="s.handoff">
                <input v-model="assignee[s.wa_id]" placeholder="assignee user id"
                  class="bg-surface-container border border-outline-variant/50 rounded-lg px-2 py-1 text-xs w-44" />
                <button @click="assign(s.wa_id)" :disabled="!assignee[s.wa_id]"
                  class="px-2 py-1 rounded bg-primary text-white text-xs font-bold disabled:opacity-40">Assign</button>
              </div>
              <span v-else class="text-xs text-on-surface-variant">—</span>
            </td>
          </tr>
          <tr v-if="!filtered.length">
            <td colspan="6" class="px-4 py-10 text-center text-on-surface-variant">No sessions.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <p v-if="message" class="mt-4 text-sm text-green-400">{{ message }}</p>
    <p v-if="actionError" class="mt-4 text-sm text-red-400">{{ actionError }}</p>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { getWaSessions, assignWaSession } from '../api'

const sessions = ref([])
const loading = ref(true)
const available = ref(true)
const error = ref('')
const message = ref('')
const actionError = ref('')
const waFilter = ref('')
const handoffOnly = ref(false)
const assignee = reactive({})

const filtered = computed(() =>
  sessions.value.filter(s => !waFilter.value || s.wa_id.includes(waFilter.value.trim()))
)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await getWaSessions({ handoff_only: handoffOnly.value })
    sessions.value = res.sessions || []
    available.value = res.available !== false
    if (!available.value) error.value = res.error || ''
  } catch (e) {
    error.value = e.message || 'Failed to load sessions'
    available.value = false
  } finally {
    loading.value = false
  }
}

async function assign(waId) {
  message.value = ''
  actionError.value = ''
  try {
    await assignWaSession(waId, assignee[waId])
    message.value = `Assigned ${waId}.`
    await load()
  } catch (e) {
    actionError.value = e.error || e.message || 'Assignment failed'
  }
}

onMounted(load)
</script>
