<template>
  <div class="rounded-xl border border-white/10 bg-surface/50 overflow-hidden">
    <div class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-white/5" @click="toggle">
      <span class="material-symbols-outlined text-primary text-[18px]">
        {{ expanded ? 'expand_more' : 'chevron_right' }}
      </span>
      <span class="w-2 h-2 rounded-full" :class="unitColor"></span>
      <div class="flex-1 min-w-0">
        <div class="flex items-center gap-2">
          <span class="font-bold text-sm text-primary">{{ node.name }}</span>
          <span class="text-[10px] px-2 py-0.5 rounded-full bg-white/10 text-secondary uppercase tracking-wide">{{ node.unit_type }}</span>
          <span v-if="!node.is_active" class="text-[10px] px-2 py-0.5 rounded-full bg-red-500/20 text-red-400">inactive</span>
        </div>
        <div class="text-[11px] text-secondary mt-0.5 truncate">
          <template v-if="node.geography_scope && node.geography_scope.length">
            {{ scopeLabel }}
          </template>
          <template v-else>No geography scope (top of branch)</template>
        </div>
      </div>
      <div class="flex gap-1.5 opacity-0 group-hover:opacity-100" :class="{ 'opacity-100': expanded }">
        <button class="w-6 h-6 rounded-md bg-white/10 hover:bg-white/20 flex items-center justify-center text-white text-xs" title="Add child unit" @click.stop="emit('add-child', node)">+</button>
        <button class="w-6 h-6 rounded-md bg-white/10 hover:bg-white/20 flex items-center justify-center text-white text-xs" title="Add role" @click.stop="emit('add-role', node)">person_add</button>
        <button class="w-6 h-6 rounded-md bg-white/10 hover:bg-white/20 flex items-center justify-center text-white text-xs" title="Edit" @click.stop="emit('edit-unit', node)">edit</button>
        <button class="w-6 h-6 rounded-md bg-white/10 hover:bg-white/20 flex items-center justify-center text-white text-xs text-red-400" title="Delete" @click.stop="emit('delete-unit', node)">delete</button>
      </div>
    </div>

    <div v-if="expanded" class="px-4 pb-3 space-y-2">
      <div v-if="node.roles && node.roles.length" class="space-y-1.5">
        <div v-for="role in node.roles" :key="role.id"
             class="flex items-center gap-2 rounded-lg bg-black/30 border border-white/5 px-3 py-2 text-xs">
          <span class="material-symbols-outlined text-[15px] text-primary">badge</span>
          <span class="font-semibold text-primary">{{ role.title }}</span>
          <span class="text-secondary">→</span>
          <span class="text-secondary">{{ role.assigned_user ? role.assigned_user.name : 'VACANT' }}</span>
          <span v-if="role.backup_user" class="text-[10px] text-secondary">backup: {{ role.backup_user.name }}</span>
          <span v-if="!role.is_active" class="text-[10px] text-red-400">inactive</span>
          <span class="flex-1"></span>
          <button class="w-6 h-6 rounded-md bg-white/10 hover:bg-white/20 flex items-center justify-center text-white text-xs text-[14px]" title="Edit role" @click="emit('edit-role', node, role)">edit</button>
          <button class="w-6 h-6 rounded-md bg-white/10 hover:bg-white/20 flex items-center justify-center text-white text-xs text-[14px] text-red-400" title="Delete role" @click="emit('delete-role', role)">delete</button>
        </div>
      </div>
      <div v-else class="text-[11px] text-secondary italic">No roles assigned — items landing here auto-escalate upward.</div>
    </div>

    <div v-if="expanded && node.children && node.children.length" class="pl-6 pr-3 pb-3 space-y-2">
      <OrgUnitNode
        v-for="child in node.children"
        :key="child.id"
        :node="child"
        :users="users"
        @edit-unit="(u) => emit('edit-unit', u)"
        @delete-unit="(u) => emit('delete-unit', u)"
        @add-child="(u) => emit('add-child', u)"
        @add-role="(u) => emit('add-role', u)"
        @edit-role="(u, r) => emit('edit-role', u, r)"
        @delete-role="(r) => emit('delete-role', r)"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  node: { type: Object, required: true },
  users: { type: Array, default: () => [] },
})
const emit = defineEmits(['edit-unit', 'delete-unit', 'add-child', 'add-role', 'edit-role', 'delete-role'])

const expanded = ref(false)
const toggle = () => { expanded.value = !expanded.value }

const unitColor = computed(() => {
  const map = { national: 'bg-purple-400', state: 'bg-blue-400', district: 'bg-emerald-400', region: 'bg-amber-400', city: 'bg-pink-400' }
  return map[props.node.unit_type] || 'bg-white'
})

const scopeLabel = computed(() => {
  return props.node.geography_scope
    .map(s => [s.state, s.district].filter(Boolean).join(' / ') + (s.pincodes?.length ? ` (${s.pincodes.join(',')})` : ''))
    .filter(Boolean).join(' · ')
})
</script>

