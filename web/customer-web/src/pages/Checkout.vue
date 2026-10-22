<template>
  <div class="bg-gray-50 min-h-screen text-on-surface">
    <!-- Clean Header -->
    <header class="bg-white sticky top-0 z-50 border-b border-gray-100 h-20">
      <div class="max-w-[1200px] mx-auto px-6 h-full flex items-center justify-between">
        <div class="flex items-center gap-8">
           <router-link to="/" class="flex items-center gap-2 group">
              <div class="w-8 h-8 bg-primary rounded-xl flex items-center justify-center">
                 <span class="material-symbols-outlined text-white text-xl">restaurant_menu</span>
              </div>
              <span class="font-display text-xl font-black tracking-tighter">UTTERLAZY</span>
           </router-link>
           <div class="h-6 w-[1px] bg-gray-200"></div>
           <h1 class="text-xs font-black tracking-widest uppercase text-gray-400">SECURE CHECKOUT</h1>
        </div>
        <div class="flex items-center gap-4">
           <span class="material-symbols-outlined text-gray-400">support_agent</span>
           <span class="text-xs font-black tracking-widest uppercase text-gray-400">HELP</span>
        </div>
      </div>
    </header>

    <main class="max-w-[1200px] mx-auto px-6 py-10 min-h-[70vh] flex flex-col items-center justify-center">
      <!-- Empty Cart State -->
      <div v-if="cart.items.length === 0" class="py-20 text-center max-w-xl mx-auto">
         <div class="w-40 h-40 bg-white rounded-[3rem] shadow-soft border border-gray-50 flex items-center justify-center mx-auto mb-10">
            <span class="material-symbols-outlined text-7xl text-primary/20">shopping_basket</span>
         </div>
         <h2 class="text-4xl font-black text-on-surface mb-6 leading-tight tracking-tighter uppercase">Your manifest is empty</h2>
         <p class="text-gray-400 text-lg font-medium mb-12 uppercase tracking-widest text-[10px]">No items detected. First select your craving to initiate the mission.</p>
         <button @click="router.push('/')" class="bg-on-surface text-white px-12 py-5 rounded-2xl font-black text-xs tracking-[0.2em] uppercase shadow-xl shadow-gray-200 hover:scale-105 active:scale-95 transition-all">
           DISCOVER CRAVINGS
         </button>
      </div>

      <!-- Checkout Grid -->
      <div v-else class="w-full grid grid-cols-1 lg:grid-cols-12 gap-10">
      <!-- Left Column: Checkout Sections -->
      <div class="lg:col-span-8 space-y-6">
        
        <!-- Section 1: Account -->
        <section class="bg-white p-10 rounded-3xl border border-gray-100 flex gap-8 items-start relative">
           <div class="w-12 h-12 bg-on-surface text-white rounded-2xl flex items-center justify-center shrink-0">
              <span class="material-symbols-outlined">person</span>
           </div>
           <div class="flex-1">
              <h2 class="text-xl font-black tracking-tight mb-2 uppercase">Account</h2>
              <p class="font-bold text-gray-600">{{ userProfile?.user?.name || 'Guest User' }} | {{ userProfile?.user?.email || 'Login to continue' }}</p>
           </div>
           <div class="absolute right-10 top-10">
              <span class="material-symbols-outlined text-green-500 font-bold">check_circle</span>
           </div>
        </section>

        <!-- Section 2: Delivery Address -->
        <section class="bg-white p-10 rounded-3xl border border-gray-100 relative overflow-hidden">
           <div class="flex gap-8 items-start mb-10">
              <div class="w-12 h-12 bg-on-surface text-white rounded-2xl flex items-center justify-center shrink-0">
                 <span class="material-symbols-outlined">location_on</span>
              </div>
              <div class="flex-1">
                 <h2 class="text-xl font-black tracking-tight mb-1 uppercase">Select Delivery Address</h2>
                 <p class="text-sm font-bold text-gray-400 uppercase tracking-widest">Saved coordinates in your sector</p>
              </div>
           </div>

           <!-- Address Grid -->
           <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
              <div v-for="addr in addresses" :key="addr.id" 
                @click="selectAddress(addr)"
                :class="selectedAddress?.id === addr.id ? 'border-primary ring-1 ring-primary' : 'border-gray-100 hover:border-gray-200'"
                class="border-2 p-6 rounded-2xl cursor-pointer transition-all group relative overflow-hidden">
                <div class="flex items-center gap-4 mb-4">
                   <span class="material-symbols-outlined text-gray-400 group-hover:text-primary transition-colors">{{ addr.label === 'Home' ? 'home' : 'work' }}</span>
                   <h3 class="font-black text-sm uppercase tracking-widest">{{ addr.label }}</h3>
                </div>
                <p class="text-[11px] font-bold text-gray-500 leading-relaxed mb-6">{{ formatAddr(addr) }}</p>
                <button class="bg-green-600 text-white px-8 py-2.5 rounded-lg font-black text-[10px] tracking-widest uppercase shadow-lg shadow-green-100 group-hover:scale-105 transition-all">DELIVER HERE</button>
                <div v-if="selectedAddress?.id === addr.id" class="absolute -top-6 -right-6 w-12 h-12 bg-primary rotate-45"></div>
              </div>

              <!-- Add New -->
              <div @click="showNewAddrForm = true" class="border-2 border-dashed border-gray-200 p-6 rounded-2xl cursor-pointer hover:border-primary transition-all flex flex-col items-center justify-center gap-4 text-gray-400 hover:text-primary group">
                 <span class="material-symbols-outlined text-3xl group-hover:scale-110 transition-transform">add_location_alt</span>
                 <p class="font-black text-[10px] tracking-[0.2em] uppercase">Add New Address</p>
              </div>
           </div>
        </section>

        <!-- Section 3: Payment -->
        <section class="bg-white p-10 rounded-3xl border border-gray-100">
           <div class="flex gap-8 items-start mb-10">
              <div class="w-12 h-12 bg-gray-100 text-gray-400 rounded-2xl flex items-center justify-center shrink-0">
                 <span class="material-symbols-outlined">payments</span>
              </div>
              <div class="flex-1">
                 <h2 class="text-xl font-black tracking-tight mb-1 uppercase text-gray-400">Payment</h2>
                 <p class="text-sm font-bold text-gray-300 uppercase tracking-widest">Select authorization method</p>
              </div>
           </div>
           
           <div class="space-y-4">
              <button @click="paymentMode = 'cod'" 
                :class="paymentMode === 'cod' ? 'border-primary bg-orange-50/20' : 'border-gray-100'"
                class="w-full flex items-center justify-between p-6 rounded-2xl border-2 transition-all">
                <div class="flex items-center gap-4">
                   <span class="material-symbols-outlined text-gray-400">payments</span>
                   <span class="font-black text-sm tracking-widest uppercase">Cash on Delivery</span>
                </div>
                <div v-if="paymentMode === 'cod'" class="w-5 h-5 bg-primary rounded-full flex items-center justify-center">
                   <span class="material-symbols-outlined text-white text-[14px]">check</span>
                </div>
              </button>

              <button @click="paymentMode = 'online'"
                :class="paymentMode === 'online' ? 'border-primary bg-orange-50/20' : 'border-gray-100'"
                class="w-full flex items-center justify-between p-6 rounded-2xl border-2 transition-all">
                <div class="flex items-center gap-4">
                   <span class="material-symbols-outlined text-gray-400">account_balance</span>
                   <span class="font-black text-sm tracking-widest uppercase">Online Payment / Cards</span>
                </div>
                <div v-if="paymentMode === 'online'" class="w-5 h-5 bg-primary rounded-full flex items-center justify-center">
                   <span class="material-symbols-outlined text-white text-[14px]">check</span>
                </div>
              </button>

              <button @click="walletBalance >= parseFloat(finalTotal) ? paymentMode = 'wallet' : null"
                :class="[
                  paymentMode === 'wallet' ? 'border-primary bg-orange-50/20' : 'border-gray-100',
                  walletBalance < parseFloat(finalTotal) ? 'opacity-50 cursor-not-allowed' : ''
                ]"
                class="w-full flex items-center justify-between p-6 rounded-2xl border-2 transition-all">
                <div class="flex items-center gap-4">
                   <span class="material-symbols-outlined text-gray-400">account_balance_wallet</span>
                   <div class="text-left">
                      <span class="font-black text-sm tracking-widest uppercase block">UtterLazy Wallet</span>
                      <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider block">
                        Available Balance: ₹{{ walletBalance.toFixed(2) }}
                        <span v-if="walletBalance < parseFloat(finalTotal)" class="text-red-500 font-black ml-1">(INSUFFICIENT)</span>
                      </span>
                   </div>
                </div>
                <div v-if="paymentMode === 'wallet'" class="w-5 h-5 bg-primary rounded-full flex items-center justify-center">
                   <span class="material-symbols-outlined text-white text-[14px]">check</span>
                </div>
              </button>
           </div>
        </section>

         <!-- Business Ordering Section -->
         <section class="bg-white border border-gray-100 rounded-3xl p-8 shadow-soft mb-8">
            <div class="flex items-center justify-between">
               <div class="flex items-center gap-4">
                  <div class="w-10 h-10 rounded-full bg-primary/5 flex items-center justify-center text-primary">
                     <span class="material-symbols-outlined text-[20px]">business_center</span>
                  </div>
                  <div class="text-left">
                     <h2 class="font-black text-sm tracking-widest uppercase">Business Ordering</h2>
                     <p class="text-[10px] font-bold text-gray-400 tracking-wider uppercase">Claim GST Input Tax Credit</p>
                  </div>
               </div>
               <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="isBusinessOrder" class="sr-only peer" />
                  <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-primary"></div>
               </label>
            </div>

            <div v-if="isBusinessOrder" class="space-y-6 pt-6 mt-6 border-t border-gray-100">
               <div>
                  <label class="block text-[10px] font-black text-gray-400 tracking-widest uppercase mb-2">Company Name</label>
                  <input type="text" v-model="companyName" placeholder="e.g. Acme Corporation" 
                     class="w-full px-5 py-4 bg-gray-50 rounded-2xl text-xs font-bold border border-transparent focus:border-primary focus:bg-white outline-none transition-all" />
               </div>
               <div>
                  <label class="block text-[10px] font-black text-gray-400 tracking-widest uppercase mb-2">GST Number</label>
                  <input type="text" v-model="companyGst" placeholder="e.g. 07AAAAA1111A1Z1" 
                     class="w-full px-5 py-4 bg-gray-50 rounded-2xl text-xs font-bold border border-transparent focus:border-primary focus:bg-white outline-none transition-all" />
               </div>
            </div>
         </section>
      </div>

      <!-- Right Column: Bill & Summary -->
      <aside class="lg:col-span-4 h-fit sticky top-28 space-y-6">
        
        <!-- Bill Details -->
        <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-soft">
           <div class="flex items-center gap-4 mb-8">
              <div class="w-16 h-16 rounded-2xl overflow-hidden border border-gray-100">
                 <img :src="cart.items[0]?.image_url" class="w-full h-full object-cover" />
              </div>
              <div class="flex-1 min-w-0">
                 <h3 class="font-black text-sm truncate uppercase tracking-tight">{{ cart.restaurantName || 'Selected Artisan' }}</h3>
                 <p class="text-[10px] font-bold text-gray-400 tracking-widest uppercase">{{ cart.items.length }} ITEMS MANIFEST</p>
              </div>
           </div>

           <!-- Item List (Mini) -->
           <div class="space-y-3 mb-8">
              <div v-for="item in cart.items" :key="item.id" class="flex justify-between text-[11px] font-bold">
                 <div class="flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full" :class="item.is_veg ? 'bg-green-600' : 'bg-red-600'"></span>
                    <span class="text-gray-600">{{ item.name }} x {{ item.quantity }}</span>
                 </div>
                 <span class="text-gray-400 font-black">₹{{ item.price * item.quantity }}</span>
              </div>
           </div>

           <hr class="border-gray-50 mb-8" />

           <!-- Bill Summary -->
           <div class="space-y-4">
              <div class="flex justify-between text-[11px] font-bold uppercase tracking-widest">
                 <span class="text-gray-400">Item Total</span>
                 <span class="text-on-surface font-black">₹{{ cartTotal }}</span>
              </div>
              <div class="flex justify-between text-[11px] font-bold uppercase tracking-widest">
                 <span class="text-gray-400">Delivery Fee | 2.4 km</span>
                 <span class="text-on-surface font-black">₹39</span>
              </div>
              <div v-if="appliedCoupon" class="flex justify-between text-[11px] font-black uppercase tracking-widest text-green-600">
                 <span>Coupon Applied</span>
                 <span>-₹{{ appliedCoupon.discount_amount }}</span>
              </div>
              <div class="flex justify-between text-[11px] font-bold uppercase tracking-widest">
                 <span class="text-gray-400">Taxes & Platform Fee</span>
                 <span class="text-on-surface font-black">₹24</span>
              </div>
              
              <div class="pt-6 border-t-2 border-dashed border-gray-100 flex justify-between items-end">
                 <span class="text-xs font-black tracking-[0.2em] uppercase">Total Payable</span>
                 <span class="text-3xl font-black text-on-surface tracking-tighter">₹{{ finalTotal }}</span>
              </div>
           </div>

           <!-- Auto Coupon Success -->
           <div v-if="appliedCoupon" class="mt-8 bg-green-50 border border-green-100 rounded-2xl p-4 flex items-center gap-4">
              <span class="material-symbols-outlined text-green-600 font-bold">verified</span>
              <p class="text-[9px] font-black text-green-700 tracking-[0.15em] uppercase">₹{{ appliedCoupon.discount_amount }} Smart Savings Applied Successfully</p>
           </div>

           <!-- Distance Warning Banner -->
           <div v-if="isOutOfRange" class="mt-8 bg-red-50 border border-red-100 rounded-2xl p-4 flex items-center gap-4">
              <span class="material-symbols-outlined text-red-600 font-bold">distance</span>
              <p class="text-[10px] font-black text-red-700 tracking-[0.1em] uppercase">
                 Selected address is {{ calculatedDistance.toFixed(1) }} km away. Delivery is capped at 10 km.
              </p>
           </div>

           <!-- Error State Banner -->
           <div v-if="checkoutError" class="mt-8 bg-red-50 border border-red-100 rounded-2xl p-4 flex items-center gap-4">
              <span class="material-symbols-outlined text-red-600 font-bold">error</span>
              <p class="text-[10px] font-black text-red-700 tracking-[0.1em] uppercase">{{ checkoutError }}</p>
           </div>

           <button @click="placeOrder" :disabled="isProcessing || !selectedAddress || isOutOfRange" 
             class="w-full bg-primary text-white py-5 rounded-2xl font-black text-xs tracking-[0.2em] uppercase mt-8 shadow-xl shadow-primary/20 hover:scale-[1.02] active:scale-[0.98] transition-all disabled:opacity-50"
             :class="{ 'opacity-40 cursor-not-allowed': !selectedAddress || isOutOfRange }">
             {{ isProcessing ? 'AUTHORIZING...' : 'PLACE MISSION' }}
           </button>
        </div>

        <!-- Nudges -->
        <div class="bg-orange-50/50 rounded-3xl p-6 border border-orange-100 flex items-center gap-4">
           <span class="material-symbols-outlined text-primary text-3xl">sports_esports</span>
           <div class="flex-1">
              <p class="text-[10px] font-black text-primary tracking-widest uppercase mb-1">Culinaria Arena</p>
              <p class="text-[11px] font-bold text-gray-600 leading-tight">Play a quick game while your meal is prepared and win coins!</p>
           </div>
           <span class="material-symbols-outlined text-gray-400">chevron_right</span>
        </div>
        </aside>
      </div>
    </main>

    <!-- Address Modal (Simplified) -->
    <div v-if="showNewAddrForm" class="fixed inset-0 bg-black/60 backdrop-blur-md z-[100] flex items-center justify-center p-6">
       <div class="bg-white w-full max-w-xl rounded-[3rem] overflow-hidden shadow-2xl relative">
          <button @click="showNewAddrForm = false" class="absolute top-8 right-8 material-symbols-outlined text-gray-400">close</button>
          <div class="p-12">
             <h3 class="text-2xl font-black tracking-tight mb-8 uppercase">New Delivery Coordinates</h3>
             <OlaMapPicker placeholder="Search for your landmark..." @locationSelected="onCheckoutMapSelect" />
             <div class="grid grid-cols-2 gap-4 mt-6">
                <input v-model="newAddr.flat_building" placeholder="Flat / Building" class="col-span-2 px-6 py-4 bg-gray-50 rounded-2xl text-sm font-bold focus:outline-none focus:ring-2 focus:ring-primary/20" />
                <input v-model="newAddr.street" placeholder="Area / Street" class="px-6 py-4 bg-gray-50 rounded-2xl text-sm font-bold focus:outline-none focus:ring-2 focus:ring-primary/20" />
                <input v-model="newAddr.landmark" placeholder="Landmark" class="px-6 py-4 bg-gray-50 rounded-2xl text-sm font-bold focus:outline-none focus:ring-2 focus:ring-primary/20" />
             </div>
             <div class="flex gap-4 mt-8">
                <button v-for="l in ['Home', 'Work', 'Other']" :key="l" 
                  @click="newAddr.label = l"
                  :class="newAddr.label === l ? 'bg-on-surface text-white' : 'bg-gray-100 text-gray-400'"
                  class="flex-1 py-4 rounded-2xl font-black text-[10px] tracking-widest uppercase transition-all">
                  {{ l }}
                </button>
             </div>
             <button @click="handleAddNewAddr" class="w-full bg-primary text-white py-5 rounded-2xl font-black text-xs tracking-[0.2em] uppercase mt-10 shadow-xl shadow-primary/20">SAVE COORDINATES</button>
          </div>
       </div>
    </div>

    <Footer />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { state as cart, cartTotal, clearCart } from '../store/cart'
import { checkoutOrder, getMe, isAuthenticated, getAddresses, addAddress as apiAddAddress, getAvailableCoupons, createPaymentOrder, verifyPayment, getWalletData, getRestaurant } from '../api'
import Navbar from '../components/Navbar.vue'
import Footer from '../components/Footer.vue'
import OlaMapPicker from '../components/OlaMapPicker.vue'
import { olaReverseGeocode } from '../olaMaps'

const router = useRouter()
const isProcessing = ref(false)
const checkoutError = ref('')
const userProfile = ref(null)
const addresses = ref([])
const selectedAddress = ref(null)
const showNewAddrForm = ref(false)
const paymentMode = ref('cod')
const appliedCoupon = ref(null)
const isBusinessOrder = ref(false)
const companyName = ref('')
const companyGst = ref('')
const newAddr = ref({ label: 'Home', flat_building: '', street: '', landmark: '', city: 'Bhiwani', pincode: '', latitude: null, longitude: null })
const walletBalance = ref(0)
const restaurant = ref(null)

const finalTotal = computed(() => {
  let total = cartTotal.value + 39 + 24
  if (appliedCoupon.value) total -= appliedCoupon.value.discount_amount
  return Math.max(0, total).toFixed(0)
})

const formatAddr = (a) => [a.flat_building, a.street, a.landmark].filter(Boolean).join(', ')

const selectAddress = (addr) => { selectedAddress.value = addr }

const haversineDistance = (lat1, lon1, lat2, lon2) => {
  const R = 6371 // Radius of the earth in km
  const dLat = (lat2 - lat1) * Math.PI / 180
  const dLon = (lon2 - lon1) * Math.PI / 180
  const a = 
    Math.sin(dLat/2) * Math.sin(dLat/2) +
    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
    Math.sin(dLon/2) * Math.sin(dLon/2)
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a))
  return R * c
}

const calculatedDistance = computed(() => {
  if (!selectedAddress.value || !restaurant.value) return 0
  const lat1 = parseFloat(selectedAddress.value.latitude)
  const lng1 = parseFloat(selectedAddress.value.longitude)
  const lat2 = parseFloat(restaurant.value.latitude)
  const lng2 = parseFloat(restaurant.value.longitude)
  if (!lat1 || !lng1 || !lat2 || !lng2) return 0
  return haversineDistance(lat1, lng1, lat2, lng2)
})

const isOutOfRange = computed(() => {
  if (!selectedAddress.value || !restaurant.value) return false
  return calculatedDistance.value > 10
})

const onCheckoutMapSelect = async (loc) => {
  newAddr.value.street = loc.formatted_address || loc.description || loc.mainText || ''
  newAddr.value.latitude = loc.lat
  newAddr.value.longitude = loc.lng
  
  let components = loc.components
  if (!components && loc.lat && loc.lng) {
    const result = await olaReverseGeocode(loc.lat, loc.lng)
    if (result) {
      components = result.components
    }
  }
  
  if (components) {
    if (components.city) newAddr.value.city = components.city
    if (components.pincode) newAddr.value.pincode = components.pincode
  }
}

const handleAddNewAddr = async () => {
  try {
    const res = await apiAddAddress({ ...newAddr.value })
    addresses.value.unshift(res.address)
    selectedAddress.value = res.address
    showNewAddrForm.value = false
  } catch (e) {
    checkoutError.value = e.errors ? Object.values(e.errors).flat().join(' ') : (e.message || 'Failed to save address.')
  }
}

const loadRazorpay = () => {
  return new Promise((resolve) => {
    if (window.Razorpay) {
      resolve(true)
      return
    }
    const script = document.createElement('script')
    script.src = 'https://checkout.razorpay.com/v1/checkout.js'
    script.onload = () => resolve(true)
    script.onerror = () => resolve(false)
    document.body.appendChild(script)
  })
}

onMounted(async () => {
  loadRazorpay()
  if (isAuthenticated()) {
    try {
      const [userData, addrData, couponData, walletData] = await Promise.all([
        getMe(), getAddresses(), getAvailableCoupons(cartTotal.value), getWalletData()
      ])
      userProfile.value = userData
      addresses.value = addrData.addresses || []
      selectedAddress.value = addresses.value.find(a => a.is_default) || addresses.value[0] || null
      if (couponData.best_coupon) appliedCoupon.value = couponData.best_coupon
      walletBalance.value = walletData.balance || 0
    } catch (e) { console.error(e) }
  }
  
  if (cart.restaurantId || (cart.items && cart.items[0]?.restaurant_id)) {
    try {
      restaurant.value = await getRestaurant(cart.restaurantId || cart.items[0]?.restaurant_id)
    } catch (e) { console.error(e) }
  }

  // Pre-fill address modal city and pincode from the active frontend location (localStorage: 'utterlazy_location')
  const savedLoc = localStorage.getItem('utterlazy_location')
  if (savedLoc) {
    try {
      const parsed = JSON.parse(savedLoc)
      if (parsed && parsed.lat && parsed.lng) {
        newAddr.value.latitude = parsed.lat
        newAddr.value.longitude = parsed.lng
        newAddr.value.city = parsed.city || 'Bhiwani'
        const geoResult = await olaReverseGeocode(parsed.lat, parsed.lng)
        if (geoResult) {
          if (geoResult.components?.city) newAddr.value.city = geoResult.components.city
          if (geoResult.components?.pincode) newAddr.value.pincode = geoResult.components.pincode
          newAddr.value.street = geoResult.formatted_address || ''
        }
      }
    } catch (err) { console.error('Error pre-populating location:', err) }
  }
})

const placeOrder = async () => {
  isProcessing.value = true
  checkoutError.value = ''
  
  if (isBusinessOrder.value) {
    if (!companyName.value.trim()) {
      checkoutError.value = 'Company Name is required for business orders.'
      isProcessing.value = false
      return
    }
    if (!companyGst.value.trim()) {
      checkoutError.value = 'GST Number is required for business orders.'
      isProcessing.value = false
      return
    }
    const gstPattern = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/i
    if (!gstPattern.test(companyGst.value.trim())) {
      checkoutError.value = 'Please enter a valid 15-digit GSTIN.'
      isProcessing.value = false
      return
    }
  }

  try {
    const payload = {
      restaurant_id: cart.restaurantId || cart.items[0]?.restaurant_id,
      items: cart.items.map(i => ({ menu_item_id: i.id, quantity: i.quantity })),
      address_id: selectedAddress.value.id,
      payment_mode: paymentMode.value === 'online' ? 'prepaid' : paymentMode.value === 'wallet' ? 'wallet' : 'cod',
      coupon_code: appliedCoupon.value?.code,
      is_business_order: isBusinessOrder.value,
      company_name: isBusinessOrder.value ? companyName.value.trim() : null,
      company_gst: isBusinessOrder.value ? companyGst.value.trim().toUpperCase() : null
    }
    const res = await checkoutOrder(payload)
    const order = res.order
    
    if (paymentMode.value === 'online') {
      try {
        const payOrder = await createPaymentOrder(order.id)
        const options = {
          key: payOrder.key_id,
          amount: payOrder.razorpay_order.amount,
          currency: payOrder.razorpay_order.currency,
          name: 'UtterLazy',
          description: 'Order Payment',
          order_id: payOrder.razorpay_order.id,
          handler: async function (response) {
            try {
              isProcessing.value = true
              await verifyPayment(order.id, {
                razorpay_order_id: response.razorpay_order_id,
                razorpay_payment_id: response.razorpay_payment_id,
                razorpay_signature: response.razorpay_signature
              })
              clearCart()
              router.push({ path: '/order-success', query: { orderId: order.id, total: order.grand_total }})
            } catch (err) {
              checkoutError.value = err.message
            } finally {
              isProcessing.value = false
            }
          },
          modal: {
            ondismiss: function () {
              checkoutError.value = 'Payment cancelled. You can try COD instead.'
            }
          }
        }
        const rzp = new window.Razorpay(options)
        rzp.open()
      } catch (err) {
        checkoutError.value = 'Payment initiation failed: ' + err.message
      }
    } else {
      clearCart()
      router.push({ path: '/order-success', query: { orderId: order.id, total: order.grand_total }})
    }
  } catch (e) {
    console.error(e)
    checkoutError.value = e.message
  }
  finally { isProcessing.value = false }
}

</script>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
