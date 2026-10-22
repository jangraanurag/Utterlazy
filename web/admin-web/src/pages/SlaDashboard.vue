<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="font-display font-bold text-2xl text-primary uppercase tracking-wide">SLA Watch</h1>
        <p class="text-sm text-secondary mt-1">Items at risk of or past their approval SLA — auto-escalation runs every 15 min (§5.3)</p>
      </div>
      <button @click="load" class="bg-primary text-black font-bold px-4 py-2 rounded-lg text-sm hover:opacity-90">↻ Refresh</button>
    </div>

    <div class="grid grid-cols-3 gap-4 mb-6">
      <div class="rounded-2xl border border-red-500/30 bg-red-500/5 p-4">
        <p class="text-[11px] uppercase tracking-wider text-red-400 font-bold">Breached</p>
        <p class="text-3xl font-bold text-red-400 mt-1">{{ stats.breached }}</p>
      </div>
      <div class="rounded-2xl border border-amber-500/30 bg-amber-500/5 p-4">
        <p class="text-[11px] uppercase tracking-wider text-amber-400 font-bold">At risk (&lt; 6h)</p>
        <p class="text-3xl font-bold text-amber-400 mt-1">{{ stats.atRisk }}</p>
      </div>
      <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/5 p-4">
        <p class="text-[11px] uppercase tracking-wider text-emerald-400 font-bold">On track</p>
        <p class="text-3xl font-bold text-emerald-400 mt-1">{{ stats.onTrack }}</p>
      </div>
    </div>

    <div class="flex gap-4 mb-6">
      <select v-model="department" @change="load" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:border-primary w-48">
        <option value="">All departments</option>
        <option v-for="d in DEPARTMENTS" :key="d" :value="d">{{ d }}</option>
      </select>
      <label class="flex items-center gap-2 text-sm text-secondary cursor-pointer">
        <input v-model="breachedOnly" type="checkbox" class="accent-red-500" @change="load" />
        Breached only
      </label>
    </div>

    <div v-if="loading" class="text-secondary text-sm py-10 text-center">Loading…</div>
    <div v-else-if="items.length === 0" class="text-secondary text-sm py-10 text-center">All clear — no items in flight.</div>

    <div class="space-y-3">
      <div v-for="item in items" :key="item.id"
           class="rounded-2xl border p-4 flex items-center gap-4"
           :class="item.breached ? 'border-red-500/40 bg-red-500/5' : 'border-white/10 bg-surface/50'">
        <span class="material-symbols-outlined" :class="item.breached ? 'text-red-400' : 'text-amber-400'">{{ item.breached ? 'warning' : 'timer' }}</span>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <span class="font-bold text-sm text-primary truncate">{{ item.entity_summary?.label || item.entity_type }}</span>
            <span class="text-[10px] px-2 py-0.5 rounded-full bg-white/10 text-secondary">{{ item.current_org_unit }}</span>
          </div>
          <div class="text-[11px] text-secondary mt-1">
            {{ item.department }} · {{ item.status_label }} · reviewer: {{ item.reviewer || 'vacant' }}
          </div>
        </div>
        <div class="text-right shrink-0">
          <p :class="item.breached ? 'text-red-400 font-bold' : 'text-amber-400 font-bold'" class="text-sm">
            {{ item.breached ? 'BREACHED' : `${item.minutes_remaining}m left` }}
          </p>
          <p class="text-[10px] text-secondary">due {{ fmt(item.sla_due_at) }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { getSlaDashboard } from '../api'

const DEPARTMENTS = ['finance', 'account_management', 'rider_ops', 'support']
const items = ref([])
const loading = ref(true)
const department = ref('')
const breachedOnly = ref(false)

const stats = computed(() => ({
  breached: items.value.filter(i => i.breached).length,
  atRisk: items.value.filter(i => !i.breached && i.minutes_remaining < 360).length,
  onTrack: items.value.filter(i => !i.breached && i.minutes_remaining >= 360).length,
}))

const load = async () => {
  loading.value = true
  try { items.value = (await getSlaDashboard({ department: department.value, breached_only: breachedOnly.value })).items || [] }
  catch (e) { alert(e.message || 'Failed to load') }
  finally { loading.value = false }
}

const fmt = (iso) => iso ? new Date(iso).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' }) : '—'

onMounted(load)
</script>

