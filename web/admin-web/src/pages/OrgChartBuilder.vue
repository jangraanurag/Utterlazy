<template>
  <div class="p-6">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="font-display font-bold text-2xl text-primary uppercase tracking-wide">Org Chart Builder</h1>
        <p class="text-sm text-secondary mt-1">Variable-depth, geography-scoped hierarchy per department — the routing map for the Approval Engine</p>
      </div>
      <button @click="openUnitModal()" class="bg-primary text-black font-bold px-4 py-2 rounded-lg text-sm hover:opacity-90">+ New Root Unit</button>
    </div>

    <div class="flex gap-4 mb-6">
      <select v-model="department" @change="load" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:border-primary">
        <option value="">All departments</option>
        <option v-for="d in DEPARTMENTS" :key="d" :value="d">{{ d }}</option>
      </select>
      <select v-model="filterUnitType" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:border-primary">
        <option value="">All unit types</option>
        <option v-for="t in UNIT_TYPES" :key="t" :value="t">{{ t }}</option>
      </select>
    </div>

    <div v-if="loading" class="text-secondary text-sm py-10 text-center">Loading org chart…</div>

    <div v-else-if="tree.length === 0" class="text-secondary text-sm py-10 text-center">
      No org units yet. Create the first root unit (e.g. Finance HQ / CAO) to start.
    </div>

    <div v-else class="space-y-3">
      <OrgUnitNode
        v-for="node in filteredTree"
        :key="node.id"
        :node="node"
        :users="users"
        @edit-unit="openUnitModal"
        @delete-unit="deleteUnit"
        @add-child="openUnitModal"
        @add-role="openRoleModal"
        @edit-role="openRoleModal"
        @delete-role="deleteRole"
      />
    </div>

    <!-- Unit modal -->
    <div v-if="unitModal" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50" @click.self="unitModal = false">
      <div class="bg-surface rounded-2xl p-6 w-full max-w-lg max-h-[85vh] overflow-y-auto">
        <h2 class="font-display font-bold text-lg text-primary uppercase mb-4">{{ unitModal.id ? 'Edit Org Unit' : (unitModal.parent_id ? 'Add Child Unit' : 'New Root Unit') }}</h2>
        <div class="space-y-4">
          <div>
            <label class="block text-[11px] uppercase tracking-wider text-white/60 font-bold mb-1">Name</label>
            <input v-model="unitForm.name" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:border-primary" placeholder="e.g. Bhiwani District Office" />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-[11px] uppercase tracking-wider text-white/60 font-bold mb-1">Department</label>
              <select v-model="unitForm.department" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:border-primary">
                <option v-for="d in DEPARTMENTS" :key="d" :value="d">{{ d }}</option>
              </select>
            </div>
            <div>
              <label class="block text-[11px] uppercase tracking-wider text-white/60 font-bold mb-1">Unit type</label>
              <select v-model="unitForm.unit_type" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:border-primary">
                <option v-for="t in UNIT_TYPES" :key="t" :value="t">{{ t }}</option>
              </select>
            </div>
          </div>
          <div>
            <label class="block text-[11px] uppercase tracking-wider text-white/60 font-bold mb-1">Geography scope (state/district/pincodes covered)</label>
            <div v-for="(row, i) in unitForm.geography_scope" :key="i" class="flex gap-2 mb-2">
              <input v-model="row.state" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:border-primary flex-1" placeholder="State (e.g. Haryana)" />
              <input v-model="row.district" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:border-primary flex-1" placeholder="District (e.g. Bhiwani)" />
              <input v-model="row.pincodes" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:border-primary flex-1" placeholder="Pincodes (comma)" />
              <button @click="unitForm.geography_scope.splice(i, 1)" class="text-red-400 px-2">✕</button>
            </div>
            <button @click="unitForm.geography_scope.push({ state: '', district: '', pincodes: '' })" class="text-sm text-primary">+ Add scope row</button>
            <p class="text-[11px] text-secondary mt-1">Items are routed to the deepest unit whose scope matches. Leave empty for top-level (national).</p>
          </div>
          <div class="flex items-center gap-3">
            <input v-model="unitForm.is_active" type="checkbox" id="unitActive" class="accent-primary" />
            <label for="unitActive" class="text-sm text-primary">Active</label>
          </div>
        </div>
        <div class="flex justify-end gap-3 mt-6">
          <button @click="unitModal = false" class="border border-white/15 text-white/60 px-4 py-2 rounded-lg text-sm hover:text-white">Cancel</button>
          <button @click="saveUnit" class="bg-primary text-black font-bold px-4 py-2 rounded-lg text-sm hover:opacity-90">{{ unitForm.id ? 'Save' : 'Create' }}</button>
        </div>
      </div>
    </div>

    <!-- Role modal -->
    <div v-if="roleModal" class="fixed inset-0 bg-black/70 flex items-center justify-center z-50" @click.self="roleModal = false">
      <div class="bg-surface rounded-2xl p-6 w-full max-w-md">
        <h2 class="font-display font-bold text-lg text-primary uppercase mb-4">{{ roleForm.id ? 'Edit Role' : 'Add Role' }} — {{ roleForm.org_unit_name }}</h2>
        <div class="space-y-4">
          <div>
            <label class="block text-[11px] uppercase tracking-wider text-white/60 font-bold mb-1">Title</label>
            <input v-model="roleForm.title" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:border-primary" placeholder="e.g. District Account Officer" />
          </div>
          <div>
            <label class="block text-[11px] uppercase tracking-wider text-white/60 font-bold mb-1">Assigned user</label>
            <select v-model="roleForm.assigned_user_id" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:border-primary">
              <option value="">— vacant —</option>
              <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.admin_role }})</option>
            </select>
          </div>
          <div>
            <label class="block text-[11px] uppercase tracking-wider text-white/60 font-bold mb-1">Backup user (§5.4)</label>
            <select v-model="roleForm.backup_user_id" class="w-full bg-black/40 border border-white/10 rounded-lg px-3 py-2 text-sm text-white placeholder-white/40 focus:outline-none focus:border-primary">
              <option value="">— none —</option>
              <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.admin_role }})</option>
            </select>
          </div>
          <div class="flex items-center gap-3">
            <input v-model="roleForm.is_active" type="checkbox" id="roleActive" class="accent-primary" />
            <label for="roleActive" class="text-sm text-primary">Active</label>
          </div>
          <p class="text-[11px] text-secondary">If the assigned user is unavailable and no backup exists, items auto-escalate to the parent unit.</p>
        </div>
        <div class="flex justify-end gap-3 mt-6">
          <button @click="roleModal = false" class="border border-white/15 text-white/60 px-4 py-2 rounded-lg text-sm hover:text-white">Cancel</button>
          <button @click="saveRole" class="bg-primary text-black font-bold px-4 py-2 rounded-lg text-sm hover:opacity-90">{{ roleForm.id ? 'Save' : 'Add' }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { getOrgTree, createOrgUnit, updateOrgUnit, deleteOrgUnit, createOrgRole, updateOrgRole, deleteOrgRole, getAdminUsers } from '../api'
import OrgUnitNode from '../components/OrgUnitNode.vue'

const DEPARTMENTS = ['finance', 'account_management', 'rider_ops', 'support']
const UNIT_TYPES = ['national', 'state', 'district', 'region', 'city']

const tree = ref([])
const users = ref([])
const loading = ref(true)
const department = ref('')
const filterUnitType = ref('')

const unitModal = ref(false)
const unitForm = ref({})
const roleModal = ref(false)
const roleForm = ref({})

const filteredTree = computed(() => {
  if (!filterUnitType.value) return tree.value
  const walk = (nodes) => nodes
    .filter(n => n.unit_type === filterUnitType.value || n.children.some(c => walk([c]).length > 0))
    .map(n => ({ ...n, children: walk(n.children) }))
  return walk(tree.value)
})

const load = async () => {
  loading.value = true
  try {
    tree.value = await getOrgTree(department.value)
  } catch (e) {
    alert(e.message || 'Failed to load')
  } finally {
    loading.value = false
  }
}

const openUnitModal = (unit = null) => {
  unitModal.value = true
  unitForm.value = unit ? {
    id: unit.id,
    name: unit.name,
    department: unit.department,
    unit_type: unit.unit_type,
    parent_org_unit_id: unit.parent_org_unit_id || null,
    is_active: unit.is_active,
    geography_scope: (unit.geography_scope || []).map(s => ({ ...s, pincodes: (s.pincodes || []).join(', ') })),
  } : {
    name: '',
    department: department.value || 'finance',
    unit_type: 'district',
    parent_org_unit_id: null,
    is_active: true,
    geography_scope: [{ state: '', district: '', pincodes: '' }],
  }
}

const openChildModal = (parent) => {
  openUnitModal(null)
  unitForm.value.department = parent.department
  unitForm.value.parent_org_unit_id = parent.id
}

const saveUnit = async () => {
  try {
    const payload = {
      ...unitForm.value,
      geography_scope: unitForm.value.geography_scope
        .filter(r => r.state || r.district || r.pincodes)
        .map(r => ({
          state: r.state.trim() || undefined,
          district: r.district.trim() || undefined,
          pincodes: r.pincodes ? r.pincodes.split(',').map(p => p.trim()).filter(Boolean) : undefined,
        })),
    }
    if (unitForm.value.id) {
      await updateOrgUnit(unitForm.value.id, payload)
    } else {
      await createOrgUnit(payload)
    }
    unitModal.value = false
    await load()
  } catch (e) {
    alert(e.message || JSON.stringify(e.errors || e))
  }
}

const deleteUnit = async (unit) => {
  if (!confirm(`Delete "${unit.name}"? Only possible if it has no children or roles.`)) return
  try {
    await deleteOrgUnit(unit.id)
    await load()
  } catch (e) {
    alert(e.message || JSON.stringify(e.errors || e))
  }
}

const openRoleModal = (unit, role = null) => {
  roleModal.value = true
  roleForm.value = role ? {
    id: role.id,
    org_unit_id: role.org_unit_id || unit.id,
    org_unit_name: unit.name,
    title: role.title,
    assigned_user_id: role.assigned_user?.id || '',
    backup_user_id: role.backup_user?.id || '',
    is_active: role.is_active,
  } : {
    id: null,
    org_unit_id: unit.id,
    org_unit_name: unit.name,
    title: '',
    assigned_user_id: '',
    backup_user_id: '',
    is_active: true,
  }
}

const saveRole = async () => {
  try {
    const payload = {
      title: roleForm.value.title,
      assigned_user_id: roleForm.value.assigned_user_id || null,
      backup_user_id: roleForm.value.backup_user_id || null,
      is_active: roleForm.value.is_active,
    }
    if (roleForm.value.id) {
      await updateOrgRole(roleForm.value.id, payload)
    } else {
      await createOrgRole(roleForm.value.org_unit_id, payload)
    }
    roleModal.value = false
    await load()
  } catch (e) {
    alert(e.message || JSON.stringify(e.errors || e))
  }
}

const deleteRole = async (role) => {
  if (!confirm(`Delete role "${role.title}"?`)) return
  try {
    await deleteOrgRole(role.id)
    await load()
  } catch (e) {
    alert(e.message || JSON.stringify(e.errors || e))
  }
}

onMounted(async () => {
  await load()
  try { users.value = await getAdminUsers() } catch (e) { /* non-fatal */ }
})
</script>

