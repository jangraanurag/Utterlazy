<template>
  <div>
    <h1 class="text-3xl font-serif font-bold text-on-surface mb-2">PII Audit</h1>
    <p class="text-on-surface-variant mb-6">
      Append-only record of who viewed or exported customer, rider, and restaurant personal data.
      Auditor role is read-only.
    </p>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8" v-if="summary">
      <div class="bg-surface-container-lowest p-5 rounded-xl border border-outline-variant/30">
        <p class="text-xs uppercase tracking-wider text-on-surface-variant mb-2">Accesses 24h</p>
        <p class="text-3xl font-bold">{{ summary.last_24h }}</p>
      </div>
      <div class="bg-surface-container-lowest p-5 rounded-xl border border-outline-variant/30">
        <p class="text-xs uppercase tracking-wider text-on-surface-variant mb-2">Exports 24h</p>
        <p class="text-3xl font-bold" :class="summary.exports_24h > 0 ? 'text-amber-400' : 'text-green-400'">{{ summary.exports_24h }}</p>
      </div>
      <div class="bg-surface-container-lowest p-5 rounded-xl border border-outline-variant/30">
        <p class="text-xs uppercase tracking-wider text-on-surface-variant mb-2">Views 24h</p>
        <p class="text-3xl font-bold">{{ summary.views_24h }}</p>
      </div>
      <div class="bg-surface-container-lowest p-5 rounded-xl border border-outline-variant/30">
        <p class="text-xs uppercase tracking-wider text-on-surface-variant mb-2">Distinct actors</p>
        <p class="text-3xl font-bold">{{ summary.actors_24h }}</p>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-3 mb-4">
      <select v-model="filters.action" @change="load" class="input w-40">
        <option value="">All actions</option>
        <option value="export">Export</option>
        <option value="view">View</option>
        <option value="download">Download</option>
      </select>
      <input v-model="filters.resource_type" @keyup.enter="load" placeholder="resource type" class="input w-48" />
      <input v-model="filters.from" type="date" class="input w-44" />
      <input v-model="filters.to" type="date" class="input w-44" />
      <button @click="load" class="px-4 py-2 rounded-lg bg-surface-container border border-outline-variant/50 text-sm font-bold">Filter</button>
      <span v-if="error" class="text-sm text-red-400">{{ error }}</span>
    </div>

    <div v-if="loading" class="py-12 text-center text-on-surface-variant">Loading...</div>

    <div v-else class="bg-surface-container-lowest rounded-xl border border-outline-variant/30 overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-surface-container text-on-surface-variant text-xs uppercase tracking-wider">
          <tr>
            <th class="text-left px-4 py-3">When</th>
            <th class="text-left px-4 py-3">Actor</th>
            <th class="text-left px-4 py-3">Role</th>
            <th class="text-left px-4 py-3">Action</th>
            <th class="text-left px-4 py-3">Resource</th>
            <th class="text-right px-4 py-3">Rows</th>
            <th class="text-left px-4 py-3">Filters / purpose</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="log in logs" :key="log.id" class="border-t border-outline-variant/20">
            <td class="px-4 py-3 text-xs whitespace-nowrap">{{ formatTime(log.created_at) }}</td>
            <td class="px-4 py-3">
              <p class="font-bold">{{ log.actor?.name || log.actor_user_id }}</p>
              <p class="text-[10px] text-on-surface-variant">{{ log.ip_address || '—' }}</p>
            </td>
            <td class="px-4 py-3 text-xs uppercase tracking-wider">{{ log.actor_role || '—' }}</td>
            <td class="px-4 py-3">
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                :class="log.action === 'export' ? 'bg-amber-500/20 text-amber-300' : 'bg-surface-container text-on-surface-variant'">
                {{ log.action }}
              </span>
            </td>
            <td class="px-4 py-3 text-xs">
              {{ log.resource_type }}
              <span v-if="log.resource_id" class="font-mono text-[10px] text-on-surface-variant">#{{ String(log.resource_id).slice(0, 8) }}</span>
            </td>
            <td class="px-4 py-3 text-right font-mono">{{ log.row_count ?? '—' }}</td>
            <td class="px-4 py-3 text-[11px] text-on-surface-variant max-w-[320px] truncate"
              :title="JSON.stringify(log.filters)">
              {{ log.purpose || '' }} {{ log.filters ? JSON.stringify(log.filters) : '' }}
            </td>
          </tr>
          <tr v-if="!logs.length">
            <td colspan="7" class="px-4 py-10 text-center text-on-surface-variant">No audit rows.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <section class="mt-10">
      <h2 class="text-xl font-bold mb-2">Retention runs</h2>
      <p class="text-xs text-on-surface-variant mb-4">
        Manual, audited purges of PII access logs. Dry-runs are recorded too; minimum window 30 days.
      </p>
      <div class="bg-surface-container-lowest rounded-xl border border-outline-variant/30 overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-surface-container text-on-surface-variant text-xs uppercase tracking-wider">
            <tr>
              <th class="text-left px-4 py-3">When</th>
              <th class="text-left px-4 py-3">Actor</th>
              <th class="text-left px-4 py-3">Mode</th>
              <th class="text-left px-4 py-3">Cutoff</th>
              <th class="text-right px-4 py-3">Deleted</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="run in retentionRuns" :key="run.id" class="border-t border-outline-variant/20">
              <td class="px-4 py-3 text-xs">{{ formatTime(run.created_at) }}</td>
              <td class="px-4 py-3 text-xs">{{ run.actor?.name || run.actor_user_id || 'CLI' }}</td>
              <td class="px-4 py-3">
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                  :class="run.dry_run ? 'bg-surface-container text-on-surface-variant' : 'bg-amber-500/20 text-amber-300'">
                  {{ run.dry_run ? 'dry-run' : 'applied' }}
                </span>
              </td>
              <td class="px-4 py-3 text-xs">{{ formatTime(run.cutoff) }}</td>
              <td class="px-4 py-3 text-right font-mono">{{ run.rows_deleted }}</td>
            </tr>
            <tr v-if="!retentionRuns.length">
              <td colspan="5" class="px-4 py-8 text-center text-on-surface-variant">No retention runs.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { getPiiAudit, getPiiRetentionRuns } from '../api'

const logs = ref([])
const summary = ref(null)
const loading = ref(true)
const error = ref('')
const filters = reactive({ action: '', resource_type: '', from: '', to: '' })
const retentionRuns = ref([])

const formatTime = (value) => new Date(value).toLocaleString('en-IN', { dateStyle: 'short', timeStyle: 'short' })

async function load() {
  loading.value = true
  error.value = ''
  try {
    const res = await getPiiAudit({
      action: filters.action,
      resource_type: filters.resource_type,
      from: filters.from ? `${filters.from} 00:00:00` : '',
      to: filters.to ? `${filters.to} 23:59:59` : '',
    })
    logs.value = res.logs?.data || []
    summary.value = res.summary || null
    try { retentionRuns.value = (await getPiiRetentionRuns()).runs || [] } catch { retentionRuns.value = [] }
  } catch (e) {
    error.value = e.message || 'Failed to load audit log'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<style scoped>
.input {
  border: 1px solid rgba(148, 163, 184, 0.35);
  border-radius: 0.5rem;
  padding: 0.5rem 0.75rem;
  font-size: 0.875rem;
  background: transparent;
}
</style>
