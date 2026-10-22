<template>
  <div>
    <h1 class="text-3xl font-serif font-bold text-on-surface mb-2">Shift Planner</h1>
    <p class="text-on-surface-variant mb-6">
      Scheduled ops coverage tied to city and org unit. Overlapping shifts for the same agent are rejected.
    </p>

    <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/30 p-5 mb-8">
      <h2 class="font-bold mb-1">Auto-rotate week</h2>
      <p class="text-xs text-on-surface-variant mb-4">
        Deterministic rotation from active workforce. Existing shifts are respected; gaps are reported, never silently understaffed.
      </p>
      <div class="grid grid-cols-1 md:grid-cols-5 gap-3 items-end">
        <div>
          <label class="label">Week start (Mon)</label>
          <input type="date" v-model="rotationWeek" class="input" />
        </div>
        <div>
          <label class="label">Start time</label>
          <input type="time" v-model="rotStart" class="input" />
        </div>
        <div>
          <label class="label">Hours (1–12)</label>
          <input type="number" min="1" max="12" v-model.number="rotHours" class="input" />
        </div>
        <div>
          <label class="label">Agents per day</label>
          <input type="number" min="1" max="20" v-model.number="rotPerDay" class="input" />
        </div>
        <div class="flex gap-2">
          <button @click="runRotation(false)" :disabled="rotating"
            class="px-3 py-2 rounded-lg bg-surface-container border border-outline-variant/50 text-xs font-bold disabled:opacity-40">Preview</button>
          <button @click="runRotation(true)" :disabled="rotating"
            class="px-3 py-2 rounded-lg bg-primary text-white text-xs font-bold disabled:opacity-40">Apply</button>
        </div>
      </div>
      <p v-if="rotationMessage" class="mt-3 text-sm text-green-400">{{ rotationMessage }}</p>
      <div v-if="rotationGaps.length" class="mt-2 text-xs text-amber-400">
        Gaps: <span v-for="gap in rotationGaps" :key="gap.date + (gap.city_id || '')" class="mr-2">{{ gap.date }} ({{ gap.missing }})</span>
      </div>
    </div>

    <form @submit.prevent="create" class="bg-surface-container-lowest rounded-xl border border-outline-variant/30 p-5 mb-8">
      <h2 class="font-bold mb-4">Schedule a shift</h2>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <div>
          <label class="label">Agent</label>
          <select v-model="form.user_id" required class="input">
            <option value="" disabled>Select staff</option>
            <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }} — {{ s.admin_role }}</option>
          </select>
        </div>
        <div>
          <label class="label">Role snapshot</label>
          <input v-model="form.role" maxlength="40" placeholder="support_l1" class="input" />
        </div>
        <div>
          <label class="label">City</label>
          <select v-model="form.city_id" class="input">
            <option value="">All cities</option>
            <option v-for="c in cities" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>
        </div>
        <div>
          <label class="label">Org unit id (optional)</label>
          <input v-model="form.org_unit_id" placeholder="uuid" class="input" />
        </div>
        <div>
          <label class="label">Starts</label>
          <input type="datetime-local" v-model="form.starts_at" required class="input" />
        </div>
        <div>
          <label class="label">Ends (max 12h)</label>
          <input type="datetime-local" v-model="form.ends_at" required class="input" />
        </div>
        <div class="md:col-span-2">
          <label class="label">Notes</label>
          <input v-model="form.notes" maxlength="200" placeholder="optional" class="input" />
        </div>
        <div class="md:col-span-4 flex items-center gap-3">
          <button class="px-5 py-2 rounded-lg bg-primary text-white text-sm font-bold">Schedule</button>
          <span v-if="message" class="text-sm text-green-400">{{ message }}</span>
          <span v-if="formError" class="text-sm text-red-400">{{ formError }}</span>
        </div>
      </div>
    </form>

    <div class="flex flex-wrap items-center gap-3 mb-4">
      <input type="date" v-model="day" @change="load" class="input w-44" />
      <select v-model="cityFilter" @change="load" class="input w-52">
        <option value="">All cities</option>
        <option v-for="c in cities" :key="c.id" :value="c.id">{{ c.name }}</option>
      </select>
      <button @click="load" class="px-4 py-2 rounded-lg bg-surface-container border border-outline-variant/50 text-sm font-bold">Refresh</button>
      <span v-if="coverage" class="text-sm text-on-surface-variant">
        Coverage: {{ coverage.agents }} agents · {{ coverage.shifts }} shifts
      </span>
      <span v-if="error" class="text-sm text-red-400">{{ error }}</span>
    </div>

    <div v-if="loading" class="py-12 text-center text-on-surface-variant">Loading...</div>

    <div v-else class="space-y-3">
      <div v-for="shift in shifts" :key="shift.id"
        class="bg-surface-container-lowest rounded-xl border border-outline-variant/30 p-4 flex items-start justify-between gap-3">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <p class="font-bold">{{ shift.user?.name || shift.user_id }}</p>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border border-outline-variant/40">{{ shift.status }}</span>
            <span class="text-[10px] uppercase tracking-wider text-on-surface-variant">{{ shift.role || shift.user?.admin_role }}</span>
          </div>
          <p class="text-xs text-on-surface-variant">
            {{ formatTime(shift.starts_at) }} → {{ formatTime(shift.ends_at) }}
            · {{ shift.city?.name || 'All cities' }}
            <span v-if="shift.orgUnit"> · {{ shift.orgUnit.name }}</span>
          </p>
          <p v-if="shift.notes" class="text-xs text-on-surface-variant mt-1">{{ shift.notes }}</p>
          <p v-if="shift.cancelled_reason" class="text-xs text-red-400 mt-1">Cancelled: {{ shift.cancelled_reason }}</p>
        </div>
        <div class="flex gap-2 shrink-0" v-if="['scheduled','active'].includes(shift.status)">
          <button v-if="shift.status === 'scheduled'" @click="activate(shift)"
            class="px-3 py-1.5 rounded-lg bg-surface-container border border-outline-variant/50 text-xs font-bold">Activate</button>
          <button @click="cancel(shift)"
            class="px-3 py-1.5 rounded-lg bg-red-500/20 text-red-300 text-xs font-bold">Cancel</button>
        </div>
      </div>
      <div v-if="!shifts.length" class="text-center py-12 text-on-surface-variant">No shifts for this day.</div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { getShifts, getShiftCoverage, createShift, activateShift, cancelShift, getStaff, getCities, rotateShifts } from '../api'

const shifts = ref([])
const staff = ref([])
const cities = ref([])
const coverage = ref(null)
const loading = ref(true)
const error = ref('')
const message = ref('')
const formError = ref('')
const day = ref(new Date().toISOString().slice(0, 10))
const cityFilter = ref('')

const rotationWeek = ref(nextMonday())
const rotStart = ref('09:00')
const rotHours = ref(8)
const rotPerDay = ref(1)
const rotating = ref(false)
const rotationMessage = ref('')
const rotationGaps = ref([])

const form = reactive({
  user_id: '',
  role: '',
  city_id: '',
  org_unit_id: '',
  starts_at: '',
  ends_at: '',
  notes: '',
})

function nextMonday() {
  const d = new Date()
  const day = d.getDay()
  const delta = ((8 - day) % 7) || 7
  d.setDate(d.getDate() + delta)
  return d.toISOString().slice(0, 10)
}

async function runRotation(apply) {
  rotating.value = true
  rotationMessage.value = ''
  rotationGaps.value = []
  try {
    const res = await rotateShifts({
      week_start: rotationWeek.value,
      start_time: rotStart.value,
      hours: Number(rotHours.value),
      per_day: Number(rotPerDay.value),
      days: 7,
      city_id: cityFilter.value || undefined,
      apply,
    })
    const entries = res.plan?.entries?.length || 0
    rotationGaps.value = res.plan?.gaps || []
    if (apply) {
      rotationMessage.value = `Applied: created ${res.result?.created ?? 0}, skipped ${res.result?.skipped ?? 0}, staffing incidents ${res.incidents?.created ?? 0}.`
      await load()
    } else {
      rotationMessage.value = `Preview: ${entries} shift(s), ${rotationGaps.value.length} gap day(s).`
    }
  } catch (e) {
    rotationMessage.value = e.error || e.message || 'Rotation failed'
  } finally {
    rotating.value = false
  }
}

const formatTime = (value) => new Date(value).toLocaleString('en-IN', { dateStyle: 'short', timeStyle: 'short' })
const toIso = (v) => (v ? new Date(v).toISOString() : null)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await getShifts({ date: day.value, city_id: cityFilter.value })
    shifts.value = res.data || []
    coverage.value = await getShiftCoverage({ date: day.value, city_id: cityFilter.value })
  } catch (e) {
    error.value = e.message || 'Failed to load shifts'
  } finally {
    loading.value = false
  }
}

async function create() {
  message.value = ''
  formError.value = ''
  try {
    const payload = {
      user_id: form.user_id,
      role: form.role || undefined,
      starts_at: toIso(form.starts_at),
      ends_at: toIso(form.ends_at),
      notes: form.notes || undefined,
    }
    if (form.city_id) payload.city_id = form.city_id
    if (form.org_unit_id) payload.org_unit_id = form.org_unit_id

    await createShift(payload)
    message.value = 'Shift scheduled.'
    form.starts_at = ''
    form.ends_at = ''
    form.notes = ''
    await load()
  } catch (e) {
    formError.value = e.error || e.message || JSON.stringify(e)
  }
}

async function activate(shift) {
  try {
    await activateShift(shift.id)
    await load()
  } catch (e) {
    error.value = e.error || e.message || 'Activate failed'
  }
}

async function cancel(shift) {
  const reason = prompt('Cancellation reason (5-200 chars):')
  if (!reason) return
  try {
    await cancelShift(shift.id, reason)
    await load()
  } catch (e) {
    error.value = e.error || e.message || 'Cancel failed'
  }
}

onMounted(async () => {
  try {
    const [staffRes, cityRes] = await Promise.all([getStaff({ per_page: 100 }), getCities()])
    staff.value = staffRes.data || []
    cities.value = cityRes.data || cityRes || []
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
.label {
  display: block;
  font-size: 0.65rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  margin-bottom: 0.25rem;
  opacity: 0.7;
}
</style>
