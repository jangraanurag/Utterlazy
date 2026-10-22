<template>
  <div class="p-8 max-w-5xl mx-auto">
    <h1 class="text-2xl font-bold mb-1">WhatsApp Promotions</h1>
    <p class="text-sm opacity-70 mb-6">
      Paid placements appear only in sponsored WhatsApp recommendations and are labelled "Sponsored".
      Your organic listing is never affected. A placement goes live after approval and spends CPC per tap.
    </p>

    <div class="rounded-xl border p-5 mb-8" style="border-color: var(--border-color, rgba(0,0,0,.08))">
      <h2 class="font-bold mb-4">New campaign</h2>

      <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-bold uppercase tracking-wider mb-1">City</label>
          <select v-model="form.city_id" required class="input">
            <option value="" disabled>Select city</option>
            <option v-for="c in cities" :key="c.id" :value="c.id">{{ c.name }} — {{ c.state }}</option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-bold uppercase tracking-wider mb-1">Daily budget (₹100–₹50,000)</label>
          <input type="number" min="100" max="50000" step="1" v-model.number="form.daily_budget" required class="input" />
        </div>

        <div>
          <label class="block text-xs font-bold uppercase tracking-wider mb-1">CPC bid (₹) — must be ≥ city floor</label>
          <input type="number" min="0.01" step="0.01" v-model.number="form.cpc_bid" required class="input" />
        </div>

        <div>
          <label class="block text-xs font-bold uppercase tracking-wider mb-1">Start</label>
          <input type="datetime-local" v-model="form.start_at" required class="input" />
        </div>

        <div>
          <label class="block text-xs font-bold uppercase tracking-wider mb-1">End (max 90 days)</label>
          <input type="datetime-local" v-model="form.end_at" required class="input" />
        </div>

        <div>
          <label class="block text-xs font-bold uppercase tracking-wider mb-1">Title (3–40)</label>
          <input v-model="form.creative_title" required minlength="3" maxlength="40" class="input" />
        </div>

        <div class="md:col-span-2">
          <label class="block text-xs font-bold uppercase tracking-wider mb-1">Body (10–120) — no phone numbers, no "guaranteed"</label>
          <input v-model="form.creative_body" required minlength="10" maxlength="120" class="input" />
        </div>

        <div class="md:col-span-2 flex items-center gap-3">
          <button type="submit" :disabled="saving" class="px-5 py-2 rounded-lg text-white font-bold bg-orange-600 disabled:opacity-40">
            {{ saving ? 'Submitting…' : 'Submit for approval' }}
          </button>
          <span v-if="message" class="text-sm text-green-600">{{ message }}</span>
          <span v-if="error" class="text-sm text-red-600">{{ error }}</span>
        </div>
      </form>
    </div>

    <h2 class="font-bold mb-3">Your placements</h2>

    <div v-if="loading" class="py-8 text-center opacity-60">Loading…</div>

    <div v-else-if="!placements.length" class="py-8 text-center opacity-60">No placements yet.</div>

    <div v-else class="space-y-3">
      <div v-for="p in placements" :key="p.id" class="rounded-xl border p-4 flex items-start justify-between gap-4"
        style="border-color: var(--border-color, rgba(0,0,0,.08))">
        <div>
          <div class="flex items-center gap-2 mb-1">
            <p class="font-bold">{{ p.creative_title }}</p>
            <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded-full border">{{ p.status.replace('_', ' ') }}</span>
            <span v-if="p.status === 'active'" class="text-[10px] uppercase tracking-wider text-orange-600">Sponsored</span>
          </div>
          <p class="text-sm opacity-70">{{ p.creative_body }}</p>
          <p class="text-xs opacity-60 mt-1">
            ₹{{ Number(p.daily_budget).toFixed(0) }}/day · bid ₹{{ Number(p.cpc_bid).toFixed(2) }} ·
            spent ₹{{ Number(p.spent_today).toFixed(2) }}
            <span v-if="p.pinned_floor != null"> · floor ₹{{ Number(p.pinned_floor).toFixed(2) }}</span>
          </p>
          <p v-if="p.rejection_reason" class="text-xs text-red-600 mt-1">Rejected: {{ p.rejection_reason }}</p>
        </div>
        <button v-if="p.status === 'active'" @click="pause(p)"
          class="px-3 py-1 rounded-lg text-xs font-bold border border-red-300 text-red-600">Pause</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { getPublicCities, getWaPlacements, createWaPlacement, pauseWaPlacement } from '../api'

const cities = ref([])
const placements = ref([])
const loading = ref(true)
const saving = ref(false)
const message = ref('')
const error = ref('')

const form = reactive({
  city_id: '',
  daily_budget: 500,
  cpc_bid: 5,
  start_at: '',
  end_at: '',
  creative_title: '',
  creative_body: '',
})

const toIso = (v) => (v ? new Date(v).toISOString() : null)

async function load() {
  loading.value = true
  try {
    const [c, p] = await Promise.all([getPublicCities(), getWaPlacements()])
    cities.value = Array.isArray(c) ? c.filter(x => x.is_active !== false) : (c.data || [])
    placements.value = p.data || []
  } catch (e) {
    error.value = e.message || 'Failed to load'
  } finally {
    loading.value = false
  }
}

async function submit() {
  message.value = ''
  error.value = ''

  if (/guaranteed/i.test(form.creative_body)) {
    error.value = 'Body must not use the word "guaranteed".'
    return
  }
  if (/(\+?91)?[6-9]\d{9}/.test(form.creative_body.replace(/\s+/g, ''))) {
    error.value = 'Body must not contain a phone number.'
    return
  }

  saving.value = true
  try {
    await createWaPlacement({
      city_id: form.city_id,
      daily_budget: Number(form.daily_budget),
      cpc_bid: Number(form.cpc_bid),
      start_at: toIso(form.start_at),
      end_at: toIso(form.end_at),
      creative_title: form.creative_title.trim(),
      creative_body: form.creative_body.trim(),
    })
    message.value = 'Submitted for approval.'
    form.creative_title = ''
    form.creative_body = ''
    await load()
  } catch (e) {
    error.value = e.error || e.message || 'Submit failed'
  } finally {
    saving.value = false
  }
}

async function pause(p) {
  error.value = ''
  try {
    await pauseWaPlacement(p.id)
    message.value = 'Placement paused.'
    await load()
  } catch (e) {
    error.value = e.error || e.message || 'Pause failed'
  }
}

onMounted(load)
</script>

<style scoped>
.input {
  width: 100%;
  border: 1px solid rgba(0, 0, 0, 0.15);
  border-radius: 0.5rem;
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  background: transparent;
}
</style>
