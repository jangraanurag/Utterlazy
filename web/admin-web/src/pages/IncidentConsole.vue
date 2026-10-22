<template>
  <div>
    <h1 class="text-3xl font-serif font-bold text-on-surface mb-2">Incident Console</h1>
    <p class="text-on-surface-variant mb-6">
      One owner per incident. SEV1 ack in 5 min, SEV2 in 15, SEV3 in 60. SLA breaches are flagged automatically.
    </p>

    <section v-if="canSos && sosAlerts.length" class="bg-red-500/10 border border-red-500/30 rounded-xl p-5 mb-8">
      <h2 class="font-bold text-red-300 mb-3">🚨 Open SOS</h2>
      <div v-for="alert in sosAlerts" :key="alert.id" class="border-t border-red-500/20 py-3 flex items-start justify-between gap-3">
        <div>
          <p class="font-bold">{{ alert.rider?.name || alert.rider_id }} <span class="text-xs text-red-300">{{ alert.status }}</span></p>
          <p class="text-xs text-on-surface-variant">
            {{ alert.rider?.phone || 'no phone' }} · triggered {{ formatTime(alert.triggered_at) }}
            <span v-if="alert.latitude != null"> · {{ Number(alert.latitude).toFixed(4) }},{{ Number(alert.longitude).toFixed(4) }}</span>
            <span v-if="alert.order_id"> · order #{{ String(alert.order_id).slice(0, 8) }}</span>
          </p>
        </div>
        <div class="flex gap-2 shrink-0">
          <button v-if="alert.status === 'open'" @click="ackSosAlert(alert)"
            class="px-3 py-1.5 rounded-lg bg-surface-container border border-red-500/40 text-xs font-bold text-red-200">Acknowledge</button>
          <button @click="resolveSosAlert(alert)"
            class="px-3 py-1.5 rounded-lg bg-red-500 text-white text-xs font-bold">Resolve</button>
        </div>
      </div>
    </section>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8" v-if="summary">
      <div class="bg-surface-container-lowest p-5 rounded-xl border border-outline-variant/30">
        <p class="text-xs uppercase tracking-wider text-on-surface-variant mb-2">Open</p>
        <p class="text-3xl font-bold">{{ summary.open }}</p>
      </div>
      <div class="bg-surface-container-lowest p-5 rounded-xl border border-outline-variant/30">
        <p class="text-xs uppercase tracking-wider text-on-surface-variant mb-2">SEV1 open</p>
        <p class="text-3xl font-bold" :class="summary.sev1_open > 0 ? 'text-red-400' : 'text-green-400'">{{ summary.sev1_open }}</p>
      </div>
      <div class="bg-surface-container-lowest p-5 rounded-xl border border-outline-variant/30">
        <p class="text-xs uppercase tracking-wider text-on-surface-variant mb-2">SLA breaches</p>
        <p class="text-3xl font-bold" :class="summary.breached_open > 0 ? 'text-red-400' : 'text-green-400'">{{ summary.breached_open }}</p>
      </div>
      <div class="bg-surface-container-lowest p-5 rounded-xl border border-outline-variant/30">
        <p class="text-xs uppercase tracking-wider text-on-surface-variant mb-2">Resolved 24h</p>
        <p class="text-3xl font-bold">{{ summary.resolved_24h }}</p>
      </div>
    </div>

    <form @submit.prevent="create" class="bg-surface-container-lowest rounded-xl border border-outline-variant/30 p-5 mb-8">
      <h2 class="font-bold mb-4">Open incident</h2>
      <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
        <select v-model="form.severity" required class="input">
          <option value="sev1">SEV1</option>
          <option value="sev2">SEV2</option>
          <option value="sev3">SEV3</option>
        </select>
        <select v-model="form.category" required class="input">
          <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
        </select>
        <select v-model="form.city_id" class="input">
          <option value="">All cities</option>
          <option v-for="c in cities" :key="c.id" :value="c.id">{{ c.name }}</option>
        </select>
        <input v-model="form.title" required minlength="5" maxlength="160" placeholder="Short title" class="input md:col-span-2" />
        <textarea v-model="form.description" rows="2" maxlength="2000" placeholder="What is happening? (optional)"
          class="input md:col-span-4"></textarea>
        <button type="submit" class="px-4 py-2 rounded-lg bg-primary text-white text-sm font-bold">Open</button>
      </div>
      <p v-if="formError" class="mt-3 text-sm text-red-400">{{ formError }}</p>
    </form>

    <div class="flex items-center gap-3 mb-4">
      <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" v-model="openOnly" @change="load" /> Open only
      </label>
      <select v-model="severityFilter" @change="load" class="input w-40">
        <option value="">All severities</option>
        <option value="sev1">SEV1</option>
        <option value="sev2">SEV2</option>
        <option value="sev3">SEV3</option>
      </select>
      <button @click="load" class="px-4 py-2 rounded-lg bg-surface-container border border-outline-variant/50 text-sm font-bold">Refresh</button>
      <span v-if="message" class="text-sm text-green-400">{{ message }}</span>
      <span v-if="error" class="text-sm text-red-400">{{ error }}</span>
    </div>

    <div v-if="loading" class="py-12 text-center text-on-surface-variant">Loading...</div>

    <div v-else class="space-y-3">
      <div v-for="incident in incidents" :key="incident.id"
        class="bg-surface-container-lowest rounded-xl border border-outline-variant/30 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="flex items-center gap-2 mb-1">
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                :class="incident.severity === 'sev1' ? 'bg-red-500/20 text-red-300' : incident.severity === 'sev2' ? 'bg-amber-500/20 text-amber-300' : 'bg-surface-container text-on-surface-variant'">
                {{ incident.severity.toUpperCase() }}
              </span>
              <span class="text-[10px] uppercase tracking-wider text-on-surface-variant">{{ incident.category }}</span>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border border-outline-variant/40">{{ incident.status }}</span>
              <span v-if="incident.sla_breached_at" class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-red-500/20 text-red-300">SLA breach</span>
            </div>
            <p class="font-bold">{{ incident.title }}</p>
            <p class="text-xs text-on-surface-variant mt-1">
              {{ incident.city?.name || 'All cities' }} · opened {{ formatTime(incident.opened_at) }}
              · SLA due {{ formatTime(incident.sla_due_at) }}
              <span v-if="incident.owner">· owner {{ incident.owner.name }}</span>
            </p>
            <p v-if="incident.description" class="text-sm mt-1 text-on-surface-variant">{{ incident.description }}</p>
            <p v-if="incident.resolution_note" class="text-xs mt-1 text-green-400">Resolution: {{ incident.resolution_note }}</p>
          </div>

          <div class="flex flex-col gap-2 shrink-0" v-if="incident.status !== 'resolved'">
            <button v-if="incident.status === 'open'" @click="ack(incident)"
              class="px-3 py-1.5 rounded-lg bg-surface-container border border-outline-variant/50 text-xs font-bold">Acknowledge</button>
            <button @click="resolve(incident)"
              class="px-3 py-1.5 rounded-lg bg-primary text-white text-xs font-bold">Resolve</button>
            <div class="flex gap-1">
              <input v-model="owners[incident.id]" placeholder="owner user id"
                class="bg-surface-container border border-outline-variant/50 rounded px-2 py-1 text-[11px] w-32" />
              <button @click="assign(incident)" :disabled="!owners[incident.id]"
                class="px-2 py-1 rounded bg-surface-container border border-outline-variant/50 text-[11px] font-bold disabled:opacity-40">Assign</button>
            </div>
          </div>
        </div>
      </div>
      <div v-if="!incidents.length" class="text-center py-12 text-on-surface-variant">No incidents.</div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { getIncidents, createIncident, ackIncident, resolveIncident, assignIncident, getCities, getSos, ackSos, resolveSos } from '../api'

const categories = ['payments', 'riders', 'kitchen', 'whatsapp', 'infra', 'catalog', 'safety', 'other']
const adminUser = (() => { try { return JSON.parse(localStorage.getItem('admin_user') || '{}') } catch { return {} } })()
const canSos = ['super_admin', 'rider_ops', 'city_ops', 'support_lead', 'auditor'].includes(adminUser.admin_role)
const sosAlerts = ref([])

const incidents = ref([])
const summary = ref(null)
const cities = ref([])
const loading = ref(true)
const error = ref('')
const message = ref('')
const formError = ref('')
const openOnly = ref(true)
const severityFilter = ref('')
const owners = reactive({})

const form = reactive({
  severity: 'sev2',
  category: 'infra',
  city_id: '',
  title: '',
  description: '',
})

const formatTime = (value) => value ? new Date(value).toLocaleString('en-IN', { dateStyle: 'short', timeStyle: 'short' }) : '—'

async function loadSos() {
  if (!canSos) return
  try {
    const res = await getSos({ open_only: 1 })
    sosAlerts.value = res.alerts?.data || []
  } catch { sosAlerts.value = [] }
}

async function ackSosAlert(alert) {
  try { await ackSos(alert.id); await loadSos() } catch (e) { error.value = e.error || e.message || 'SOS ack failed' }
}

async function resolveSosAlert(alert) {
  const note = prompt('SOS resolution note (10-500 chars):')
  if (!note) return
  try { await resolveSos(alert.id, note); await loadSos() } catch (e) { error.value = e.error || e.message || 'SOS resolve failed' }
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    await loadSos()
    const res = await getIncidents({
      open_only: openOnly.value ? 1 : '',
      severity: severityFilter.value,
    })
    incidents.value = res.incidents?.data || []
    summary.value = res.summary || null
  } catch (e) {
    error.value = e.message || 'Failed to load incidents'
  } finally {
    loading.value = false
  }
}

async function create() {
  formError.value = ''
  message.value = ''
  try {
    const payload = { ...form }
    if (!payload.city_id) delete payload.city_id
    await createIncident(payload)
    message.value = 'Incident opened.'
    form.title = ''
    form.description = ''
    await load()
  } catch (e) {
    formError.value = e.error || e.message || JSON.stringify(e)
  }
}

async function ack(incident) {
  message.value = ''
  try {
    await ackIncident(incident.id)
    message.value = 'Acknowledged.'
    await load()
  } catch (e) {
    error.value = e.error || e.message || 'Ack failed'
  }
}

async function resolve(incident) {
  const note = prompt('Resolution note (10-500 chars):')
  if (!note) return
  message.value = ''
  try {
    await resolveIncident(incident.id, note)
    message.value = 'Resolved.'
    await load()
  } catch (e) {
    error.value = e.error || e.message || 'Resolve failed'
  }
}

async function assign(incident) {
  message.value = ''
  try {
    await assignIncident(incident.id, owners[incident.id])
    message.value = 'Assigned.'
    await load()
  } catch (e) {
    error.value = e.error || e.message || 'Assign failed'
  }
}

onMounted(async () => {
  try {
    const res = await getCities()
    cities.value = res.data || res || []
  } catch { /* non-blocking */ }
  await load()
})
</script>

<style scoped>
.input {
  width: 100%;
  border: 1px solid rgba(148, 163, 184, 0.35);
  border-radius: 0.5rem;
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  background: transparent;
}
</style>
