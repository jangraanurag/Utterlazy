<template>
  <div class="bg-background text-on-surface min-h-screen">
    <nav class="fixed top-0 left-0 w-full flex justify-between items-center px-8 py-4 bg-[#fdf9f4]/60 backdrop-blur-xl z-50">
      <div class="flex items-center gap-6">
        <router-link to="/" class="font-serif text-3xl italic font-bold text-primary">UtterLazy</router-link>
        <router-link to="/" class="flex items-center gap-1 text-on-surface-variant hover:text-primary transition-colors font-medium">
          <span class="material-symbols-outlined text-[18px]">arrow_back</span> Home
        </router-link>
      </div>
    </nav>

    <main class="pt-28 px-8 max-w-3xl mx-auto pb-16">
      <header class="mb-10">
        <h1 class="font-serif text-4xl font-bold text-on-surface mb-2">Account Settings</h1>
        <p class="text-on-surface-variant font-medium">Manage your profile and preferences.</p>
      </header>

      <div v-if="loading" class="py-20 text-center text-on-surface-variant">
        <span class="material-symbols-outlined text-5xl animate-spin block mb-4">progress_activity</span>
        <p class="text-lg">Loading your account...</p>
      </div>

      <div v-else class="space-y-8">
        <!-- Success / Error messages -->
        <div v-if="successMsg" class="p-4 rounded-xl bg-secondary-container text-on-secondary-container font-medium flex items-center gap-2">
          <span class="material-symbols-outlined text-[18px]">check_circle</span> {{ successMsg }}
        </div>
        <div v-if="errorMsg" class="p-4 rounded-xl bg-error-container text-on-error-container font-medium flex items-center gap-2">
          <span class="material-symbols-outlined text-[18px]">error</span> {{ errorMsg }}
        </div>

        <!-- Profile Card -->
        <section class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 overflow-hidden">
          <div class="p-8 bg-primary text-on-primary flex items-center gap-6">
            <div class="w-20 h-20 rounded-full bg-white/20 flex items-center justify-center text-4xl font-serif font-bold">
              {{ user.name?.charAt(0) || 'K' }}
            </div>
            <div>
              <h2 class="font-serif text-2xl font-bold">{{ user.name }}</h2>
              <p class="opacity-80 text-sm">{{ user.email }}</p>
              <div class="flex items-center gap-1 mt-2 text-sm font-bold opacity-90">
                <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">account_balance_wallet</span>
                Wallet Balance: ₹{{ walletBalance.toFixed(2) }}
              </div>
            </div>
          </div>
        </section>

        <!-- Personal Details Form -->
        <section class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 p-8">
          <h3 class="font-serif text-xl font-bold text-on-surface mb-6 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">person</span> Personal Details
          </h3>
          <div class="space-y-5">
            <div>
              <label class="block text-sm font-bold text-on-surface-variant mb-2">Full Name</label>
              <input v-model="form.name" type="text" class="w-full px-4 py-3 rounded-xl bg-surface-container border border-outline-variant/30 text-on-surface font-medium focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" />
            </div>
            <div>
              <label class="block text-sm font-bold text-on-surface-variant mb-2">Email Address</label>
              <input v-model="form.email" type="email" class="w-full px-4 py-3 rounded-xl bg-surface-container border border-outline-variant/30 text-on-surface font-medium focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" />
            </div>
            <div>
              <label class="block text-sm font-bold text-on-surface-variant mb-2">Phone Number</label>
              <input v-model="form.phone" type="tel" placeholder="+91 98765 43210" class="w-full px-4 py-3 rounded-xl bg-surface-container border border-outline-variant/30 text-on-surface font-medium focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" />
            </div>
            <button @click="saveProfile" :disabled="profileSaving" class="primary-gradient text-on-primary font-bold py-3 px-8 rounded-xl shadow-lg shadow-primary/20 flex items-center gap-2 transition-all active:scale-95 disabled:opacity-70">
              <span class="material-symbols-outlined">save</span>
              {{ profileSaving ? 'Saving...' : 'Save Changes' }}
            </button>
          </div>
        </section>

        <!-- Wallet Management -->
        <section class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 p-8">
          <h3 class="font-serif text-xl font-bold text-on-surface mb-6 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">account_balance_wallet</span> UtterLazy Wallet
          </h3>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Balance Card -->
            <div class="bg-primary/5 border border-primary/20 rounded-2xl p-6 flex flex-col justify-between">
              <div>
                <p class="text-[10px] font-black text-primary uppercase tracking-[0.2em] mb-1">Current Balance</p>
                <p class="font-serif text-4xl font-bold text-on-surface">₹{{ walletBalance.toFixed(2) }}</p>
              </div>
              
              <div class="mt-6 space-y-4">
                <!-- Add Money -->
                <div class="flex gap-2">
                  <input v-model="depositAmount" type="number" placeholder="Enter amount (₹)" min="1" class="flex-1 px-4 py-3 rounded-xl bg-white border border-outline-variant/30 text-on-surface font-medium focus:outline-none focus:ring-2 focus:ring-primary/30 text-sm font-bold" />
                  <button @click="addMoney" :disabled="depositLoading || depositAmount <= 0" class="primary-gradient text-on-primary font-bold px-6 py-3 rounded-xl shadow-md shadow-primary/10 active:scale-95 disabled:opacity-50 text-xs uppercase tracking-wider shrink-0">
                    {{ depositLoading ? 'Adding...' : 'Add Cash' }}
                  </button>
                </div>
              </div>
            </div>

            <!-- Transfer Money -->
            <div class="bg-surface-container rounded-2xl p-6 flex flex-col justify-between border border-outline-variant/10">
              <div>
                <p class="text-[10px] font-black text-secondary uppercase tracking-[0.2em] mb-1">Send Money to Friend</p>
                <p class="text-xs text-on-surface-variant mb-4 font-bold">Transfer wallet balance instantly using their Email or Phone.</p>
              </div>
              
              <div class="space-y-3">
                <input v-model="transferRecipient" type="text" placeholder="Recipient's Email or Phone" class="w-full px-4 py-3 rounded-xl bg-white border border-outline-variant/30 text-on-surface font-medium focus:outline-none focus:ring-2 focus:ring-primary/30 text-sm font-bold" />
                <div class="flex gap-2">
                  <input v-model="transferAmount" type="number" placeholder="Amount (₹)" min="1" class="w-28 px-4 py-3 rounded-xl bg-white border border-outline-variant/30 text-on-surface font-medium focus:outline-none focus:ring-2 focus:ring-primary/30 text-sm font-bold" />
                  <button @click="sendMoney" :disabled="transferLoading || transferAmount <= 0 || !transferRecipient" class="bg-on-surface text-surface font-bold flex-1 py-3 rounded-xl active:scale-95 disabled:opacity-50 text-xs uppercase tracking-wider">
                    {{ transferLoading ? 'Sending...' : 'Send Cash' }}
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Wallet Transaction History -->
          <div class="mt-8">
            <h4 class="font-bold text-on-surface text-sm uppercase tracking-wider mb-4">Transaction History</h4>
            <div v-if="walletTransactions.length === 0" class="text-center py-6 text-on-surface-variant text-sm font-medium">
              No transactions recorded yet.
            </div>
            <div v-else class="space-y-3 max-h-60 overflow-y-auto pr-2">
              <div v-for="t in walletTransactions" :key="t.id" class="flex justify-between items-center py-3 border-b border-outline-variant/10 last:border-0">
                <div>
                  <p class="font-bold text-sm text-on-surface">{{ t.description }}</p>
                  <p class="text-[10px] text-on-surface-variant mt-0.5">{{ new Date(t.created_at).toLocaleString() }}</p>
                </div>
                <span :class="t.type === 'credit' ? 'text-green-600' : 'text-red-600'" class="font-bold text-sm">
                  {{ t.type === 'credit' ? '+' : '-' }}₹{{ parseFloat(t.amount).toFixed(2) }}
                </span>
              </div>
            </div>
          </div>
        </section>

        <!-- Saved Addresses -->
        <section class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 p-8">
          <div class="flex justify-between items-center mb-6">
            <h3 class="font-serif text-xl font-bold text-on-surface flex items-center gap-2">
              <span class="material-symbols-outlined text-primary">location_on</span> Saved Addresses
            </h3>
            <button @click="openAddressForm()" class="flex items-center gap-1 text-primary font-bold text-sm hover:bg-primary/10 px-4 py-2 rounded-xl transition-colors">
              <span class="material-symbols-outlined text-[18px]">add</span> Add New
            </button>
          </div>

          <!-- Address Form Modal -->
          <div v-if="showAddressForm" class="mb-6 p-6 rounded-xl bg-surface-container border border-outline-variant/30 space-y-4">
            <h4 class="font-bold text-on-surface">{{ editingAddressId ? 'Edit Address' : 'Add New Address' }}</h4>
            <!-- Ola Maps Location Search -->
            <div>
              <label class="block text-sm font-bold text-on-surface-variant mb-2">📍 Search Location</label>
              <OlaMapPicker placeholder="Search for area, street, landmark..." @locationSelected="onMapLocationSelected" />
            </div>
            <!-- Label Pills -->
            <div>
              <label class="block text-sm font-bold text-on-surface-variant mb-2">Label</label>
              <div class="flex gap-2 flex-wrap">
                <button v-for="l in ['Home','Work','Hotel','Other']" :key="l" @click="addrForm.label = l" :class="addrForm.label === l ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface'" class="px-4 py-2 rounded-full text-sm font-bold transition-colors flex items-center gap-1">
                  <span class="material-symbols-outlined text-[16px]">{{ l === 'Home' ? 'home' : l === 'Work' ? 'work' : l === 'Hotel' ? 'hotel' : 'pin_drop' }}</span> {{ l }}
                </button>
              </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-bold text-on-surface-variant mb-1">Flat / House / Building</label>
                <input v-model="addrForm.flat_building" type="text" placeholder="e.g. Flat 402, Tower B" class="w-full px-4 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-on-surface font-medium focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" />
              </div>
              <div>
                <label class="block text-sm font-bold text-on-surface-variant mb-1">Street / Area</label>
                <input v-model="addrForm.street" type="text" placeholder="e.g. Gomti Nagar, Sector 5" class="w-full px-4 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-on-surface font-medium focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" />
              </div>
              <div>
                <label class="block text-sm font-bold text-on-surface-variant mb-1">Landmark</label>
                <input v-model="addrForm.landmark" type="text" placeholder="e.g. Near Phoenix Mall" class="w-full px-4 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-on-surface font-medium focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" />
              </div>
              <div>
                <label class="block text-sm font-bold text-on-surface-variant mb-1">Location / Area Name</label>
                <input v-model="addrForm.location_name" type="text" placeholder="e.g. Gomti Nagar" class="w-full px-4 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-on-surface font-medium focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" />
              </div>
              <div>
                <label class="block text-sm font-bold text-on-surface-variant mb-1">City</label>
                <input v-model="addrForm.city" type="text" class="w-full px-4 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-on-surface font-medium focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" />
              </div>
              <div>
                <label class="block text-sm font-bold text-on-surface-variant mb-1">Pincode</label>
                <input v-model="addrForm.pincode" type="text" maxlength="6" placeholder="226010" class="w-full px-4 py-3 rounded-xl bg-surface-container-lowest border border-outline-variant/30 text-on-surface font-medium focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" />
              </div>
            </div>
            <div class="flex gap-3 pt-2">
              <button @click="saveAddress" :disabled="addrSaving" class="primary-gradient text-on-primary font-bold py-3 px-6 rounded-xl shadow-lg shadow-primary/20 flex items-center gap-2 active:scale-95 disabled:opacity-70">
                <span class="material-symbols-outlined text-[18px]">{{ editingAddressId ? 'save' : 'add_location' }}</span>
                {{ addrSaving ? 'Saving...' : (editingAddressId ? 'Update Address' : 'Save Address') }}
              </button>
              <button @click="showAddressForm = false" class="bg-surface-container-high text-on-surface font-bold py-3 px-6 rounded-xl hover:bg-surface-container-highest transition-colors">Cancel</button>
            </div>
          </div>

          <!-- Address List -->
          <div v-if="addresses.length === 0 && !showAddressForm" class="py-10 text-center">
            <span class="material-symbols-outlined text-4xl text-on-surface-variant opacity-30 block mb-3">add_location</span>
            <p class="text-on-surface-variant">No saved addresses yet. Add your first delivery address!</p>
          </div>
          <div v-else class="space-y-4">
            <div v-for="addr in addresses" :key="addr.id" :class="addr.is_default ? 'border-primary/40 bg-primary/5' : 'border-outline-variant/20'" class="rounded-xl border p-5 flex gap-4 items-start group hover:shadow-md transition-all">
              <div class="w-12 h-12 rounded-full flex items-center justify-center shrink-0" :class="addr.is_default ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface-variant'">
                <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">{{ addr.label === 'Home' ? 'home' : addr.label === 'Work' ? 'work' : addr.label === 'Hotel' ? 'hotel' : 'pin_drop' }}</span>
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1">
                  <span class="font-bold text-on-surface">{{ addr.label }}</span>
                  <span v-if="addr.is_default" class="text-[10px] uppercase tracking-widest font-bold bg-primary/10 text-primary px-2 py-0.5 rounded-full">Default</span>
                </div>
                <p class="text-sm text-on-surface-variant leading-relaxed">{{ formatAddress(addr) }}</p>
                <div v-if="addr.location_name" class="flex items-center gap-1 mt-1 text-xs font-bold text-primary">
                  <span class="material-symbols-outlined text-[14px]">my_location</span> {{ addr.location_name }}
                </div>
              </div>
              <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <button v-if="!addr.is_default" @click="handleSetDefault(addr.id)" title="Set as default" class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-primary/10 text-on-surface-variant hover:text-primary transition-colors">
                  <span class="material-symbols-outlined text-[18px]">check_circle</span>
                </button>
                <button @click="openEditAddress(addr)" title="Edit" class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-primary/10 text-on-surface-variant hover:text-primary transition-colors">
                  <span class="material-symbols-outlined text-[18px]">edit</span>
                </button>
                <button @click="handleDeleteAddress(addr.id)" title="Delete" class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-error/10 text-on-surface-variant hover:text-error transition-colors">
                  <span class="material-symbols-outlined text-[18px]">delete</span>
                </button>
              </div>
            </div>
          </div>
        </section>

        <!-- Security Section -->
        <section class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 p-8">
          <h3 class="font-serif text-xl font-bold text-on-surface mb-6 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">lock</span> Security
          </h3>
          <div class="space-y-5">
            <div>
              <label class="block text-sm font-bold text-on-surface-variant mb-2">Current Password</label>
              <input v-model="pwForm.current_password" type="password" placeholder="••••••••" class="w-full px-4 py-3 rounded-xl bg-surface-container border border-outline-variant/30 text-on-surface font-medium focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" />
            </div>
            <div>
              <label class="block text-sm font-bold text-on-surface-variant mb-2">New Password</label>
              <input v-model="pwForm.new_password" type="password" placeholder="••••••••" class="w-full px-4 py-3 rounded-xl bg-surface-container border border-outline-variant/30 text-on-surface font-medium focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" />
            </div>
            <div>
              <label class="block text-sm font-bold text-on-surface-variant mb-2">Confirm New Password</label>
              <input v-model="pwForm.new_password_confirmation" type="password" placeholder="••••••••" class="w-full px-4 py-3 rounded-xl bg-surface-container border border-outline-variant/30 text-on-surface font-medium focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all" />
            </div>
            <button @click="handleChangePassword" :disabled="pwSaving" class="bg-surface-container-high text-on-surface font-bold py-3 px-8 rounded-xl flex items-center gap-2 hover:bg-surface-container-highest transition-colors active:scale-95 disabled:opacity-70">
              <span class="material-symbols-outlined">key</span>
              {{ pwSaving ? 'Updating...' : 'Update Password' }}
            </button>
          </div>
        </section>

        <!-- My Complaints -->
        <section class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 p-8">
          <router-link to="/my-complaints" class="flex items-center gap-4 group">
            <div class="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
              <span class="material-symbols-outlined text-primary">report_problem</span>
            </div>
            <div class="flex-1">
              <p class="font-bold text-on-surface group-hover:text-primary transition-colors">My Complaints</p>
              <p class="text-sm text-on-surface-variant">View and track your reported issues</p>
            </div>
            <span class="material-symbols-outlined text-on-surface-variant">chevron_right</span>
          </router-link>
        </section>

        <!-- Preferences -->
        <section class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 p-8">
          <h3 class="font-serif text-xl font-bold text-on-surface mb-6 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">tune</span> Preferences
          </h3>
          <div class="space-y-4">
            <router-link to="/language" class="flex items-center justify-between p-4 rounded-xl border border-outline-variant/20 hover:border-primary/40 hover:bg-primary/5 transition-all cursor-pointer">
              <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-primary">language</span>
                <div>
                  <p class="font-bold text-on-surface">Language</p>
                  <p class="text-sm text-on-surface-variant">Choose your preferred language</p>
                </div>
              </div>
              <span class="material-symbols-outlined text-on-surface-variant">chevron_right</span>
            </router-link>
            <label class="flex items-center justify-between cursor-pointer">
              <div>
                <p class="font-bold text-on-surface">Push Notifications</p>
                <p class="text-sm text-on-surface-variant">Get notified about orders and promotions</p>
              </div>
              <div @click="prefs.pushNotifications = !prefs.pushNotifications" :class="prefs.pushNotifications ? 'bg-primary' : 'bg-outline-variant'" class="w-12 h-7 rounded-full relative transition-colors cursor-pointer">
                <div :class="prefs.pushNotifications ? 'translate-x-5' : 'translate-x-0'" class="w-6 h-6 bg-white rounded-full shadow absolute top-0.5 left-0.5 transition-transform"></div>
              </div>
            </label>
            <!-- Coins Alerts Removed -->
          </div>
        </section>
      </div>
    </main>

    <Footer />
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { getMe, updateProfile, changePassword, getAddresses, addAddress, updateAddress, deleteAddress, setDefaultAddress, getWalletData, addWalletMoney, transferWalletMoney } from '../api'
import Footer from '../components/Footer.vue'
import OlaMapPicker from '../components/OlaMapPicker.vue'

const user = ref({})
const loading = ref(true)
const profileSaving = ref(false)
const pwSaving = ref(false)
const addrSaving = ref(false)
const successMsg = ref('')
const errorMsg = ref('')

// Wallet state
const walletBalance = ref(0.00)
const walletTransactions = ref([])
const depositAmount = ref('')
const depositLoading = ref(false)
const transferRecipient = ref('')
const transferAmount = ref('')
const transferLoading = ref(false)

const fetchWallet = async () => {
  try {
    const data = await getWalletData()
    walletBalance.value = data.balance
    walletTransactions.value = data.transactions || []
  } catch (e) {
    console.error('Failed to fetch wallet:', e)
  }
}

const addMoney = async () => {
  if (depositAmount.value <= 0) return
  depositLoading.value = true
  errorMsg.value = ''
  successMsg.value = ''
  try {
    const res = await addWalletMoney(depositAmount.value)
    walletBalance.value = res.balance
    depositAmount.value = ''
    successMsg.value = res.message || 'Money deposited successfully!'
    await fetchWallet()
  } catch (e) {
    errorMsg.value = e.message || 'Failed to deposit money.'
  } finally {
    depositLoading.value = false
    clearMessages()
  }
}

const sendMoney = async () => {
  if (transferAmount.value <= 0 || !transferRecipient.value) return
  transferLoading.value = true
  errorMsg.value = ''
  successMsg.value = ''
  try {
    const res = await transferWalletMoney(transferRecipient.value, transferAmount.value)
    walletBalance.value = res.balance
    transferAmount.value = ''
    transferRecipient.value = ''
    successMsg.value = res.message || 'Money transferred successfully!'
    await fetchWallet()
  } catch (e) {
    errorMsg.value = e.message || 'Failed to transfer money.'
  } finally {
    transferLoading.value = false
    clearMessages()
  }
}

const form = reactive({ name: '', email: '', phone: '' })
const pwForm = reactive({ current_password: '', new_password: '', new_password_confirmation: '' })
const prefs = reactive({ pushNotifications: true, coinAlerts: true })

// Address state
const addresses = ref([])
const showAddressForm = ref(false)
const editingAddressId = ref(null)
const addrForm = reactive({ label: 'Home', flat_building: '', street: '', landmark: '', location_name: '', city: 'Bhiwani', pincode: '', latitude: null, longitude: null })

const clearMessages = () => {
  setTimeout(() => { successMsg.value = ''; errorMsg.value = '' }, 5000)
}

const onMapLocationSelected = (loc) => {
  if (loc.components) {
    addrForm.street = loc.secondaryText || loc.formatted_address || ''
    addrForm.location_name = loc.components.area || loc.mainText || ''
    addrForm.city = loc.components.city || 'Bhiwani'
    addrForm.pincode = loc.components.pincode || ''
  } else {
    addrForm.street = loc.secondaryText || ''
    addrForm.location_name = loc.mainText || ''
  }
  addrForm.latitude = loc.lat || null
  addrForm.longitude = loc.lng || null
}

const resetAddrForm = () => {
  addrForm.label = 'Home'; addrForm.flat_building = ''; addrForm.street = ''; addrForm.landmark = ''
  addrForm.location_name = ''; addrForm.city = 'Bhiwani'; addrForm.pincode = ''; addrForm.latitude = null; addrForm.longitude = null
  editingAddressId.value = null
}

const openAddressForm = () => { resetAddrForm(); showAddressForm.value = true }

const openEditAddress = (addr) => {
  editingAddressId.value = addr.id
  addrForm.label = addr.label; addrForm.flat_building = addr.flat_building || ''; addrForm.street = addr.street || ''
  addrForm.landmark = addr.landmark || ''; addrForm.location_name = addr.location_name || ''
  addrForm.city = addr.city || 'Bhiwani'; addrForm.pincode = addr.pincode || ''
  showAddressForm.value = true
}

const formatAddress = (addr) => {
  return [addr.flat_building, addr.street, addr.landmark ? `Near ${addr.landmark}` : null, addr.city, addr.pincode].filter(Boolean).join(', ')
}

const fetchAddresses = async () => {
  try { const res = await getAddresses(); addresses.value = res.addresses || [] } catch (e) { console.error(e) }
}

const saveAddress = async () => {
  addrSaving.value = true; errorMsg.value = ''
  try {
    if (editingAddressId.value) {
      await updateAddress(editingAddressId.value, { ...addrForm })
      successMsg.value = 'Address updated!'
    } else {
      await addAddress({ ...addrForm })
      successMsg.value = 'Address added!'
    }
    showAddressForm.value = false; resetAddrForm(); await fetchAddresses()
  } catch (e) {
    errorMsg.value = e.errors ? Object.values(e.errors).flat().join(' ') : 'Failed to save address.'
  } finally { addrSaving.value = false; clearMessages() }
}

const handleDeleteAddress = async (id) => {
  if (!confirm('Delete this address?')) return
  try { await deleteAddress(id); await fetchAddresses(); successMsg.value = 'Address deleted.' } catch (e) { errorMsg.value = 'Failed to delete.' }
  clearMessages()
}

const handleSetDefault = async (id) => {
  try { await setDefaultAddress(id); await fetchAddresses(); successMsg.value = 'Default address updated.' } catch (e) { errorMsg.value = 'Failed to update default.' }
  clearMessages()
}

onMounted(async () => {
  try {
    const data = await getMe()
    user.value = data.user
    form.name = data.user.name; form.email = data.user.email; form.phone = data.user.phone || ''
  } catch (e) { console.error(e) }
  finally { loading.value = false }
  fetchAddresses()
  fetchWallet()
})

const saveProfile = async () => {
  profileSaving.value = true; errorMsg.value = ''; successMsg.value = ''
  try {
    const res = await updateProfile(form); user.value = res.user
    successMsg.value = 'Profile updated successfully!'
  } catch (e) {
    errorMsg.value = e.errors ? Object.values(e.errors).flat().join(' ') : 'Failed to update profile.'
  } finally { profileSaving.value = false; clearMessages() }
}

const handleChangePassword = async () => {
  if (!pwForm.current_password || !pwForm.new_password) { errorMsg.value = 'Please fill all password fields.'; clearMessages(); return }
  pwSaving.value = true; errorMsg.value = ''; successMsg.value = ''
  try {
    await changePassword(pwForm); successMsg.value = 'Password changed successfully!'
    pwForm.current_password = ''; pwForm.new_password = ''; pwForm.new_password_confirmation = ''
  } catch (e) {
    errorMsg.value = e.errors ? Object.values(e.errors).flat().join(' ') : 'Failed to change password.'
  } finally { pwSaving.value = false; clearMessages() }
}
</script>
