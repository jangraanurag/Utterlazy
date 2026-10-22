<template>
  <div class="p-6">
    <div class="mb-6">
      <h1 class="font-display font-bold text-2xl text-primary uppercase tracking-wide">AM Restaurant Assignment</h1>
      <p class="text-sm text-secondary mt-1">Account Management reference implementation (§6) — restaurant→AM assignments are geography-routed through the AM org hierarchy instead of a flat admin-assigns-anyone model. Final approval creates the active assignment.</p>
    </div>

    <div v-if="loading" class="text-secondary text-sm py-10 text-center">Loading…</div>
    <div v-else>
      <div v-if="restaurants.length === 0" class="text-secondary text-sm py-10 text-center">All restaurants have an active AM.</div>

      <div class="space-y-3">
        <div v-for="r in restaurants" :key="r.id" class="rounded-2xl border border-white/10 bg-surface/50 p-4 flex items-center gap-4">
          <div class="flex-1 min-w-0">
            <p class="font-bold text-primary text-sm">{{ r.name }}</p>
            <p class="text-[11px] text-secondary mt-1">
              {{ r.district || 'no district' }}, {{ r.state || 'no state' }}
              <span v-if="!r.district || !r.state" class="text-amber-400"> — no geography, will route as exception</span>
            </p>
          </div>
          <select v-model="selectedAm[r.id]" class="bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-primary">
            <option value="" disabled>Select AM…</option>
            <option v-for="a in accountManagers" :key="a.id" :value="a.id">{{ a.name }}</option>
          </select>
          <button class="bg-primary text-black font-bold px-4 py-2 rounded-lg text-sm hover:opacity-90 disabled:opacity-40" @click="originate(r)" :disabled="busy === r.id || !selectedAm[r.id]">
            {{ busy === r.id ? 'Routing…' : 'Route for Approval' }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="result" class="mt-6 rounded-2xl border border-emerald-500/30 bg-emerald-500/5 p-4 text-sm">
      <p class="text-emerald-400 font-bold mb-1">✓ Originated at {{ result.origin_org_unit }}</p>
      <p class="text-secondary">Status: {{ result.status_label }} · reviewer: {{ result.reviewer || 'auto-escalating…' }} · SLA due: {{ result.sla_due_at }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getPendingRestaurantAssignments, initiateRestaurantAssignmentApproval } from '../api'

const restaurants = ref([])
const accountManagers = ref([])
const selectedAm = ref({})
const loading = ref(true)
const busy = ref(null)
const result = ref(null)

const load = async () => {
  loading.value = true
  try {
    const d = await getPendingRestaurantAssignments()
    restaurants.value = d.restaurants
    accountManagers.value = d.account_managers
  } catch (e) {
    alert(e.message || 'Failed to load')
  } finally {
    loading.value = false
  }
}

const originate = async (r) => {
  busy.value = r.id
  result.value = null
  try {
    result.value = await initiateRestaurantAssignmentApproval(r.id, selectedAm.value[r.id])
    await load()
  } catch (e) {
    alert(e.message || JSON.stringify(e.errors || e))
  } finally {
    busy.value = null
  }
}

onMounted(load)
</script>
