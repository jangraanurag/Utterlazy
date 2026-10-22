<template>
  <div class="p-8">
    <div class="flex justify-between items-center mb-8">
      <div>
        <h1 class="text-3xl font-bold text-white">Account Managers</h1>
        <p class="text-white/50 text-sm mt-1">Manage AM accounts and restaurant assignments</p>
      </div>
      <button @click="showCreateForm = true" class="px-6 py-3 rounded-xl sovereign-gradient text-white font-bold text-sm uppercase tracking-wider">+ New AM</button>
    </div>

    <!-- Tabs -->
    <div class="flex gap-4 mb-6">
      <button @click="tab = 'ams'" :class="tab === 'ams' ? 'text-primary border-primary' : 'text-white/50 border-transparent'" class="pb-2 border-b-2 font-bold text-sm transition-colors">Account Managers</button>
      <button @click="tab = 'assignments'" :class="tab === 'assignments' ? 'text-primary border-primary' : 'text-white/50 border-transparent'" class="pb-2 border-b-2 font-bold text-sm transition-colors">Assignments</button>
    </div>

    <!-- AM List -->
    <div v-if="tab === 'ams'">
      <div v-if="loading" class="text-center py-20 text-white/50">Loading...</div>
      <div v-else-if="ams.length === 0" class="text-center py-20 text-white/50">No Account Managers yet</div>
      <div v-else class="grid gap-4">
        <div v-for="am in ams" :key="am.id" class="bg-surface rounded-2xl border border-white/5 p-6 flex items-center justify-between">
          <div>
            <h3 class="text-lg font-bold text-white">{{ am.name }}</h3>
            <p class="text-white/50 text-sm">{{ am.email }} · {{ am.phone || 'No phone' }} · {{ am.assignments_count || 0 }} restaurants</p>
          </div>
          <div class="flex gap-2">
            <span :class="am.is_admin ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'" class="px-3 py-1 rounded-full text-xs font-bold">{{ am.is_admin ? 'Active' : 'Inactive' }}</span>
            <button @click="editAm(am)" class="px-4 py-2 rounded-xl bg-white/5 text-white/70 text-sm hover:bg-white/10">Edit</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Assignments Tab -->
    <div v-if="tab === 'assignments'">
      <div v-if="loading" class="text-center py-20 text-white/50">Loading...</div>
      <div v-else>
        <div class="mb-6 flex gap-4">
          <select v-model="selectedAm" class="px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white">
            <option value="">Select Account Manager</option>
            <option v-for="am in ams" :key="am.id" :value="am.id">{{ am.name }}</option>
          </select>
          <select v-model="selectedRestaurant" class="px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white flex-1">
            <option value="">Select Restaurant</option>
            <option v-for="r in availableRestaurants" :key="r.id" :value="r.id">{{ r.name }}</option>
          </select>
          <button @click="assignRestaurant" :disabled="!selectedAm || !selectedRestaurant" class="px-6 py-3 rounded-xl sovereign-gradient text-white font-bold disabled:opacity-50">Assign</button>
        </div>

        <div v-if="assignmentsList.length === 0" class="text-center py-20 text-white/50">No assignments yet</div>
        <div v-else>
          <div v-for="(ams, amId) in assignmentsList" :key="amId" class="mb-8">
            <h3 class="text-lg font-bold text-white mb-4">{{ ams[0]?.account_manager?.name || 'Unknown AM' }}</h3>
            <div v-for="a in ams" :key="a.id" class="flex items-center justify-between bg-surface rounded-xl border border-white/5 p-4 mb-2">
              <span class="text-white">{{ a.restaurant?.name || 'Unknown' }}</span>
              <button @click="unassign(a.id)" class="text-red-400 text-sm hover:text-red-300">Unassign</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit AM Modal -->
    <div v-if="showCreateForm" class="fixed inset-0 bg-black/60 backdrop-blur-md z-50 flex items-center justify-center p-6" @click.self="showCreateForm = false; editingId = null">
      <div class="bg-surface rounded-3xl p-8 w-full max-w-md border border-white/5">
        <h2 class="text-2xl font-bold text-white mb-6">{{ editingId ? 'Edit AM' : 'New Account Manager' }}</h2>
        <div v-if="formError" class="mb-4 p-3 rounded-xl bg-red-500/10 text-red-400 text-sm">{{ formError }}</div>
        <div class="space-y-4">
          <div>
            <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Name</label>
            <input v-model="form.name" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white"/>
          </div>
          <div>
            <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Email</label>
            <input v-model="form.email" type="email" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white"/>
          </div>
          <div>
            <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">Phone</label>
            <input v-model="form.phone" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white"/>
          </div>
          <div>
            <label class="block text-xs uppercase tracking-widest text-white/50 mb-2">{{ editingId ? 'New Password (leave blank to keep)' : 'Password' }}</label>
            <input v-model="form.password" type="password" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-white/10 text-white"/>
          </div>
        </div>
        <div class="flex gap-4 mt-8">
          <button @click="showCreateForm = false; editingId = null" class="flex-1 py-3 rounded-xl bg-white/5 text-white/70 hover:bg-white/10">Cancel</button>
          <button @click="saveAm" :disabled="saving" class="flex-1 py-3 rounded-xl sovereign-gradient text-white font-bold disabled:opacity-50">{{ saving ? 'Saving...' : (editingId ? 'Update' : 'Create') }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { adminGetAccountManagers, adminCreateAccountManager, adminUpdateAccountManager, adminAssignRestaurant, adminUnassignRestaurant, adminGetAssignments, adminGetAvailableRestaurants } from '../api'

const tab = ref('ams')
const ams = ref([])
const loading = ref(true)
const showCreateForm = ref(false)
const editingId = ref(null)
const saving = ref(false)
const formError = ref('')
const availableRestaurants = ref([])
const assignmentsList = ref([])
const selectedAm = ref('')
const selectedRestaurant = ref('')

const form = reactive({ name: '', email: '', phone: '', password: '' })

const fetchData = async () => {
  loading.value = true
  try {
    if (tab.value === 'ams') {
      ams.value = await adminGetAccountManagers()
    } else {
      const [assignments, avail] = await Promise.all([
        adminGetAssignments(),
        adminGetAvailableRestaurants()
      ])
      assignmentsList.value = assignments
      availableRestaurants.value = avail
    }
  } catch (e) { console.error(e) }
  finally { loading.value = false }
}

const editAm = (am) => {
  editingId.value = am.id
  form.name = am.name; form.email = am.email; form.phone = am.phone || ''; form.password = ''
  showCreateForm.value = true
}

const saveAm = async () => {
  saving.value = true; formError.value = ''
  try {
    if (editingId.value) {
      await adminUpdateAccountManager(editingId.value, { name: form.name, email: form.email, phone: form.phone, password: form.password || undefined })
    } else {
      await adminCreateAccountManager({ name: form.name, email: form.email, phone: form.phone, password: form.password })
    }
    showCreateForm.value = false; editingId.value = null
    form.name = ''; form.email = ''; form.phone = ''; form.password = ''
    await fetchData()
  } catch (e) { formError.value = e.errors ? Object.values(e.errors).flat().join(' ') : (e.message || 'Failed') }
  finally { saving.value = false }
}

const assignRestaurant = async () => {
  try {
    await adminAssignRestaurant(selectedAm.value, selectedRestaurant.value)
    selectedAm.value = ''; selectedRestaurant.value = ''
    await fetchData()
  } catch (e) { alert('Failed: ' + (e.message || e)) }
}

const unassign = async (id) => {
  if (!confirm('Unassign this restaurant?')) return
  try { await adminUnassignRestaurant(id); await fetchData() }
  catch (e) { alert('Failed: ' + (e.message || e)) }
}

onMounted(fetchData)
</script>
