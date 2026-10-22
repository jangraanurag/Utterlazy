<script setup>
import { ref, watch, onMounted } from 'vue'
import { useAuth } from '../auth'
import { updateVendorProfile, updateOperatingHours } from '../api'

const { restaurant, loadProfile } = useAuth()
const saving = ref(false)
const tab = ref('profile')

const profile = ref({ name: '', description: '', phone: '', address: '' })
const compliance = ref({ fssai_number: '', gst_number: '', pan_number: '' })
const bank = ref({ bank_account_number: '', bank_ifsc: '', bank_name: '', bank_holder_name: '' })
const hours = ref({
  monday: { open: '09:00', close: '23:00', is_open: true },
  tuesday: { open: '09:00', close: '23:00', is_open: true },
  wednesday: { open: '09:00', close: '23:00', is_open: true },
  thursday: { open: '09:00', close: '23:00', is_open: true },
  friday: { open: '09:00', close: '23:00', is_open: true },
  saturday: { open: '09:00', close: '23:00', is_open: true },
  sunday: { open: '10:00', close: '22:00', is_open: true },
})

onMounted(() => {
  if (restaurant.value) syncFromRestaurant()
})
watch(restaurant, syncFromRestaurant)

function syncFromRestaurant() {
  const r = restaurant.value
  if (!r) return
  profile.value = { name: r.name || '', description: r.description || '', phone: r.phone || '', address: r.address || '' }
  compliance.value = { fssai_number: r.fssai_number || '', gst_number: r.gst_number || '', pan_number: r.pan_number || '' }
  bank.value = { bank_account_number: r.bank_account_number || '', bank_ifsc: r.bank_ifsc || '', bank_name: r.bank_name || '', bank_holder_name: r.bank_holder_name || '' }
  if (r.operating_hours) hours.value = r.operating_hours
}

async function saveProfile() {
  saving.value = true
  try { await updateVendorProfile(profile.value); await loadProfile(); alert('Saved!') }
  catch (e) { alert('Error: ' + (e.response?.data?.message || e.message)) }
  saving.value = false
}

async function saveCompliance() {
  saving.value = true
  try { await updateVendorProfile(compliance.value); await loadProfile(); alert('Saved!') }
  catch (e) { alert('Error: ' + (e.response?.data?.message || e.message)) }
  saving.value = false
}

async function saveBank() {
  saving.value = true
  try { await updateVendorProfile(bank.value); await loadProfile(); alert('Saved!') }
  catch (e) { alert('Error: ' + (e.response?.data?.message || e.message)) }
  saving.value = false
}

async function saveHours() {
  saving.value = true
  try { await updateOperatingHours(hours.value); await loadProfile(); alert('Saved!') }
  catch (e) { alert('Error: ' + (e.response?.data?.message || e.message)) }
  saving.value = false
}

const tabs = [
  { key: 'profile', label: 'Profile' },
  { key: 'compliance', label: 'Compliance' },
  { key: 'bank', label: 'Banking' },
  { key: 'hours', label: 'Hours' },
]
</script>

<template>
  <div>
    <div class="page-header">
      <h1 class="page-title">Settings</h1>
      <p class="page-subtitle">Manage your restaurant profile, compliance, and banking</p>
    </div>

    <div class="tabs">
      <button v-for="t in tabs" :key="t.key" class="tab" :class="{ active: tab === t.key }" @click="tab = t.key">{{ t.label }}</button>
    </div>

    <div v-if="tab === 'profile'" class="card">
      <h3 class="font-space mb-5" style="font-weight: 700;">Restaurant Profile</h3>
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Restaurant Name</label>
          <input class="form-input" v-model="profile.name" />
        </div>
        <div class="form-group">
          <label class="form-label">Phone</label>
          <input class="form-input" v-model="profile.phone" placeholder="+91 9876543210" />
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea class="form-input" rows="3" v-model="profile.description"></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Address</label>
        <input class="form-input" v-model="profile.address" />
      </div>
      <div class="flex-between mt-4">
        <div class="text-sm text-muted">
          Commission: <span style="font-weight: 700;">{{ restaurant?.commission_rate }}%</span> (set by admin)
        </div>
        <button class="btn btn-primary" @click="saveProfile" :disabled="saving">{{ saving ? 'Saving...' : 'Save' }}</button>
      </div>
    </div>

    <div v-if="tab === 'compliance'" class="card">
      <h3 class="font-space mb-5" style="font-weight: 700;">Compliance & Licensing</h3>
      <div class="form-group">
        <label class="form-label">FSSAI License Number</label>
        <input class="form-input" v-model="compliance.fssai_number" placeholder="14 digit FSSAI number" maxlength="14" />
      </div>
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">GST Number</label>
          <input class="form-input" v-model="compliance.gst_number" placeholder="22AAAAA0000A1Z5" maxlength="15" />
        </div>
        <div class="form-group">
          <label class="form-label">PAN Number</label>
          <input class="form-input" v-model="compliance.pan_number" placeholder="ABCDE1234F" maxlength="10" style="text-transform: uppercase;" />
        </div>
      </div>
      <button class="btn btn-primary mt-4" @click="saveCompliance" :disabled="saving">{{ saving ? 'Saving...' : 'Save' }}</button>
    </div>

    <div v-if="tab === 'bank'" class="card">
      <h3 class="font-space mb-5" style="font-weight: 700;">Banking Details</h3>
      <p class="text-sm text-muted mb-4">Payouts are settled to this account weekly</p>
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Account Holder Name</label>
          <input class="form-input" v-model="bank.bank_holder_name" />
        </div>
        <div class="form-group">
          <label class="form-label">Bank Name</label>
          <input class="form-input" v-model="bank.bank_name" placeholder="State Bank of India" />
        </div>
      </div>
      <div class="grid-2">
        <div class="form-group">
          <label class="form-label">Account Number</label>
          <input class="form-input" v-model="bank.bank_account_number" />
        </div>
        <div class="form-group">
          <label class="form-label">IFSC Code</label>
          <input class="form-input" v-model="bank.bank_ifsc" placeholder="SBIN0001234" maxlength="11" style="text-transform: uppercase;" />
        </div>
      </div>
      <button class="btn btn-primary mt-4" @click="saveBank" :disabled="saving">{{ saving ? 'Saving...' : 'Save' }}</button>
    </div>

    <div v-if="tab === 'hours'" class="card">
      <h3 class="font-space mb-5" style="font-weight: 700;">Operating Hours</h3>
      <div v-for="(config, day) in hours" :key="day" class="flex-between" style="padding: 12px 0; border-bottom: 1px solid var(--glass-edge);">
        <div class="flex gap-3 items-center">
          <label class="switch">
            <input type="checkbox" v-model="config.is_open" />
            <span class="slider"></span>
          </label>
          <span style="font-weight: 600; text-transform: capitalize; width: 100px;">{{ day }}</span>
        </div>
        <div v-if="config.is_open" class="flex gap-2 items-center">
          <input type="time" class="form-input" style="width: 120px; padding: 6px 8px;" v-model="config.open" />
          <span class="text-muted">to</span>
          <input type="time" class="form-input" style="width: 120px; padding: 6px 8px;" v-model="config.close" />
        </div>
        <span v-else class="badge badge-danger">Closed</span>
      </div>
      <button class="btn btn-primary mt-4" @click="saveHours" :disabled="saving">{{ saving ? 'Saving...' : 'Save' }}</button>
    </div>
  </div>
</template>
