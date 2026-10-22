<template>
  <div>
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-black text-white flex items-center gap-3">
          <span class="w-10 h-10 bg-gradient-to-br from-cyan-400 to-blue-500 rounded-xl flex items-center justify-center text-lg">🏍️</span>
          Rider Fleet
        </h1>
        <p class="text-white/50 text-sm mt-1">Manage your delivery partners</p>
      </div>
      <button @click="showAddModal = true" class="bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-bold px-5 py-2.5 rounded-xl flex items-center gap-2 hover:opacity-90 transition-opacity shadow-lg shadow-cyan-500/20">
        <span class="material-symbols-outlined text-[18px]">person_add</span> Add Rider
      </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-4 gap-4 mb-6">
      <div class="bg-white/5 border border-white/10 rounded-xl p-4">
        <p class="text-white/40 text-xs uppercase tracking-wider mb-1">Total Riders</p>
        <p class="text-2xl font-black text-white">{{ riderStats.total }}</p>
      </div>
      <div class="bg-white/5 border border-white/10 rounded-xl p-4">
        <p class="text-white/40 text-xs uppercase tracking-wider mb-1">Online Now</p>
        <p class="text-2xl font-black text-green-400">{{ riderStats.online }}</p>
      </div>
      <div class="bg-white/5 border border-white/10 rounded-xl p-4">
        <p class="text-white/40 text-xs uppercase tracking-wider mb-1">On Delivery</p>
        <p class="text-2xl font-black text-cyan-400">{{ riderStats.active }}</p>
      </div>
      <div class="bg-white/5 border border-white/10 rounded-xl p-4">
        <p class="text-white/40 text-xs uppercase tracking-wider mb-1">Unverified</p>
        <p class="text-2xl font-black text-amber-400">{{ riderStats.unverified }}</p>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex gap-2 mb-4">
      <button v-for="f in ['all','online','offline','unverified']" :key="f"
        @click="filter = f === 'all' ? '' : f; fetchRiders()"
        :class="(f === 'all' && !filter) || filter === f ? 'bg-white text-black' : 'bg-white/10 text-white/60 hover:bg-white/20'"
        class="px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider transition-colors">
        {{ f }}
      </button>
    </div>

    <!-- Table -->
    <div class="bg-white/5 border border-white/10 rounded-2xl overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-white/10 text-white/40 text-xs uppercase tracking-wider">
              <th class="text-left p-4">Rider</th>
              <th class="text-left p-4">Vehicle</th>
              <th class="text-center p-4">Status</th>
              <th class="text-center p-4">Rating</th>
              <th class="text-center p-4">Deliveries</th>
              <th class="text-right p-4">Earnings</th>
              <th class="text-center p-4">Accept %</th>
              <th class="text-center p-4">Verified</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="rider in riders" :key="rider.id"
              class="border-b border-white/5 hover:bg-white/5 transition-colors">
              <td class="p-4">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-full bg-gradient-to-br from-cyan-400/20 to-blue-500/20 flex items-center justify-center text-cyan-400 font-bold text-sm">
                    {{ rider.name.charAt(0).toUpperCase() }}
                  </div>
                  <div>
                    <p class="font-bold text-white">{{ rider.name }}</p>
                    <p class="text-white/40 text-xs">{{ rider.phone }}</p>
                  </div>
                </div>
              </td>
              <td class="p-4">
                <span class="px-2 py-1 rounded-md text-xs font-bold uppercase" :class="{
                  'bg-blue-500/20 text-blue-400': rider.vehicle_type === 'bike',
                  'bg-purple-500/20 text-purple-400': rider.vehicle_type === 'scooter',
                  'bg-green-500/20 text-green-400': rider.vehicle_type === 'bicycle',
                  'bg-amber-500/20 text-amber-400': rider.vehicle_type === 'car',
                }">
                  {{ rider.vehicle_type }}
                </span>
                <span v-if="rider.vehicle_number" class="text-white/30 text-xs ml-1">{{ rider.vehicle_number }}</span>
              </td>
              <td class="p-4 text-center">
                <span :class="rider.is_online ? 'bg-green-500/20 text-green-400' : 'bg-white/10 text-white/40'"
                  class="px-2 py-1 rounded-full text-xs font-bold">
                  {{ rider.is_online ? '🟢 Online' : '🔴 Offline' }}
                </span>
              </td>
              <td class="p-4 text-center">
                <span class="text-amber-400 font-bold">⭐ {{ rider.avg_rating.toFixed(1) }}</span>
              </td>
              <td class="p-4 text-center font-bold text-white">{{ rider.total_deliveries }}</td>
              <td class="p-4 text-right font-bold text-green-400">₹{{ parseFloat(rider.total_earnings).toFixed(0) }}</td>
              <td class="p-4 text-center">
                <span :class="rider.acceptance_rate >= 80 ? 'text-green-400' : rider.acceptance_rate >= 50 ? 'text-amber-400' : 'text-red-400'"
                  class="font-bold">{{ rider.acceptance_rate.toFixed(0) }}%</span>
              </td>
              <td class="p-4 text-center">
                <button @click="toggleVerify(rider)" class="transition-all hover:scale-110" :title="rider.is_verified ? 'Click to unverify' : 'Click to verify'">
                  <span v-if="rider.is_verified" class="text-green-400 text-lg">✅</span>
                  <span v-else class="text-white/30 text-lg hover:text-amber-400">⏳</span>
                </button>
              </td>
            </tr>
            <tr v-if="riders.length === 0">
              <td colspan="8" class="p-12 text-center text-white/30">No riders found.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="lastPage > 1" class="flex justify-center gap-2 mt-4">
      <button v-for="p in lastPage" :key="p" @click="page = p; fetchRiders()"
        :class="p === page ? 'bg-white text-black' : 'bg-white/10 text-white/60 hover:bg-white/20'"
        class="w-8 h-8 rounded-lg text-xs font-bold transition-colors">{{ p }}</button>
    </div>

    <!-- Add Rider Modal -->
    <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
      <div class="bg-[#1a1a2e] border border-white/10 rounded-2xl p-8 w-full max-w-md shadow-2xl">
        <h2 class="text-xl font-black text-white mb-6 flex items-center gap-2">
          <span class="material-symbols-outlined text-cyan-400">person_add</span> Add New Rider
        </h2>
        <div class="space-y-4">
          <div>
            <label class="text-white/50 text-xs uppercase tracking-wider block mb-1">Name</label>
            <input v-model="newRider.name" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-cyan-500" placeholder="Rider name" />
          </div>
          <div>
            <label class="text-white/50 text-xs uppercase tracking-wider block mb-1">Email</label>
            <input v-model="newRider.email" type="email" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-cyan-500" placeholder="rider@email.com" />
          </div>
          <div>
            <label class="text-white/50 text-xs uppercase tracking-wider block mb-1">Phone</label>
            <input v-model="newRider.phone" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-cyan-500" placeholder="+91 9876543210" />
          </div>
          <div>
            <label class="text-white/50 text-xs uppercase tracking-wider block mb-1">Password</label>
            <input v-model="newRider.password" type="password" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-cyan-500" placeholder="Min 6 chars" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-white/50 text-xs uppercase tracking-wider block mb-1">Vehicle</label>
              <select v-model="newRider.vehicle_type" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-cyan-500">
                <option value="bike">🏍️ Bike</option>
                <option value="scooter">🛵 Scooter</option>
                <option value="bicycle">🚲 Bicycle</option>
                <option value="car">🚗 Car</option>
              </select>
            </div>
            <div>
              <label class="text-white/50 text-xs uppercase tracking-wider block mb-1">Vehicle No.</label>
              <input v-model="newRider.vehicle_number" class="w-full bg-white/5 border border-white/10 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:border-cyan-500" placeholder="UP32 XX 1234" />
            </div>
          </div>
        </div>
        <p v-if="addError" class="text-red-400 text-xs mt-3">{{ addError }}</p>
        <div class="flex gap-3 mt-6">
          <button @click="showAddModal = false" class="flex-1 bg-white/10 text-white font-bold py-2.5 rounded-xl hover:bg-white/20 transition-colors">Cancel</button>
          <button @click="addRider" :disabled="adding" class="flex-1 bg-gradient-to-r from-cyan-500 to-blue-600 text-white font-bold py-2.5 rounded-xl hover:opacity-90 transition-opacity disabled:opacity-50">
            {{ adding ? 'Creating...' : 'Create Rider' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { getRiders as fetchRidersApi, createRider, verifyRider as verifyRiderApi } from '../api'

const riders = ref([])
const page = ref(1)
const lastPage = ref(1)
const filter = ref('')
const showAddModal = ref(false)
const adding = ref(false)
const addError = ref('')

const riderStats = reactive({ total: 0, online: 0, active: 0, unverified: 0 })

const newRider = reactive({
  name: '', email: '', phone: '', password: '',
  vehicle_type: 'bike', vehicle_number: '',
})

const fetchRiders = async () => {
  try {
    const data = await fetchRidersApi(filter.value, page.value)
    riders.value = data.data || []
    lastPage.value = data.last_page || 1

    // Compute stats from unfiltered view
    riderStats.total = data.total || riders.value.length
    riderStats.online = riders.value.filter(r => r.is_online).length
    riderStats.active = riders.value.filter(r => r.current_order_id).length
    riderStats.unverified = riders.value.filter(r => !r.is_verified).length
  } catch (e) { console.error(e) }
}

const addRider = async () => {
  adding.value = true
  addError.value = ''
  try {
    await createRider({ ...newRider, is_verified: true })
    showAddModal.value = false
    Object.assign(newRider, { name: '', email: '', phone: '', password: '', vehicle_type: 'bike', vehicle_number: '' })
    await fetchRiders()
  } catch (e) {
    addError.value = e.message || JSON.stringify(e.errors || e)
  } finally { adding.value = false }
}

const toggleVerify = async (rider) => {
  try {
    await verifyRiderApi(rider.id)
    rider.is_verified = !rider.is_verified
  } catch (e) { console.error(e) }
}

onMounted(fetchRiders)
</script>
