<template>
  <div class="bg-white min-h-screen text-on-surface">
    <Navbar 
      :location="currentLocation" 
      :isLoggedIn="!!userProfile"
      :userName="userProfile?.user?.name || ''"
      :cartCount="cartItemsCount"
      @toggleUserPanel="showUserPanel = !showUserPanel"
    />

    <main class="pt-32 pb-20 px-6 md:px-12 max-w-[1400px] mx-auto">
      <!-- Arena Header -->
      <header class="mb-16 relative overflow-hidden bg-orange-50 rounded-[3rem] p-12 md:p-20">
         <div class="absolute top-0 right-0 w-96 h-96 bg-primary/5 rounded-full -translate-y-1/2 translate-x-1/2"></div>
         <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-12">
            <div class="max-w-xl text-center md:text-left">
               <span class="bg-primary/10 text-primary px-6 py-2 rounded-full text-[10px] font-black tracking-[0.2em] mb-8 inline-block uppercase">Sovereign Domain</span>
               <h1 class="text-5xl md:text-7xl font-black text-on-surface mb-6 leading-tight tracking-tighter uppercase">Culinaria<br/><span class="text-primary italic">Arena</span></h1>
               <p class="text-lg text-gray-600 font-medium mb-10">Test your culinary tactical skills, earn Sovereign Coins, and redeem them for legendary rewards.</p>
               
               <div class="flex flex-wrap gap-4 justify-center md:justify-start">
                  <div class="bg-white px-8 py-6 rounded-3xl shadow-soft border border-orange-100 flex items-center gap-6">
                     <div class="w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center">
                        <span class="material-symbols-outlined text-primary font-bold">payments</span>
                     </div>
                     <div>
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Your Balance</p>
                        <p class="text-2xl font-black text-on-surface tracking-tight">{{ userCoins }} <span class="text-xs text-primary ml-1">COINS</span></p>
                     </div>
                  </div>
                  <div class="bg-white px-8 py-6 rounded-3xl shadow-soft border border-orange-100 flex items-center gap-6">
                     <div class="w-12 h-12 bg-green-50 rounded-full flex items-center justify-center">
                        <span class="material-symbols-outlined text-green-600 font-bold">military_tech</span>
                     </div>
                     <div>
                        <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">Rank</p>
                        <p class="text-2xl font-black text-on-surface tracking-tight">Artisan</p>
                     </div>
                  </div>
               </div>
            </div>
            
            <div class="w-full md:w-[400px] aspect-square relative">
               <div class="absolute inset-0 bg-primary/20 rounded-full animate-ping opacity-20"></div>
               <div class="absolute inset-4 bg-primary/10 rounded-full animate-pulse opacity-40"></div>
               <div class="relative z-10 w-full h-full bg-white rounded-full shadow-2xl flex items-center justify-center border-8 border-orange-50">
                  <span class="material-symbols-outlined text-[120px] text-primary">token</span>
               </div>
            </div>
         </div>
      </header>

      <!-- Active Missions -->
      <section class="mb-20">
         <div class="flex items-center justify-between mb-10">
            <h2 class="text-3xl font-black text-on-surface tracking-tighter uppercase">Active Missions</h2>
            <p class="text-[10px] font-black text-gray-400 uppercase tracking-[0.3em]">Resetting in 14h 22m</p>
         </div>
         
         <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <div v-for="game in games" :key="game.id" 
               @click="activeGameId = game.id"
               class="group bg-white border border-gray-100 rounded-[2.5rem] p-8 shadow-sm hover:shadow-2xl hover:-translate-y-2 transition-all cursor-pointer relative overflow-hidden">
               <div class="absolute top-0 right-0 w-32 h-32 bg-gray-50 rounded-full translate-x-1/2 -translate-y-1/2 group-hover:bg-primary/5 transition-colors"></div>
               
               <div class="w-16 h-16 rounded-2xl mb-8 flex items-center justify-center shadow-lg" :class="game.bgColor">
                  <span class="material-symbols-outlined text-white text-3xl">{{ game.icon }}</span>
               </div>
               
               <h3 class="text-2xl font-black text-on-surface mb-3 tracking-tight uppercase">{{ game.title }}</h3>
               <p class="text-sm text-gray-500 font-medium mb-8 leading-relaxed">{{ game.description }}</p>
               
               <div class="flex items-center justify-between pt-6 border-t border-gray-50">
                  <div class="flex items-center gap-2">
                     <span class="material-symbols-outlined text-primary text-sm font-bold">payments</span>
                     <span class="text-xs font-black text-primary uppercase tracking-widest">Earn {{ game.reward }} Coins</span>
                  </div>
                  <button class="bg-on-surface text-white px-6 py-3 rounded-xl font-black text-[10px] tracking-widest uppercase group-hover:bg-primary transition-colors">Launch Mission</button>
               </div>
            </div>
         </div>
      </section>

      <!-- Redemption Center -->
      <section class="bg-on-surface rounded-[3rem] p-12 md:p-20 text-white relative overflow-hidden">
         <div class="absolute top-0 left-0 w-full h-full opacity-10 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-white via-transparent to-transparent"></div>
         
         <div class="relative z-10">
            <div class="flex flex-col md:flex-row items-end justify-between gap-8 mb-16">
               <div class="max-w-xl">
                  <h2 class="text-4xl md:text-5xl font-black mb-6 tracking-tighter uppercase">Redemption Gallery</h2>
                  <p class="text-white/60 text-lg font-medium">Exchange your Sovereign Coins for exclusive gastronomic passes and smart coupons.</p>
               </div>
               <button class="text-xs font-black tracking-widest uppercase text-white/40 hover:text-primary transition-colors">View All Rewards</button>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
               <div v-for="reward in rewards" :key="reward.id" class="bg-white/5 border border-white/10 rounded-3xl p-6 hover:bg-white/10 transition-all group">
                  <div class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center mb-6 text-primary">
                     <span class="material-symbols-outlined">{{ reward.icon }}</span>
                  </div>
                  <h4 class="font-black text-lg mb-2 uppercase tracking-tight">{{ reward.title }}</h4>
                  <p class="text-white/40 text-xs font-bold mb-6 tracking-widest uppercase">{{ reward.desc }}</p>
                  <div class="flex items-center justify-between">
                     <span class="text-primary font-black text-sm">{{ reward.cost }} COINS</span>
                     <button class="material-symbols-outlined text-white/20 group-hover:text-white transition-colors">shopping_cart_checkout</button>
                  </div>
               </div>
            </div>
         </div>
      </section>
    </main>

    <Footer />

    <!-- Game Modal -->
    <div v-if="activeGameId" class="fixed inset-0 bg-black/60 backdrop-blur-md z-[110] flex items-center justify-center p-4">
       <div class="bg-white w-full max-w-xl rounded-[2.5rem] overflow-hidden shadow-2xl relative border border-gray-100">
          <button @click="activeGameId = null" class="absolute top-6 right-6 w-10 h-10 rounded-full bg-gray-50 hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-on-surface transition-colors z-50">
             <span class="material-symbols-outlined text-lg">close</span>
          </button>
          <div class="p-6 md:p-8 pt-12">
             <MiniGames :initialGame="mapGameId(activeGameId)" />
          </div>
       </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import Navbar from '../components/Navbar.vue'
import Footer from '../components/Footer.vue'
import MiniGames from '../components/MiniGames.vue'
import { getMe, isAuthenticated } from '../api'
import { state as cart } from '../store/cart'

const currentLocation = ref(null)
const userProfile = ref(null)
const userCoins = ref(1250)
const showUserPanel = ref(false)
const activeGameId = ref(null)

const cartItemsCount = computed(() => {
  return Object.values(cart.items).reduce((acc, item) => acc + item.quantity, 0)
})

const mapGameId = (id) => {
  const mapping = {
    flavor_finder: 'memory',
    chef_dash: 'catch',
    spice_master: 'trivia'
  }
  return mapping[id] || 'catch'
}

const games = [
  { id: 'flavor_finder', title: 'Flavor Finder', description: 'Match the heritage ingredients in record time to earn tactical rewards.', icon: 'psychology', bgColor: 'bg-primary', reward: 50 },
  { id: 'chef_dash', title: 'Artisan Dash', description: 'Deliver tactical orders in a high-speed simulation of the logistics domain.', icon: 'speed', bgColor: 'bg-green-600', reward: 75 },
  { id: 'spice_master', title: 'Spice Master', description: 'Identify complex aromatic profiles to unlock exclusive coupon vaults.', icon: 'potted_plant', bgColor: 'bg-orange-600', reward: 100 },
]

const rewards = [
  { id: 1, title: 'Tactical Pass', desc: 'Free Delivery for 7 Days', cost: 500, icon: 'local_shipping' },
  { id: 2, title: 'Gold Vault', desc: 'Flat ₹200 OFF Mission', cost: 1200, icon: 'savings' },
  { id: 3, title: 'Artisan Badge', desc: 'Exclusive Member Icon', cost: 300, icon: 'verified' },
  { id: 4, title: 'BOGO Token', desc: 'Buy 1 Get 1 on Biryani', cost: 800, icon: 'confirmation_number' },
]

onMounted(async () => {
  try {
    const savedLoc = localStorage.getItem('utterlazy_location')
    if (savedLoc) currentLocation.value = JSON.parse(savedLoc)
    
    if (isAuthenticated()) {
      userProfile.value = await getMe()
    }
  } catch (e) { console.error(e) }
})
</script>
