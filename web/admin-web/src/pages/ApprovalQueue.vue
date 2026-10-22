<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="font-display font-bold text-2xl text-primary uppercase tracking-wide">My Approval Queue</h1>
        <p class="text-sm text-secondary mt-1">Items awaiting your action — assigned role or backup (§5)</p>
      </div>
      <button @click="load" class="bg-primary text-black font-bold px-4 py-2 rounded-lg text-sm hover:opacity-90">↻ Refresh</button>
    </div>

    <div class="flex gap-4 mb-6 flex-wrap">
      <select v-model="filters.status" @change="load" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:border-primary w-44">
        <option value="">All statuses</option>
        <option value="under_review">Under review</option>
        <option value="sent_back">Sent back</option>
      </select>
      <select v-model="filters.department" @change="load" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:border-primary w-44">
        <option value="">All departments</option>
        <option v-for="d in DEPARTMENTS" :key="d" :value="d">{{ d }}</option>
      </select>
      <select v-model="filters.entity_type" @change="load" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:border-primary w-52">
        <option value="">All entity types</option>
        <option v-for="e in ENTITY_TYPES" :key="e" :value="e">{{ e }}</option>
      </select>
    </div>

    <div v-if="loading" class="text-secondary text-sm py-10 text-center">Loading queue…</div>
    <div v-else-if="items.length === 0" class="text-secondary text-sm py-10 text-center">Nothing awaiting your action. 🎉</div>

    <div class="space-y-3">
      <div v-for="item in items" :key="item.id" class="rounded-2xl border border-white/10 bg-surface/50 p-4 hover:border-white/20 transition-colors">
        <div class="flex items-start gap-4">
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="font-bold text-primary text-sm">{{ item.entity_summary?.label || item.entity_type }}</span>
              <span :class="statusBadge(item)" class="text-[10px] px-2 py-0.5 rounded-full uppercase">{{ item.status_label }}</span>
              <span v-if="item.is_exception" class="text-[10px] px-2 py-0.5 rounded-full bg-red-500/20 text-red-400 uppercase">exception</span>
              <span v-if="item.is_vacant" class="text-[10px] px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-400 uppercase">vacant role</span>
            </div>
            <div class="text-[11px] text-secondary mt-1.5 space-x-3">
              <span>{{ item.department }}</span>
              <span>·</span>
              <span>{{ item.current_org_unit }}</span>
              <span>·</span>
              <span>reviewer: <b class="text-primary">{{ item.reviewer || 'vacant' }}</b></span>
            </div>
            <div class="flex items-center gap-4 mt-2 text-xs">
              <span v-if="item.amount !== null" class="font-bold text-primary">₹{{ Number(item.amount).toLocaleString('en-IN') }}</span>
              <span v-if="item.max_amount !== null" class="text-secondary">limit: ₹{{ Number(item.max_amount).toLocaleString('en-IN') }}</span>
              <span class="text-secondary">waiting since {{ timeAgo(item.created_at) }}</span>
              <span v-if="item.sla_due_at" :class="item.sla_breached ? 'text-red-400 font-bold' : 'text-secondary'">
                SLA {{ item.sla_breached ? 'BREACHED' : 'due ' + timeAgo(item.sla_due_at) }}
              </span>
            </div>
          </div>
          <div class="flex gap-2 shrink-0">
            <button v-if="item.status !== 'sent_back'" class="px-3 py-1.5 rounded-lg text-xs font-semibold hover:opacity-80 whitespace-nowrap bg-emerald-500/20 text-emerald-400" title="Approve (within threshold)" @click="act(item, 'approve')">Approve</button>
            <button v-if="item.status !== 'sent_back'" class="px-3 py-1.5 rounded-lg text-xs font-semibold hover:opacity-80 whitespace-nowrap bg-blue-500/20 text-blue-400" title="Escalate to parent unit" @click="act(item, 'escalate')">Escalate</button>
            <button class="px-3 py-1.5 rounded-lg text-xs font-semibold hover:opacity-80 whitespace-nowrap bg-amber-500/20 text-amber-400" title="Send back for clarification" @click="openSendBack(item)">Send Back</button>
            <button v-if="item.status === 'sent_back'" class="px-3 py-1.5 rounded-lg text-xs font-semibold hover:opacity-80 whitespace-nowrap bg-emerald-500/20 text-emerald-400" @click="act(item, 'resubmit')">Resubmit</button>
            <button class="px-3 py-1.5 rounded-lg text-xs font-semibold hover:opacity-80 whitespace-nowrap bg-red-500/20 text-red-400" @click="act(item, 'reject')">Reject</button>
            <button class="px-3 py-1.5 rounded-lg text-xs font-semibold hover:opacity-80 whitespace-nowrap bg-white/10 text-secondary" title="View journey" @click="openDetail(item)">Details</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Send-back modal -->
    <div v-if="sendBackItem" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50" @click.self="sendBackItem = null">
      <div class="bg-surface rounded-2xl p-6 w-full max-w-md">
        <h2 class="font-display font-bold text-lg text-primary uppercase mb-2">Send back for clarification</h2>
        <p class="text-xs text-secondary mb-4">Target can be <b>any</b> lower unit in this item's chain, not just one level down (§5.2).</p>
        <div class="space-y-4">
          <div>
            <label class="block text-[11px] uppercase tracking-wider text-white/60 font-bold mb-1">Target unit</label>
            <select v-model="sendBackTarget" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:border-primary">
              <option value="">Select unit…</option>
              <option v-for="u in chainOptions" :key="u.id" :value="u.id">{{ u.name }} ({{ u.unit_type }})</option>
            </select>
          </div>
          <div>
            <label class="block text-[11px] uppercase tracking-wider text-white/60 font-bold mb-1">Comment (required)</label>
            <textarea v-model="sendBackComment" rows="3" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:border-primary" placeholder="What needs clarification?"></textarea>
          </div>
        </div>
        <div class="flex justify-end gap-3 mt-6">
          <button @click="sendBackItem = null" class="border border-white/15 text-white/60 px-4 py-2 rounded-lg text-sm hover:text-white">Cancel</button>
          <button @click="confirmSendBack" class="bg-primary text-black font-bold px-4 py-2 rounded-lg text-sm hover:opacity-90" :disabled="!sendBackTarget || sendBackComment.trim().length < 5">Send Back</button>
        </div>
      </div>
    </div>

    <!-- Detail drawer: workflow visualization + audit trail -->
    <div v-if="detail" class="fixed inset-0 bg-black/70 flex z-50" @click.self="detail = null">
      <div class="ml-auto w-full max-w-xl bg-surface h-full overflow-y-auto p-6">
        <div class="flex items-center justify-between mb-4">
          <h2 class="font-display font-bold text-lg text-primary uppercase">Workflow Journey</h2>
          <button @click="detail = null" class="text-secondary hover:text-primary">✕</button>
        </div>

        <div v-if="detailLoading" class="text-secondary text-sm py-8 text-center">Loading…</div>

        <template v-else>
          <div class="rounded-xl border border-white/10 p-4 mb-5">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="font-bold text-primary">{{ detail.instance.entity_summary?.label }}</span>
              <span :class="statusBadge(detail.instance)">{{ detail.instance.status_label }}</span>
            </div>
            <div class="grid grid-cols-2 gap-2 mt-3 text-xs">
              <div><span class="text-secondary">Entity:</span> <span class="text-primary">{{ detail.instance.entity_type }}</span></div>
              <div><span class="text-secondary">Amount:</span> <span class="text-primary font-bold">₹{{ Number(detail.instance.amount || 0).toLocaleString('en-IN') }}</span></div>
              <div><span class="text-secondary">Origin:</span> <span class="text-primary">{{ detail.instance.origin_org_unit }}</span></div>
              <div><span class="text-secondary">Current:</span> <span class="text-primary">{{ detail.instance.current_org_unit }}</span></div>
              <div><span class="text-secondary">Created:</span> <span class="text-primary">{{ fmt(detail.instance.created_at) }}</span></div>
              <div><span class="text-secondary">SLA due:</span> <span :class="detail.instance.sla_breached ? 'text-red-400' : 'text-primary'">{{ detail.instance.sla_due_at ? fmt(detail.instance.sla_due_at) : '—' }}</span></div>
            </div>
            <div v-if="detail.instance.entity_summary?.restaurant" class="mt-2 text-xs text-secondary">
              {{ detail.instance.entity_summary.restaurant }} — {{ detail.instance.entity_summary.restaurant_district || 'no district' }}, {{ detail.instance.entity_summary.restaurant_state || 'no state' }}
            </div>
          </div>

          <p class="text-[11px] uppercase tracking-widest text-secondary font-bold mb-3">Audit trail ({{ detail.steps.length }} steps)</p>
          <div class="relative pl-5 space-y-0">
            <div v-for="(step, i) in detail.steps" :key="i" class="relative pb-5 border-l border-white/10 pl-5 -ml-px">
              <span class="absolute -left-[7px] top-0 w-3.5 h-3.5 rounded-full border-2 border-surface" :class="stepDot(step.action)"></span>
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-semibold text-sm text-primary">{{ step.action_label }}</span>
                <span class="text-[11px] text-secondary">{{ step.actor }} · {{ fmt(step.at) }}</span>
              </div>
              <div class="text-xs text-secondary mt-1">
                <span>{{ step.org_unit }}</span>
                <template v-if="step.to_org_unit"> → <span class="text-primary">{{ step.to_org_unit }}</span></template>
              </div>
              <div v-if="step.comment" class="text-xs text-primary/80 mt-1 italic">“{{ step.comment }}”</div>
              <div v-if="step.metadata?.reason" class="text-[10px] text-amber-400 mt-1 uppercase">reason: {{ step.metadata.reason }}</div>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getMyApprovalQueue, getWorkflowDetail, getWorkflowChainOptions, approvalAction } from '../api'

const DEPARTMENTS = ['finance', 'account_management', 'rider_ops', 'support']
const ENTITY_TYPES = ['vendor_payout', 'invoice_correction', 'kyc_verification', 'refund', 'account_managers', 'rider_payout_exception']

const items = ref([])
const loading = ref(true)
const filters = ref({ status: '', department: '', entity_type: '' })

const sendBackItem = ref(null)
const sendBackTarget = ref('')
const sendBackComment = ref('')
const chainOptions = ref([])

const detail = ref(null)
const detailLoading = ref(false)

const load = async () => {
  loading.value = true
  try { items.value = (await getMyApprovalQueue(filters.value)).items || [] }
  catch (e) { alert(e.message || 'Failed to load queue') }
  finally { loading.value = false }
}

const statusBadge = (item) => ({
  'under_review': 'bg-blue-500/20 text-blue-400',
  'sent_back': 'bg-amber-500/20 text-amber-400',
  'approved': 'bg-emerald-500/20 text-emerald-400',
  'rejected': 'bg-red-500/20 text-red-400',
}[item.status] || 'bg-white/10 text-secondary')

const stepDot = (action) => {
  if (['approved', 'resubmitted'].includes(action)) return 'bg-emerald-400'
  if (['escalated', 'auto_escalated', 'sent_back_timeout'].includes(action)) return 'bg-blue-400'
  if (action === 'sent_back') return 'bg-amber-400'
  if (action === 'rejected') return 'bg-red-400'
  return 'bg-white'
}

const act = async (item, action) => {
  const needsComment = action === 'reject'
  const comment = needsComment ? (prompt('Rejection reason (required):') || '').trim() : ''
  if (needsComment && comment.length < 3) { alert('Rejection needs a reason'); return }
  if (!confirm(`Confirm ${action} for ${item.entity_summary?.label || item.entity_type}?`)) return
  try {
    await approvalAction(item.id, action, { comment })
    if (detail.value?.instance?.id === item.id) { detail.value = null }
    await load()
  } catch (e) { alert(e.message || JSON.stringify(e.errors || e)) }
}

const openSendBack = async (item) => {
  sendBackItem.value = item
  sendBackTarget.value = ''
  sendBackComment.value = ''
  try { chainOptions.value = await getWorkflowChainOptions(item.id) }
  catch (e) { chainOptions.value = []; alert(e.message || 'Failed to load chain options') }
}

const confirmSendBack = async () => {
  try {
    await approvalAction(sendBackItem.value.id, 'send-back', {
      target_org_unit_id: sendBackTarget.value,
      comment: sendBackComment.value.trim(),
    })
    sendBackItem.value = null
    await load()
  } catch (e) { alert(e.message || JSON.stringify(e.errors || e)) }
}

const openDetail = async (item) => {
  detail.value = { instance: item }
  detailLoading.value = true
  try { detail.value = await getWorkflowDetail(item.id) }
  catch (e) { alert(e.message || 'Failed to load workflow') }
  finally { detailLoading.value = false }
}

const fmt = (iso) => iso ? new Date(iso).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' }) : '—'
const timeAgo = (iso) => {
  if (!iso) return ''
  const s = Math.floor((Date.now() - new Date(iso).getTime()) / 1000)
  if (s < 60) return `${s}s`
  if (s < 3600) return `${Math.floor(s / 60)}m`
  if (s < 86400) return `${Math.floor(s / 3600)}h`
  return `${Math.floor(s / 86400)}d`
}

onMounted(load)
</script>

