<template>
  <div class="p-6">
    <div class="mb-6">
      <h1 class="font-display font-bold text-2xl text-primary uppercase tracking-wide">Rider KYC Approvals</h1>
      <p class="text-sm text-secondary mt-1">Rider Ops reference implementation (§6) — rider KYC verification queues routed through the org hierarchy at the right geographic level. Final approval verifies the rider and approves all uploaded documents.</p>
    </div>

    <div v-if="loading" class="text-secondary text-sm py-10 text-center">Loading pending KYC verifications…</div>
    <div v-else-if="riders.length === 0" class="text-secondary text-sm py-10 text-center">No pending KYC verifications (riders with uploaded documents who aren't yet verified).</div>

    <div class="space-y-3">
      <div v-for="r in riders" :key="r.id" class="rounded-2xl border border-white/10 bg-surface/50 p-4 flex items-center gap-4">
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <span class="font-bold text-primary text-sm">{{ r.name }}</span>
            <span class="text-[10px] px-2 py-0.5 rounded-full bg-white/10 text-secondary">{{ r.phone }}</span>
            <span class="text-[10px] px-2 py-0.5 rounded-full bg-white/10 text-secondary">{{ r.vehicle || 'no vehicle' }}</span>
          </div>
          <div class="text-[11px] text-secondary mt-1">
            {{ r.district || 'no district' }}, {{ r.state || 'no state' }}
            <span v-if="!r.district || !r.state" class="text-amber-400"> — run geo backfill to enable auto-routing</span>
          </div>
          <div class="flex gap-1.5 mt-2 flex-wrap">
            <span v-for="(status, type) in r.doc_statuses" :key="type" class="text-[10px] px-2 py-0.5 rounded-full" :class="status === 'approved' ? 'bg-emerald-500/20 text-emerald-400' : status === 'rejected' ? 'bg-red-500/20 text-red-400' : 'bg-amber-500/20 text-amber-400'">
              {{ type }}: {{ status }}
            </span>
          </div>
        </div>
        <button class="bg-primary text-black font-bold px-4 py-2 rounded-lg text-sm hover:opacity-90 disabled:opacity-40" @click="originate(r)" :disabled="busy === r.id">
          {{ busy === r.id ? 'Routing…' : 'Route for Approval' }}
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
import { getPendingKycVerifications, initiateKycVerificationApproval } from '../api'

const riders = ref([])
const loading = ref(true)
const busy = ref(null)
const result = ref(null)

const load = async () => {
  loading.value = true
  try { riders.value = await getPendingKycVerifications() }
  catch (e) { alert(e.message || 'Failed to load') }
  finally { loading.value = false }
}

const originate = async (r) => {
  busy.value = r.id
  result.value = null
  try {
    result.value = await initiateKycVerificationApproval(r.id, false)
    await load()
  } catch (e) {
    alert(e.message || JSON.stringify(e.errors || e))
  } finally {
    busy.value = null
  }
}

onMounted(load)
</script>
