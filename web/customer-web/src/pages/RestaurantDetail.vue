<template>
  <div class="bg-white text-on-surface min-h-screen">
    
    <Navbar 
      :isLoggedIn="!!userProfile"
      :userName="userProfile?.user?.name || ''"
      :cartCount="cartItemsCount"
      @toggleUserPanel="showUserPanel = !showUserPanel"
    />

    <main v-if="!loading" class="pt-24 max-w-[1400px] mx-auto px-6 md:px-12 pb-20">
      
      <!-- Restaurant Info Header -->
      <header class="mb-12">
        <nav class="flex items-center gap-2 text-[10px] font-black text-gray-400 mb-8 tracking-widest uppercase">
          <router-link to="/" class="hover:text-on-surface transition-colors">Home</router-link>
          <span class="material-symbols-outlined text-[14px]">chevron_right</span>
          <span class="text-on-surface">{{ restaurant?.name }}</span>
        </nav>

        <div class="flex flex-col md:flex-row md:items-end justify-between gap-8">
          <div>
            <h1 class="text-4xl font-black text-on-surface tracking-tight mb-2 uppercase">{{ restaurant?.name }}</h1>
            <p class="text-gray-400 text-sm font-medium tracking-wide mb-6">{{ restaurant?.description }}</p>
            
            <div class="flex items-center gap-6">
              <div class="flex items-center gap-2 border border-gray-100 rounded-xl px-4 py-3 bg-gray-50/50">
                <span class="material-symbols-outlined text-green-600 font-bold" style="font-variation-settings: 'FILL' 1;">stars</span>
                <span class="text-sm font-black text-on-surface">{{ restaurant?.rating || '4.3' }}</span>
                <span class="text-gray-300 text-[10px]">|</span>
                <span class="text-[10px] font-black text-on-surface tracking-widest">1K+ RATINGS</span>
              </div>
              <div class="flex items-center gap-2 border border-gray-100 rounded-xl px-4 py-3 bg-gray-50/50">
                 <span class="material-symbols-outlined text-primary font-bold">schedule</span>
                 <span class="text-[10px] font-black text-on-surface tracking-widest">{{ restaurant?.delivery_time || '30 MINS' }}</span>
              </div>
            </div>
          </div>

          <!-- Offers / Coupons Nudge -->
          <div class="flex gap-4 overflow-x-auto no-scrollbar pb-2">
            <div v-for="i in 2" :key="i" class="w-64 shrink-0 border border-gray-100 rounded-2xl p-5 bg-gradient-to-br from-white to-orange-50/30">
               <div class="flex items-center gap-3 mb-2">
                  <span class="material-symbols-outlined text-primary">percent</span>
                  <p class="font-black text-sm text-on-surface">50% OFF UPTO ₹100</p>
               </div>
               <p class="text-[10px] font-black text-gray-400 tracking-widest">USE WELO50 | ABOVE ₹199</p>
               <div class="mt-3 flex items-center gap-1">
                  <span class="bg-green-500 w-1.5 h-1.5 rounded-full"></span>
                  <span class="text-[9px] font-black text-green-600 tracking-widest">SMART AUTO-APPLIED</span>
               </div>
            </div>
          </div>
        </div>
      </header>

      <hr class="border-gray-100 mb-12" />

      <div class="grid grid-cols-1 md:grid-cols-12 gap-12">
        <!-- Left: Category Sidebar -->
        <aside class="hidden md:block md:col-span-2 sticky top-32 h-fit">
          <nav class="space-y-4">
            <button v-for="cat in menuCategories" :key="cat" 
              @click="scrollToCategory(cat)"
              class="w-full text-left text-xs font-black tracking-widest uppercase py-2 hover:text-primary transition-colors"
              :class="activeCategory === cat ? 'text-primary border-r-4 border-primary' : 'text-gray-400'">
              {{ cat }}
            </button>
          </nav>
        </aside>

        <!-- Center: Menu -->
        <section class="md:col-span-6 space-y-16">
          
          <div class="flex items-center justify-between mb-8 sticky top-24 bg-white/95 backdrop-blur-md py-4 z-10 border-b border-gray-50">
             <div class="relative w-full">
                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">search</span>
                <input type="text" placeholder="Search in menu..." class="w-full pl-12 pr-4 py-3 bg-gray-50 rounded-xl text-sm font-bold focus:outline-none focus:ring-2 focus:ring-primary/20 transition-all" />
             </div>
          </div>

          <div v-for="cat in menuCategories" :key="cat" :id="'cat-' + cat.toLowerCase().replace(' ', '-')">
             <h3 class="text-xl font-black text-on-surface mb-8 tracking-tight uppercase flex items-center gap-4">
                {{ cat }}
                <span class="h-[1px] flex-1 bg-gray-100"></span>
             </h3>

             <div class="space-y-10">
                <div v-for="item in getItemsByCategory(cat)" :key="item.id" class="flex justify-between gap-8 group">
                   <div class="flex-1">
                      <div class="w-4 h-4 border-2 rounded p-0.5 mb-2" :class="item.is_veg ? 'border-green-600' : 'border-red-600'">
                         <div class="w-full h-full rounded-full" :class="item.is_veg ? 'bg-green-600' : 'bg-red-600'"></div>
                      </div>
                      <h4 class="text-lg font-black text-on-surface mb-1">{{ item.name }}</h4>
                      <p class="text-sm font-bold text-gray-700 mb-3">₹{{ item.price }}</p>
                      <p class="text-xs text-gray-400 font-medium leading-relaxed max-w-md">{{ item.description }}</p>
                   </div>
                   <div class="relative shrink-0">
                      <div class="w-32 h-32 rounded-2xl overflow-hidden shadow-sm border border-gray-50 bg-gray-50">
                         <img :src="item.image_url || 'https://images.unsplash.com/photo-1589187151003-0dd3c63b47e2?auto=format&fit=crop&w=300&q=80'" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                      </div>
                      <button @click="addToCart(item, restaurant)" class="absolute -bottom-2 left-1/2 -translate-x-1/2 bg-white text-green-600 border border-gray-100 shadow-xl shadow-gray-200/50 px-8 py-2 rounded-lg font-black text-xs tracking-widest uppercase hover:bg-green-50 transition-all active:scale-90 whitespace-nowrap">
                         ADD
                      </button>
                   </div>
                </div>
                <hr class="border-gray-50" />
             </div>
          </div>
        </section>

        <!-- Right: Cart Sidebar -->
        <aside class="md:col-span-4 h-fit sticky top-32">
          <div class="bg-white border border-gray-100 rounded-3xl p-8 shadow-soft">
            <div class="flex items-center justify-between mb-8">
               <h3 class="text-2xl font-black text-on-surface tracking-tight uppercase">Your Cart</h3>
               <span class="bg-green-100 text-green-600 text-[10px] font-black px-3 py-1 rounded-full">{{ cartCount }} ITEMS</span>
            </div>

            <div v-if="cart.items.length === 0" class="py-20 text-center">
               <img src="https://media-assets.swiggy.com/swiggy/image/upload/fl_lossy,f_auto,q_auto/2xempty_cart_ybi7ji" class="w-48 mx-auto opacity-20 mb-8" />
               <p class="font-black text-xs text-gray-400 tracking-widest uppercase">Cart is Empty</p>
               <p class="text-[10px] font-bold text-gray-300 mt-2 uppercase tracking-widest">Select items to begin mission</p>
            </div>

            <div v-else class="space-y-6 max-h-[45vh] overflow-y-auto no-scrollbar pr-2 pb-6">
               <div v-for="item in cart.items" :key="item.id" class="flex items-center gap-4">
                  <div class="w-3 h-3 border rounded-sm shrink-0" :class="item.is_veg ? 'border-green-600' : 'border-red-600'">
                     <div class="w-full h-full rounded-full" :class="item.is_veg ? 'bg-green-600' : 'bg-red-600'"></div>
                  </div>
                  <div class="flex-1 min-w-0">
                     <p class="text-sm font-bold text-on-surface truncate">{{ item.name }}</p>
                  </div>
                  <div class="flex items-center gap-3 border border-gray-100 rounded-lg px-2 py-1.5">
                     <button @click="removeFromCart(item.id)" class="material-symbols-outlined text-gray-400 hover:text-red-500 transition-colors text-lg">remove</button>
                     <span class="text-xs font-black w-4 text-center">{{ item.quantity }}</span>
                     <button @click="addToCart(item)" class="material-symbols-outlined text-green-600 hover:text-green-700 transition-colors text-lg">add</button>
                  </div>
                  <p class="text-sm font-bold text-gray-600 w-16 text-right">₹{{ item.price * item.quantity }}</p>
               </div>
            </div>

            <div v-if="cart.items.length > 0" class="pt-8 border-t border-gray-50 mt-4 space-y-6">
               <!-- Auto Coupon Success Message -->
               <div class="bg-green-50 border border-green-100 rounded-xl p-4 flex items-center gap-4">
                  <span class="material-symbols-outlined text-green-600 font-bold">verified</span>
                  <div>
                     <p class="text-[10px] font-black text-green-700 tracking-widest uppercase">Smart Savings Applied</p>
                     <p class="text-[9px] font-bold text-green-600 tracking-wide mt-0.5">₹100 SAVED AUTOMATICALLY</p>
                  </div>
               </div>

               <div class="flex justify-between items-end">
                  <div>
                     <p class="text-[10px] font-black text-gray-400 tracking-widest uppercase">Subtotal</p>
                     <p class="text-[9px] font-bold text-gray-300 tracking-widest uppercase mt-0.5">EXTRA CHARGES MAY APPLY</p>
                  </div>
                  <p class="text-2xl font-black text-on-surface tracking-tighter">₹{{ cartTotal }}</p>
               </div>

               <router-link to="/checkout" class="w-full bg-primary text-white py-5 rounded-2xl font-black text-xs tracking-[0.2em] uppercase flex items-center justify-center gap-4 shadow-xl shadow-primary/20 hover:scale-[1.02] active:scale-[0.98] transition-all">
                  CHECKOUT <span class="material-symbols-outlined">arrow_forward</span>
               </router-link>
            </div>
          </div>
        </aside>
      </div>
    </main>

    <div v-if="showUserPanel" class="fixed inset-0 z-50" @click="showUserPanel = false"></div>
    
    <Footer />
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import Navbar from '../components/Navbar.vue'
import Footer from '../components/Footer.vue'
import { getRestaurant, getMenuItems, getMe, isAuthenticated } from '../api'
import { state as cart, cartTotal, addToCart, removeFromCart } from '../store/cart'

const route = useRoute()
const restaurant = ref(null)
const menuItems = ref([])
const loading = ref(true)
const userProfile = ref(null)
const showUserPanel = ref(false)
const activeCategory = ref('')

const cartCount = computed(() => {
  return Object.values(cart.items).reduce((acc, item) => acc + item.quantity, 0)
})

const cartItemsCount = computed(() => cartCount.value)

const menuCategories = computed(() => {
  const cats = new Set()
  menuItems.value.forEach(item => {
    if (item.category) cats.add(item.category)
    else cats.add('Signature Dishes')
  })
  return Array.from(cats)
})

const getItemsByCategory = (cat) => {
  return menuItems.value.filter(item => (item.category || 'Signature Dishes') === cat)
}

const scrollToCategory = (cat) => {
  activeCategory.value = cat
  const id = 'cat-' + cat.toLowerCase().replace(' ', '-')
  const el = document.getElementById(id)
  if (el) {
    const yOffset = -120
    const y = el.getBoundingClientRect().top + window.pageYOffset + yOffset
    window.scrollTo({ top: y, behavior: 'smooth' })
  }
}

onMounted(async () => {
  try {
    const id = route.params.id
    const [restData, menuData] = await Promise.all([
      getRestaurant(id),
      getMenuItems(id)
    ])
    restaurant.value = restData
    menuItems.value = menuData
    if (menuCategories.value.length) activeCategory.value = menuCategories.value[0]
    
    if (isAuthenticated()) {
      userProfile.value = await getMe()
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
})
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
