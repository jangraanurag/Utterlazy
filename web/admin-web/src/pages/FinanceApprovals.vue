<template>
  <div class="p-6">
    <div class="mb-6">
      <h1 class="font-display font-bold text-2xl text-primary uppercase tracking-wide">Invoice &amp; Recovery Approvals</h1>
      <p class="text-sm text-secondary mt-1">Finance reference implementation (§6) — invoice sign-offs and restaurant recoveries routed through the org hierarchy + DOA engine. Final approval marks invoices approved and recoveries invoiced.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <section>
        <h2 class="font-bold text-primary text-sm uppercase tracking-wide mb-3">Pending Invoices</h2>
        <div v-if="loadingInvoices" class="text-secondary text-sm py-6 text-center">Loading invoices…</div>
        <div v-else-if="invoices.length === 0" class="text-secondary text-sm py-6 text-center border border-white/10 rounded-2xl">No pending invoices.</div>
        <div class="space-y-3">
          <div v-for="i in invoices" :key="i.id" class="rounded-2xl border border-white/10 bg-surface/50 p-4 flex items-center gap-4">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <span class="font-bold text-primary text-sm">{{ i.invoice_number || 'Unnumbered' }}</span>
                <span class="text-[10px] px-2 py-0.5 rounded-full bg-white/10 text-secondary">{{ i.type }}</span>
              </div>
              <p class="text-[11px] text-secondary mt-1 truncate">{{ i.restaurant }}</p>
              <p class="text-[10px] text-secondary">{{ i.district || 'no district' }}, {{ i.state || 'no state' }}</p>
            </div>
            <div class="text-right">
              <p class="font-bold text-primary">₹{{ Number(i.total_amount).toLocaleString('en-IN') }}</p>
              <p class="text-[10px] text-secondary">{{ i.status }}</p>
            </div>
            <button class="bg-primary text-black font-bold px-4 py-2 rounded-lg text-sm hover:opacity-90 disabled:opacity-40" @click="originateInvoice(i)" :disabled="busy === i.id">
              {{ busy === i.id ? 'Routing…' : 'Route for Approval' }}
            </button>
          </div>
        </div>
      </section>

      <section>
        <h2 class="font-bold text-primary text-sm uppercase tracking-wide mb-3">Pending Recoveries</h2>
        <div v-if="loadingRecoveries" class="text-secondary text-sm py-6 text-center">Loading recoveries…</div>
        <div v-else-if="recoveries.length === 0" class="text-secondary text-sm py-6 text-center border border-white/10 rounded-2xl">No pending recoveries.</div>
        <div class="space-y-3">
          <div v-for="r in recoveries" :key="r.id" class="rounded-2xl border border-white/10 bg-surface/50 p-4 flex items-center gap-4">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <span class="font-bold text-primary text-sm">{{ r.reference || 'Recovery' }}</span>
                <span class="text-[10px] px-2 py-0.5 rounded-full bg-white/10 text-secondary">{{ r.status }}</span>
              </div>
              <p class="text-[11px] text-secondary mt-1 truncate">{{ r.restaurant }}</p>
              <p class="text-[10px] text-secondary">{{ r.district || 'no district' }}, {{ r.state || 'no state' }}</p>
            </div>
            <div class="text-right">
              <p class="font-bold text-primary">₹{{ Number(r.amount).toLocaleString('en-IN') }}</p>
              <p class="text-[10px] text-secondary">{{ new Date(r.created_at).toLocaleDateString() }}</p>
            </div>
            <button class="bg-primary text-black font-bold px-4 py-2 rounded-lg text-sm hover:opacity-90 disabled:opacity-40" @click="originateRecovery(r)" :disabled="busy === r.id">
              {{ busy === r.id ? 'Routing…' : 'Route for Approval' }}
            </button>
          </div>
        </div>
      </section>
    </div>

    <div v-if="result" class="mt-6 rounded-2xl border border-emerald-500/30 bg-emerald-500/5 p-4 text-sm">
      <p class="text-emerald-400 font-bold mb-1">✓ Originated at {{ result.origin_org_unit }}</p>
      <p class="text-secondary">Status: {{ result.status_label }} · reviewer: {{ result.reviewer || 'auto-escalating…' }} · SLA due: {{ result.sla_due_at }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getPendingInvoices, initiateInvoiceApproval, getPendingRecoveries, initiateRecoveryApproval } from '../api'

const invoices = ref([])
const recoveries = ref([])
const loadingInvoices = ref(true)
const loadingRecoveries = ref(true)
const busy = ref(null)
const result = ref(null)

const loadInvoices = async () => {
  loadingInvoices.value = true
  try { invoices.value = await getPendingInvoices() }
  catch (e) { alert(e.message || 'Failed to load invoices') }
  finally { loadingInvoices.value = false }
}

const loadRecoveries = async () => {
  loadingRecoveries.value = true
  try { recoveries.value = await getPendingRecoveries() }
  catch (e) { alert(e.message || 'Failed to load recoveries') }
  finally { loadingRecoveries.value = false }
}

const originateInvoice = async (i) => {
  busy.value = i.id
  result.value = null
  try {
    result.value = await initiateInvoiceApproval(i.id, false)
    await loadInvoices()
  } catch (e) {
    alert(e.message || JSON.stringify(e.errors || e))
  } finally {
    busy.value = null
  }
}

const originateRecovery = async (r) => {
  busy.value = r.id
  result.value = null
  try {
    result.value = await initiateRecoveryApproval(r.id, false)
    await loadRecoveries()
  } catch (e) {
    alert(e.message || JSON.stringify(e.errors || e))
  } finally {
    busy.value = null
  }
}

onMounted(() => { loadInvoices(); loadRecoveries() })
</script>
