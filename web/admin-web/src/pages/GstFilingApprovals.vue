<template>
  <div class="p-6">
    <div class="mb-6">
      <h1 class="font-display font-bold text-2xl text-primary uppercase tracking-wide">GST Filing (Accountant Team)</h1>
      <p class="text-sm text-secondary mt-1">Monthly GST liability computed per state from delivered orders, routed through the finance org hierarchy + DOA engine. Final approval marks the filing deposited. Fully scalable — any state, any period.</p>
    </div>

    <div class="rounded-2xl border border-white/10 bg-surface/50 p-4 mb-6 flex items-end gap-4 flex-wrap">
      <div>
        <label class="block text-[11px] uppercase tracking-wider text-white/60 font-bold mb-1">Year</label>
        <select v-model="year" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-primary">
          <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
        </select>
      </div>
      <div>
        <label class="block text-[11px] uppercase tracking-wider text-white/60 font-bold mb-1">Month</label>
        <select v-model="month" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-primary">
          <option v-for="m in 12" :key="m" :value="m">{{ String(m).padStart(2, '0') }}</option>
        </select>
      </div>
      <button @click="compute" :disabled="computing" class="bg-primary text-black font-bold px-4 py-2 rounded-lg text-sm hover:opacity-90 disabled:opacity-40">
        {{ computing ? 'Computing…' : 'Compute Liability' }}
      </button>
      <button @click="load" class="border border-white/15 text-white/60 px-4 py-2 rounded-lg text-sm hover:text-white">Refresh</button>
      <div v-if="computedMsg" class="text-emerald-400 text-sm">{{ computedMsg }}</div>
    </div>

    <div v-if="loading" class="text-secondary text-sm py-10 text-center">Loading filings…</div>
    <div v-else-if="filings.length === 0" class="text-secondary text-sm py-10 text-center">No filings yet — pick a period and compute liability to start.</div>

    <div class="rounded-2xl border border-white/10 overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-white/5 text-secondary text-[11px] uppercase tracking-wider">
            <th class="text-left px-4 py-3">State</th>
            <th class="text-left px-4 py-3">Period</th>
            <th class="text-right px-4 py-3">GST collected (₹)</th>
            <th class="text-right px-4 py-3">Deposited (₹)</th>
            <th class="text-left px-4 py-3">Status</th>
            <th class="text-right px-4 py-3">Action</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="f in filings" :key="f.id" class="border-t border-white/5 hover:bg-white/5">
            <td class="px-4 py-3 text-primary font-bold">{{ f.state || '— (unmapped)' }}</td>
            <td class="px-4 py-3">{{ f.filing_period }}</td>
            <td class="px-4 py-3 text-right font-bold text-primary">₹{{ Number(f.total_gst_collected).toLocaleString('en-IN') }}</td>
            <td class="px-4 py-3 text-right">₹{{ Number(f.total_gst_deposited).toLocaleString('en-IN') }}</td>
            <td class="px-4 py-3">
              <span :class="statusClass(f.status)" class="text-[10px] px-2 py-0.5 rounded-full uppercase">{{ f.status }}</span>
            </td>
            <td class="px-4 py-3 text-right">
              <button v-if="f.status !== 'filed'" @click="originate(f)" :disabled="busy === f.id" class="bg-primary text-black font-bold px-3 py-1.5 rounded-lg text-xs hover:opacity-90 disabled:opacity-40">
                {{ busy === f.id ? 'Routing…' : 'Route for Approval' }}
              </button>
              <span v-else class="text-[10px] text-emerald-400 uppercase">Filed ✓</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="result" class="mt-6 rounded-2xl border border-emerald-500/30 bg-emerald-500/5 p-4 text-sm">
      <p class="text-emerald-400 font-bold mb-1">✓ Originated at {{ result.origin_org_unit }}</p>
      <p class="text-secondary">Status: {{ result.status_label }} · reviewer: {{ result.reviewer || 'auto-escalating…' }} · SLA due: {{ result.sla_due_at }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getGstFilings, computeGstLiability, initiateGstFilingApproval } from '../api'

const years = [2026, 2025, 2024]
const year = ref(2026)
const month = ref(new Date().getMonth() + 1)
const filings = ref([])
const loading = ref(true)
const computing = ref(false)
const busy = ref(null)
const result = ref(null)
const computedMsg = ref('')

const statusClass = (s) => ({
  filed: 'bg-emerald-500/20 text-emerald-400',
  pending: 'bg-amber-500/20 text-amber-400',
  default: 'bg-white/10 text-secondary',
}[s] || 'bg-white/10 text-secondary')

const load = async () => {
  loading.value = true
  try { filings.value = await getGstFilings() }
  catch (e) { alert(e.message || 'Failed to load') }
  finally { loading.value = false }
}

const compute = async () => {
  computing.value = true
  computedMsg.value = ''
  try {
    const rows = await computeGstLiability(year.value, month.value)
    computedMsg.value = `Computed ${rows.length} filing(s) for ${year.value}-${String(month.value).padStart(2, '0')}`
    await load()
  } catch (e) {
    alert(e.message || JSON.stringify(e.errors || e))
  } finally {
    computing.value = false
  }
}

const originate = async (f) => {
  busy.value = f.id
  result.value = null
  try {
    result.value = await initiateGstFilingApproval(f.id, false)
    await load()
  } catch (e) {
    alert(e.message || JSON.stringify(e.errors || e))
  } finally {
    busy.value = null
  }
}

onMounted(load)
</script>
