<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="font-display font-bold text-2xl text-primary uppercase tracking-wide">Delegation of Authority Matrix</h1>
        <p class="text-sm text-secondary mt-1">Executable approval thresholds per department / unit type / entity — changes apply to new items only (§4, §10)</p>
      </div>
      <button @click="openModal()" class="bg-primary text-black font-bold px-4 py-2 rounded-lg text-sm hover:opacity-90">+ New Threshold Rule</button>
    </div>

    <div class="flex gap-4 mb-6">
      <select v-model="department" @change="load" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:border-primary">
        <option value="">All departments</option>
        <option v-for="d in DEPARTMENTS" :key="d" :value="d">{{ d }}</option>
      </select>
      <select v-model="entityType" @change="load" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:border-primary">
        <option value="">All entity types</option>
        <option v-for="e in ENTITY_TYPES" :key="e" :value="e">{{ e }}</option>
      </select>
    </div>

    <div class="rounded-2xl border border-white/10 overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-white/5 text-secondary text-[11px] uppercase tracking-wider">
            <th class="text-left px-4 py-3">Department</th>
            <th class="text-left px-4 py-3">Unit type</th>
            <th class="text-left px-4 py-3">Entity</th>
            <th class="text-right px-4 py-3">Max amount (₹)</th>
            <th class="text-right px-4 py-3">SLA (hrs)</th>
            <th class="text-left px-4 py-3">Geography scope</th>
            <th class="text-left px-4 py-3">Status</th>
            <th class="text-right px-4 py-3">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="8" class="px-4 py-8 text-center text-secondary">Loading…</td></tr>
          <tr v-else-if="thresholds.length === 0"><td colspan="8" class="px-4 py-8 text-center text-secondary">No rules yet. Create one to start enforcing thresholds.</td></tr>
          <tr v-for="t in thresholds" :key="t.id" class="border-t border-white/5 hover:bg-white/5">
            <td class="px-4 py-3 text-primary capitalize">{{ t.department }}</td>
            <td class="px-4 py-3 text-primary capitalize">{{ t.unit_type }}</td>
            <td class="px-4 py-3 text-primary">{{ t.entity_type }}</td>
            <td class="px-4 py-3 text-right font-bold text-primary">{{ t.max_amount === null ? 'Unlimited' : '₹' + Number(t.max_amount).toLocaleString('en-IN') }}</td>
            <td class="px-4 py-3 text-right">{{ t.sla_hours }}</td>
            <td class="px-4 py-3 text-secondary text-xs">{{ scopeLabel(t.geography_scope) }}</td>
            <td class="px-4 py-3">
              <span :class="t.is_active ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'" class="text-[10px] px-2 py-0.5 rounded-full uppercase">{{ t.is_active ? 'Active' : 'Disabled' }}</span>
            </td>
            <td class="px-4 py-3 text-right">
              <button @click="openModal(t)" class="text-primary text-xs mr-3">Edit</button>
              <button @click="remove(t)" class="text-red-400 text-xs">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div class="mt-8">
      <button @click="toggleAudit" class="text-primary text-sm font-bold uppercase tracking-wider">
        {{ showAudit ? '▾ Hide change audit trail' : '▸ Show change audit trail (§8)' }}
      </button>
      <div v-if="showAudit" class="mt-3 rounded-2xl border border-white/10 overflow-hidden">
        <div v-if="auditLoading" class="px-4 py-6 text-center text-secondary text-sm">Loading…</div>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="bg-white/5 text-secondary text-[11px] uppercase tracking-wider">
              <th class="text-left px-4 py-3">When</th>
              <th class="text-left px-4 py-3">Admin</th>
              <th class="text-left px-4 py-3">Action</th>
              <th class="text-left px-4 py-3">Rule</th>
              <th class="text-left px-4 py-3">Change</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="audit.length === 0"><td colspan="5" class="px-4 py-6 text-center text-secondary">No changes recorded yet.</td></tr>
            <tr v-for="l in audit" :key="l.id" class="border-t border-white/5">
              <td class="px-4 py-3 text-secondary text-xs whitespace-nowrap">{{ fmt(l.created_at) }}</td>
              <td class="px-4 py-3 text-primary text-xs">{{ l.admin_role }}</td>
              <td class="px-4 py-3 text-xs uppercase">{{ actionLabel(l.action) }}</td>
              <td class="px-4 py-3 text-xs">{{ ruleLabel(l) }}</td>
              <td class="px-4 py-3 text-xs text-secondary">{{ diffLabel(l) }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="modal" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50" @click.self="modal = false">
      <div class="bg-surface rounded-2xl p-6 w-full max-w-md">
        <h2 class="font-display font-bold text-lg text-primary uppercase mb-4">{{ form.id ? 'Edit Threshold Rule' : 'New Threshold Rule' }}</h2>
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-[11px] uppercase tracking-wider text-white/60 font-bold mb-1">Department</label>
              <select v-model="form.department" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:border-primary" :disabled="!!form.id">
                <option v-for="d in DEPARTMENTS" :key="d" :value="d">{{ d }}</option>
              </select>
            </div>
            <div>
              <label class="block text-[11px] uppercase tracking-wider text-white/60 font-bold mb-1">Unit type</label>
              <select v-model="form.unit_type" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:border-primary" :disabled="!!form.id">
                <option v-for="t in UNIT_TYPES" :key="t" :value="t">{{ t }}</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-[11px] uppercase tracking-wider text-white/60 font-bold mb-1">Entity type</label>
            <select v-model="form.entity_type" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:border-primary" :disabled="!!form.id">
              <option v-for="e in ENTITY_TYPES" :key="e" :value="e">{{ e }}</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-[11px] uppercase tracking-wider text-white/60 font-bold mb-1">Max amount (₹)</label>
              <input v-model="form.max_amount" type="number" min="0" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:border-primary" placeholder="Leave empty = unlimited (top)" />
            </div>
            <div>
              <label class="block text-[11px] uppercase tracking-wider text-white/60 font-bold mb-1">SLA (hours)</label>
              <input v-model="form.sla_hours" type="number" min="1" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:border-primary" />
            </div>
          </div>
          <div>
            <label class="block text-[11px] uppercase tracking-wider text-white/60 font-bold mb-1">Geography scope (optional — city/area-specific rules)</label>
            <div v-for="(row, i) in form.geography_scope" :key="i" class="flex gap-2 mb-2">
              <input v-model="row.state" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:border-primary flex-1" placeholder="State" />
              <input v-model="row.district" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:border-primary flex-1" placeholder="District" />
              <button @click="form.geography_scope.splice(i, 1)" class="text-red-400 px-2">✕</button>
            </div>
            <button @click="form.geography_scope.push({ state: '', district: '' })" class="text-sm text-primary">+ Add scope row</button>
          </div>
          <div class="flex items-center gap-3">
            <input v-model="form.is_active" type="checkbox" id="thActive" class="accent-primary" />
            <label for="thActive" class="text-sm text-primary">Active</label>
          </div>
        </div>
        <div class="flex justify-end gap-3 mt-6">
          <button @click="modal = false" class="border border-white/15 text-white/60 px-4 py-2 rounded-lg text-sm hover:text-white">Cancel</button>
          <button @click="save" class="bg-primary text-black font-bold px-4 py-2 rounded-lg text-sm hover:opacity-90">{{ form.id ? 'Save' : 'Create' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getApprovalThresholds, createApprovalThreshold, updateApprovalThreshold, deleteApprovalThreshold, getApprovalThresholdAudit } from '../api'

const DEPARTMENTS = ['finance', 'account_management', 'rider_ops', 'support']
const UNIT_TYPES = ['national', 'state', 'district', 'region', 'city']
const ENTITY_TYPES = ['vendor_payout', 'rider_payout', 'sponsored_listing', 'offer', 'complaint_refund']

const thresholds = ref([])
const loading = ref(true)
const department = ref('')
const entityType = ref('')
const modal = ref(false)
const form = ref({})
const showAudit = ref(false)
const auditLoading = ref(false)
const audit = ref([])

const fmt = (d) => d ? new Date(d).toLocaleString() : '—'
const actionLabel = (a) => a.replace('threshold_', '').replace('_', ' ')
const ruleLabel = (l) => {
  const n = l.new_values || l.old_values || {}
  return [n.department, n.unit_type, n.entity_type].filter(Boolean).join(' / ') || l.entity_id
}
const diffLabel = (l) => {
  if (l.action === 'threshold_deleted') return 'rule deleted'
  const n = l.new_values || {}
  const o = l.old_values || {}
  const parts = []
  if (String(n.max_amount) !== String(o.max_amount)) parts.push(`max: ${o.max_amount ?? '∞'} → ${n.max_amount ?? '∞'}`)
  if (String(n.sla_hours) !== String(o.sla_hours)) parts.push(`sla: ${o.sla_hours ?? '—'} → ${n.sla_hours ?? '—'}`)
  if (String(n.is_active) !== String(o.is_active)) parts.push(`status: ${o.is_active ? 'active' : 'disabled'} → ${n.is_active ? 'active' : 'disabled'}`)
  return parts.join(', ') || 'created'
}

const toggleAudit = async () => {
  showAudit.value = !showAudit.value
  if (showAudit.value && audit.value.length === 0) {
    auditLoading.value = true
    try { audit.value = await getApprovalThresholdAudit() }
    catch (e) { alert(e.message || 'Failed to load audit') }
    finally { auditLoading.value = false }
  }
}

const scopeLabel = (scope) => {
  if (!scope || !scope.length) return '—'
  return scope.map(s => [s.state, s.district].filter(Boolean).join(' / ')).filter(Boolean).join(' · ')
}

const load = async () => {
  loading.value = true
  try { thresholds.value = await getApprovalThresholds({ department: department.value, entity_type: entityType.value }) }
  catch (e) { alert(e.message || 'Failed to load') }
  finally { loading.value = false }
}

const openModal = (t = null) => {
  modal.value = true
  form.value = t ? {
    id: t.id, department: t.department, unit_type: t.unit_type, entity_type: t.entity_type,
    max_amount: t.max_amount, sla_hours: t.sla_hours,
    geography_scope: (t.geography_scope || []).map(s => ({ ...s })),
    is_active: t.is_active,
  } : {
    id: null, department: 'finance', unit_type: 'district', entity_type: 'vendor_payout',
    max_amount: null, sla_hours: 24, geography_scope: [], is_active: true,
  }
}

const save = async () => {
  try {
    const payload = {
      ...form.value,
      max_amount: form.value.max_amount === '' || form.value.max_amount === null ? null : Number(form.value.max_amount),
      sla_hours: Number(form.value.sla_hours) || 24,
      geography_scope: form.value.geography_scope.filter(r => r.state || r.district),
    }
    if (form.value.id) await updateApprovalThreshold(form.value.id, payload)
    else await createApprovalThreshold(payload)
    modal.value = false
    await load()
  } catch (e) { alert(e.message || JSON.stringify(e.errors || e)) }
}

const remove = async (t) => {
  if (!confirm(`Delete rule: ${t.department} / ${t.unit_type} / ${t.entity_type}?`)) return
  try { await deleteApprovalThreshold(t.id); await load() }
  catch (e) { alert(e.message || 'Failed to delete') }
}

onMounted(load)
</script>

