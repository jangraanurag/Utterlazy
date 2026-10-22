<script setup>
import { ref, onMounted } from 'vue'
import api from '../api'

const staff = ref([])
const loading = ref(true)
const showCreate = ref(false)
const newMember = ref({ name: '', email: '', role: 'Staff' })

async function fetchStaff() {
  loading.value = true
  try {
    const res = await api.get('/vendor/staff')
    staff.value = res.data || res
  } catch (e) { staff.value = [] }
  loading.value = false
}

onMounted(fetchStaff)

async function addStaff() {
  try {
    await api.post('/vendor/staff', newMember.value)
    showCreate.value = false
    newMember.value = { name: '', email: '', role: 'Staff' }
    fetchStaff()
  } catch (e) { alert('Failed') }
}
</script>

<template>
  <div>
    <div class="page-header">
      <h1 class="page-title">Staff</h1>
      <button class="btn btn-primary" @click="showCreate = true">+ Add Staff</button>
    </div>

    <div v-if="loading" class="text-center" style="padding: 60px;">Loading...</div>
    <div v-else-if="staff.length === 0" class="card text-center" style="padding: 60px;">
      <div style="font-size: 3rem; margin-bottom: 12px;">👥</div>
      <p class="text-muted">No staff members</p>
    </div>
    <div v-else style="display: grid; gap: 12px;">
      <div v-for="member in staff" :key="member.id" class="card flex items-center gap-4" style="padding: 16px 24px;">
        <div class="rounded-full flex items-center justify-center font-bold" :style="{ width: '40px', height: '40px', background: member.is_active ? 'rgba(255,107,0,0.1)' : 'var(--sovereign-elevated)', color: member.is_active ? 'var(--sovereign-orange)' : 'var(--text-muted)' }">
          {{ member.name?.charAt(0) }}
        </div>
        <div style="flex: 1;">
          <div class="font-bold">{{ member.name }}</div>
          <div class="text-xs text-muted">{{ member.role }} • {{ member.email }}</div>
        </div>
        <span class="badge" :class="member.is_active ? 'badge-success' : 'badge-warning'">{{ member.is_active ? 'Active' : 'Inactive' }}</span>
        <button class="btn btn-xs btn-secondary">Edit</button>
      </div>
    </div>

    <div v-if="showCreate" class="modal-overlay" @click="showCreate = false">
      <div class="modal" @click.stop>
        <div class="modal-header">
          <span class="modal-title">Add Staff Member</span>
          <button class="modal-close" @click="showCreate = false">×</button>
        </div>
        <form @submit.prevent="addStaff" class="p-6">
          <div class="form-group">
            <label class="form-label">Full Name</label>
            <input class="form-input" v-model="newMember.name" required placeholder="Rajesh Kumar" />
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" class="form-input" v-model="newMember.email" required placeholder="rajesh@utterlazy.com" />
          </div>
          <div class="form-group">
            <label class="form-label">Role</label>
            <select class="form-input" v-model="newMember.role">
              <option>Manager</option><option>Kitchen</option><option>Staff</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary mt-4 w-full">Add Staff</button>
        </form>
      </div>
    </div>
  </div>
</template>
