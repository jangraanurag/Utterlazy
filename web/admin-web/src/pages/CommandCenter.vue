<template>
  <div>
    <h1 class="text-3xl font-serif font-bold text-on-surface mb-2">Command Center</h1>
    <p class="text-on-surface-variant mb-8">Read-only city operations. Per-city order metrics use <code>orders.city_id</code>; unmapped orders appear only in the global row.</p>

    <div v-if="loading" class="py-12 text-center text-on-surface-variant">
      <span class="material-symbols-outlined text-4xl animate-spin mb-4 block">sync</span>
      <p>Loading...</p>
    </div>

    <div v-else-if="error" class="bg-red-500/10 border border-red-500/30 text-red-400 p-4 rounded-xl">{{ error }}</div>

    <div v-else class="space-y-8">
      <section class="grid grid-cols-1 md:grid-cols-3 xl:grid-cols-6 gap-4">
        <div class="bg-surface-container-lowest p-5 rounded-xl border border-outline-variant/30">
          <p class="text-xs uppercase tracking-wider text-on-surface-variant mb-2">Unaccepted &gt; 3 min</p>
          <p class="text-3xl font-bold" :class="data.global.unaccepted_over_3min > 0 ? 'text-red-400' : 'text-green-400'">
            {{ data.global.unaccepted_over_3min }}
          </p>
          <p class="text-[10px] text-on-surface-variant mt-1">Owner: city_ops</p>
        </div>
        <div class="bg-surface-container-lowest p-5 rounded-xl border border-outline-variant/30">
          <p class="text-xs uppercase tracking-wider text-on-surface-variant mb-2">Ready, no rider &gt; 8 min</p>
          <p class="text-3xl font-bold" :class="data.global.ready_no_rider_over_8min > 0 ? 'text-red-400' : 'text-green-400'">
            {{ data.global.ready_no_rider_over_8min }}
          </p>
          <p class="text-[10px] text-on-surface-variant mt-1">Owner: city_ops</p>
        </div>
        <div class="bg-surface-container-lowest p-5 rounded-xl border border-outline-variant/30">
          <p class="text-xs uppercase tracking-wider text-on-surface-variant mb-2">Prepaid stuck &gt; 30 min</p>
          <p class="text-3xl font-bold" :class="data.global.prepaid_payment_stuck_over_30min > 0 ? 'text-amber-400' : 'text-green-400'">
            {{ data.global.prepaid_payment_stuck_over_30min }}
          </p>
          <p class="text-[10px] text-on-surface-variant mt-1">Owner: support_lead</p>
        </div>
        <div class="bg-surface-container-lowest p-5 rounded-xl border border-outline-variant/30">
          <p class="text-xs uppercase tracking-wider text-on-surface-variant mb-2">COD outstanding</p>
          <p class="text-3xl font-bold" :class="data.global.cod_outstanding_amount > 0 ? 'text-amber-400' : 'text-green-400'">
            ₹{{ Number(data.global.cod_outstanding_amount || 0).toFixed(0) }}
          </p>
          <p class="text-[10px] text-on-surface-variant mt-1">Owner: rider_ops</p>
        </div>
        <div class="bg-surface-container-lowest p-5 rounded-xl border border-outline-variant/30">
          <p class="text-xs uppercase tracking-wider text-on-surface-variant mb-2">Order projection</p>
          <p class="text-3xl font-bold" :class="(data.projection?.last_24h || 0) > 0 ? 'text-green-400' : 'text-amber-400'">
            {{ data.projection?.last_24h || 0 }}
          </p>
          <p class="text-[10px] text-on-surface-variant mt-1">
            last 24h · {{ data.projection?.total || 0 }} total
          </p>
        </div>
        <div class="bg-surface-container-lowest p-5 rounded-xl border border-outline-variant/30">
          <p class="text-xs uppercase tracking-wider text-on-surface-variant mb-2">Cutover gate</p>
          <p class="text-3xl font-bold" :class="data.cutover?.allowed ? 'text-green-400' : 'text-amber-400'">
            {{ data.cutover?.allowed ? 'READY' : 'BLOCKED' }}
          </p>
          <p class="text-[10px] text-on-surface-variant mt-1">
            {{ data.cutover?.blockers?.length || 0 }} blocker(s) · Owner: CTO
          </p>
        </div>
        <div class="bg-surface-container-lowest p-5 rounded-xl border border-outline-variant/30">
          <p class="text-xs uppercase tracking-wider text-on-surface-variant mb-2">Open SOS</p>
          <p class="text-3xl font-bold" :class="data.global.open_sos > 0 ? 'text-red-400' : 'text-green-400'">
            {{ data.global.open_sos }}
          </p>
          <p class="text-[10px] text-on-surface-variant mt-1">Owner: rider_ops · SEV1 auto-incident</p>
        </div>
        <div class="bg-surface-container-lowest p-5 rounded-xl border border-outline-variant/30">
          <p class="text-xs uppercase tracking-wider text-on-surface-variant mb-2">Agents on shift</p>
          <p class="text-3xl font-bold" :class="data.global.on_shift_agents > 0 ? 'text-green-400' : 'text-amber-400'">
            {{ data.global.on_shift_agents }}
          </p>
          <p class="text-[10px] text-on-surface-variant mt-1">Coverage now · Owner: support_lead</p>
        </div>
        <div class="bg-surface-container-lowest p-5 rounded-xl border border-outline-variant/30">
          <p class="text-xs uppercase tracking-wider text-on-surface-variant mb-2">Open incidents</p>
          <p class="text-3xl font-bold" :class="data.global.open_incidents > 0 ? 'text-amber-400' : 'text-green-400'">
            {{ data.global.open_incidents }}
          </p>
          <p class="text-[10px] text-on-surface-variant mt-1">
            SEV1: {{ data.global.sev1_incidents }} · SLA breaches: {{ data.global.incident_sla_breaches }}
          </p>
        </div>
        <div class="bg-surface-container-lowest p-5 rounded-xl border border-outline-variant/30">
          <p class="text-xs uppercase tracking-wider text-on-surface-variant mb-2">Pricing parity (24h)</p>
          <p class="text-3xl font-bold"
            :class="data.global.pricing_parity_mismatches_24h > 0 ? 'text-red-400' : 'text-green-400'">
            {{ data.global.pricing_parity_mismatch_rate }}%
          </p>
          <p class="text-[10px] text-on-surface-variant mt-1">
            {{ data.global.pricing_parity_mismatches_24h }} / {{ data.global.pricing_parity_checks_24h }} checks · Owner: finance/eng
          </p>
        </div>
        <div class="bg-surface-container-lowest p-5 rounded-xl border border-outline-variant/30">
          <p class="text-xs uppercase tracking-wider text-on-surface-variant mb-2">Refunds pending</p>
          <p class="text-3xl font-bold" :class="data.global.refund_pending > 0 ? 'text-red-400' : 'text-green-400'">
            {{ data.global.refund_pending }}
          </p>
          <p class="text-[10px] text-on-surface-variant mt-1">
            oldest: {{ data.global.oldest_refund_pending_minutes ?? 0 }}m · Owner: finance
          </p>
        </div>
        <div class="bg-surface-container-lowest p-5 rounded-xl border border-outline-variant/30">
          <p class="text-xs uppercase tracking-wider text-on-surface-variant mb-2">Outbox pending</p>
          <p class="text-3xl font-bold" :class="data.event_bus.outbox_pending > 100 ? 'text-red-400' : 'text-green-400'">
            {{ data.event_bus.outbox_pending }}
          </p>
          <p class="text-[10px] text-on-surface-variant mt-1">
            oldest: {{ data.event_bus.oldest_pending_age_seconds ?? 0 }}s
          </p>
        </div>
      </section>

      <section class="bg-surface-container-lowest rounded-xl border border-outline-variant/30 overflow-hidden">
        <div class="p-5 border-b border-outline-variant/30 flex items-center justify-between">
          <h2 class="text-lg font-bold">City operations</h2>
          <span class="text-[10px] uppercase tracking-wider text-on-surface-variant">
            Daily: {{ data.metrics_source }} · Paid inventory only
          </span>
        </div>
        <table class="w-full text-sm">
          <thead class="bg-surface-container text-on-surface-variant text-xs uppercase tracking-wider">
            <tr>
              <th class="text-left px-5 py-3">City</th>
              <th class="text-right px-5 py-3">Today orders</th>
              <th class="text-right px-5 py-3">Today GMV</th>
              <th class="text-right px-5 py-3">Unaccepted &gt;3m</th>
              <th class="text-right px-5 py-3">Ready, no rider &gt;8m</th>
              <th class="text-right px-5 py-3">COD outstanding</th>
              <th class="text-right px-5 py-3">Platform cancels 24h</th>
              <th class="text-right px-5 py-3">Active placements</th>
              <th class="text-right px-5 py-3">Floor CPC</th>
              <th class="text-right px-5 py-3">Max slots</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="city in data.cities" :key="city.city_id" class="border-t border-outline-variant/20">
              <td class="px-5 py-3 font-bold">
                {{ city.name }}
                <p class="text-[10px] text-on-surface-variant font-normal">{{ city.wa_exhausted_today }} exhausted placement(s)</p>
              </td>
              <td class="px-5 py-3 text-right">{{ city.today_orders }}</td>
              <td class="px-5 py-3 text-right font-mono">₹{{ Number(city.today_gmv || 0).toFixed(0) }}</td>
              <td class="px-5 py-3 text-right" :class="city.orders_unaccepted_over_3min > 0 ? 'text-red-400' : ''">{{ city.orders_unaccepted_over_3min }}</td>
              <td class="px-5 py-3 text-right" :class="city.orders_ready_no_rider_over_8min > 0 ? 'text-red-400' : ''">{{ city.orders_ready_no_rider_over_8min }}</td>
              <td class="px-5 py-3 text-right" :class="city.orders_cod_outstanding > 0 ? 'text-amber-400' : ''">₹{{ Number(city.orders_cod_outstanding || 0).toFixed(0) }}</td>
              <td class="px-5 py-3 text-right">{{ city.orders_platform_cancels_24h }}</td>
              <td class="px-5 py-3 text-right">{{ city.wa_active_placements }}</td>
              <td class="px-5 py-3 text-right font-mono">₹{{ Number(city.wa_floor_cpc).toFixed(2) }}</td>
              <td class="px-5 py-3 text-right">{{ city.wa_max_slots }}</td>
            </tr>
            <tr v-if="!data.cities.length">
              <td colspan="10" class="px-5 py-8 text-center text-on-surface-variant">No active cities.</td>
            </tr>
          </tbody>
        </table>
      </section>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { getCommandCenter } from '../api'

const data = ref(null)
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  try {
    data.value = await getCommandCenter()
  } catch (e) {
    error.value = e.message || 'Failed to load command center'
  } finally {
    loading.value = false
  }
})
</script>
