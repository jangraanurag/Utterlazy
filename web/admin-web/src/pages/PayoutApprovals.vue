<template>
  <div class="p-6">
    <div class="mb-6">
      <h1 class="font-display font-bold text-2xl text-primary uppercase tracking-wide">Payout Approvals</h1>
      <p class="text-sm text-secondary mt-1">Finance reference implementation (§6) — weekly vendor payouts routed through the org hierarchy + DOA engine. Final approval marks the payout approved.</p>
    </div>

    <div v-if="loading" class="text-secondary text-sm py-10 text-center">Loading pending payouts…</div>
    <div v-else-if="payouts.length === 0" class="text-secondary text-sm py-10 text-center">No pending payouts to originate.</div>

    <div class="space-y-3">
      <div v-for="p in payouts" :key="p.id" class="rounded-2xl border border-white/10 bg-surface/50 p-4 flex items-center gap-4">
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <span class="font-bold text-primary text-sm">{{ p.restaurant }}</span>
            <span class="text-[10px] px-2 py-0.5 rounded-full bg-white/10 text-secondary">{{ p.week }}</span>
          </div>
          <div class="text-[11px] text-secondary mt-1">
            {{ p.district || 'no district' }}, {{ p.state || 'no state' }}
            <span v-if="!p.district || !p.state" class="text-amber-400"> — run geo backfill to enable auto-routing</span>
          </div>
        </div>
        <div class="text-right">
          <p class="font-bold text-primary">₹{{ Number(p.net_payable).toLocaleString('en-IN') }}</p>
          <p class="text-[10px] text-secondary">{{ p.status }}</p>
        </div>
        <button class="bg-primary text-black font-bold px-4 py-2 rounded-lg text-sm hover:opacity-90 disabled:opacity-40" @click="originate(p)" :disabled="busy === p.id">
          {{ busy === p.id ? 'Routing…' : 'Route for Approval' }}
        </button>
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
import { getPendingVendorPayouts, initiateVendorPayoutApproval } from '../api'

const payouts = ref([])
const loading = ref(true)
const busy = ref(null)
const result = ref(null)

const load = async () => {
  loading.value = true
  try { payouts.value = await getPendingVendorPayouts() }
  catch (e) { alert(e.message || 'Failed to load') }
  finally { loading.value = false }
}

const originate = async (p) => {
  busy.value = p.id
  result.value = null
  try {
    result.value = await initiateVendorPayoutApproval(p.id, false)
    await load()
  } catch (e) {
    alert(e.message || JSON.stringify(e.errors || e))
  } finally {
    busy.value = null
  }
}

onMounted(load)
</script>

