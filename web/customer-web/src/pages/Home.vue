<template>
  <div class="bg-white text-on-surface min-h-screen">
    
    <LocationSelector v-model="currentLocation" />

    <Navbar 
      :location="currentLocation" 
      :isLoggedIn="!!userProfile"
      :userName="userProfile?.user?.name || ''"
      :cartCount="cartItemsCount"
      @resetLocation="resetLocation"
    />

    <main class="pt-24 pb-20 px-6 md:px-12 max-w-[1400px] mx-auto">
      
      <!-- Loading State -->
      <div v-if="loading" class="py-40 text-center">
        <div class="w-12 h-12 border-4 border-primary/20 border-t-primary rounded-full animate-spin mx-auto mb-6"></div>
        <p class="font-black text-xs tracking-widest text-primary uppercase">Gathering Artisans...</p>
      </div>

      <!-- Main Content -->
      <div v-else-if="currentLocation">
        
        <!-- Section 1: Categories (What's on your mind?) -->
        <section class="mb-12">
          <div class="flex items-center justify-between mb-8">
            <h2 class="text-2xl font-black text-on-surface tracking-tight">What's on your mind?</h2>
            <div class="flex gap-2">
              <button @click="scrollCategories(-300)" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center hover:bg-gray-200 transition-colors">
                <span class="material-symbols-outlined">arrow_back</span>
              </button>
              <button @click="scrollCategories(300)" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center hover:bg-gray-200 transition-colors">
                <span class="material-symbols-outlined">arrow_forward</span>
              </button>
            </div>
          </div>
          <div ref="categoryScroll" class="flex gap-10 overflow-x-auto no-scrollbar scroll-smooth pb-4">
            <div v-for="cat in categories" :key="cat.name" 
              class="flex flex-col items-center gap-3 shrink-0 cursor-pointer group"
              @click="handleCategoryClick(cat.name)">
              <div class="w-32 h-32 rounded-full overflow-hidden transition-transform duration-500 group-hover:scale-95 group-active:scale-90"
                :class="{'ring-4 ring-primary ring-offset-4': selectedCategory === cat.name}">
                <img :src="cat.image" :alt="cat.name" class="w-full h-full object-cover" />
              </div>
              <span class="text-sm font-bold transition-colors uppercase tracking-widest text-[10px]"
                :class="selectedCategory === cat.name ? 'text-primary' : 'text-gray-600 group-hover:text-on-surface'">
                {{ cat.name }}
              </span>
            </div>
          </div>
        </section>

        <hr class="border-gray-100 mb-12" />

        <!-- Section 2: Top Brands -->
        <section class="mb-12">
          <div class="flex items-center justify-between mb-8">
            <h2 class="text-2xl font-black text-on-surface tracking-tight">Top brand for you</h2>
            <div class="flex gap-2">
              <button @click="scrollBrands(-400)" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center hover:bg-gray-200 transition-colors">
                <span class="material-symbols-outlined">arrow_back</span>
              </button>
              <button @click="scrollBrands(400)" class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center hover:bg-gray-200 transition-colors">
                <span class="material-symbols-outlined">arrow_forward</span>
              </button>
            </div>
          </div>
          <div ref="brandScroll" class="flex gap-8 overflow-x-auto no-scrollbar scroll-smooth pb-4">
            <div v-for="restaurant in topBrands" :key="restaurant.id" 
              class="w-[280px] shrink-0 group cursor-pointer"
              @click="router.push('/restaurant/' + restaurant.id)">
              <div class="h-44 rounded-3xl overflow-hidden relative mb-4 shadow-sm border border-gray-50">
                <img :src="restaurant.logo_url || 'https://images.unsplash.com/photo-1512152272829-e3139592d56f?auto=format&fit=crop&w=800&q=80'" 
                  class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" />
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent"></div>
                <div class="absolute bottom-4 left-4">
                  <p class="text-white font-black text-lg tracking-tight uppercase">{{ restaurant.offer || 'ITEMS AT ₹129' }}</p>
                </div>
              </div>
              <h3 class="font-black text-lg text-on-surface truncate group-hover:text-primary transition-colors">{{ restaurant.name }}</h3>
              <div class="flex items-center gap-2 mt-1">
                <span class="material-symbols-outlined text-green-600 text-lg" style="font-variation-settings: 'FILL' 1;">stars</span>
                <span class="text-sm font-bold text-gray-700">{{ restaurant.rating || '4.2' }}</span>
                <span class="text-gray-300">•</span>
                <span class="text-sm font-bold text-gray-700 uppercase tracking-widest text-[10px]">{{ restaurant.delivery_time || '25-30 MIN' }}</span>
              </div>
              <p class="text-gray-400 text-[11px] font-medium truncate mt-1 tracking-wide">{{ restaurant.description }}</p>
            </div>
          </div>
        </section>

        <hr class="border-gray-100 mb-12" />

        <!-- Section 3: Culinaria Arena (Games) -->
        <section class="mb-16">
          <div class="bg-orange-50 rounded-[3rem] p-10 md:p-16 flex flex-col md:flex-row items-center gap-12 overflow-hidden relative">
            <div class="absolute top-0 right-0 w-64 h-64 bg-primary/5 rounded-full -translate-y-1/2 translate-x-1/2"></div>
            <div class="absolute bottom-0 left-0 w-32 h-32 bg-primary/10 rounded-full translate-y-1/2 -translate-x-1/2"></div>
            
            <div class="flex-1 text-center md:text-left z-10">
              <span class="bg-primary/10 text-primary px-6 py-2 rounded-full text-[10px] font-black tracking-[0.2em] mb-6 inline-block uppercase">Culinaria Arena</span>
              <h2 class="text-4xl md:text-6xl font-black text-on-surface mb-6 leading-tight">Play Games.<br/><span class="text-primary italic">Win Free Meals.</span></h2>
              <p class="text-lg text-gray-600 font-medium mb-10 max-w-md">Engage in artisan culinary challenges and earn Sovereign Coins for your next mission.</p>
              <button @click="router.push('/games')" class="bg-on-surface text-white px-10 py-5 rounded-2xl font-black text-xs tracking-[0.2em] uppercase hover:scale-105 active:scale-95 transition-all shadow-xl shadow-gray-200">
                ENTER THE ARENA
              </button>
            </div>
            <div class="w-full md:w-[400px] h-[300px] bg-white rounded-3xl shadow-soft border border-orange-100/50 p-4 flex items-center justify-center overflow-hidden">
               <div class="text-center">
                  <span class="material-symbols-outlined text-8xl text-primary animate-bounce">sports_esports</span>
                  <p class="font-black text-xs tracking-widest text-primary mt-4">ARENA IS OPEN</p>
               </div>
            </div>
          </div>
        </section>

        <!-- Section 4: Main Restaurant List -->
        <section id="restaurant-list">
          <div class="flex items-center justify-between mb-8">
            <h2 class="text-2xl font-black text-on-surface tracking-tight">
              {{ selectedCategory ? `${selectedCategory} near you` : 'Restaurants with online food delivery' }}
            </h2>
            <button v-if="selectedCategory" @click="selectedCategory = null" class="text-xs font-black text-primary uppercase tracking-widest hover:underline">
              Clear Filter
            </button>
          </div>
          
          <!-- Filters -->
          <div class="flex gap-4 overflow-x-auto no-scrollbar mb-10 pb-2">
            <button 
              class="px-5 py-2.5 rounded-full border text-sm font-bold transition-all flex items-center gap-2 whitespace-nowrap active:scale-95"
              :class="gstOnly ? 'border-primary bg-primary/10 text-primary' : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
              @click="toggleGstFilter">
              GST Ready 🧾
            </button>
            <button v-for="filter in filters" :key="filter" 
              class="px-5 py-2.5 rounded-full border border-gray-200 text-sm font-bold text-gray-600 hover:bg-gray-50 transition-all flex items-center gap-2 whitespace-nowrap active:scale-95">
              {{ filter }}
              <span class="material-symbols-outlined text-[18px]" v-if="filter === 'Filter'">tune</span>
            </button>
          </div>

          <!-- Restaurant Grid -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-x-10 gap-y-16">
            <div v-for="restaurant in filteredRestaurants" :key="restaurant.id" 
              class="group cursor-pointer"
              @click="router.push('/restaurant/' + restaurant.id)">
              <div class="h-56 rounded-3xl overflow-hidden relative mb-5 shadow-sm border border-gray-50">
                <img :src="restaurant.logo_url || 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?auto=format&fit=crop&w=800&q=80'" 
                  class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700" />
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent"></div>
                <div class="absolute bottom-5 left-5 flex flex-col gap-1">
                   <p class="text-white font-black text-xl tracking-tight uppercase">{{ restaurant.offer || '₹100 OFF ABOVE ₹499' }}</p>
                   <div class="flex items-center gap-2">
                      <span class="bg-green-500 text-white text-[9px] font-black px-1.5 py-0.5 rounded uppercase tracking-widest">Smart Coupon</span>
                      <span class="text-white/60 text-[9px] font-bold uppercase tracking-widest">Auto-Applied</span>
                   </div>
                </div>
              </div>
              <h3 class="font-black text-lg text-on-surface truncate group-hover:text-primary transition-colors">{{ restaurant.name }}</h3>
              <div class="flex items-center gap-2 mt-1">
                <div class="w-5 h-5 bg-green-600 rounded-full flex items-center justify-center">
                   <span class="material-symbols-outlined text-white text-[14px]" style="font-variation-settings: 'FILL' 1;">star</span>
                </div>
                <span class="text-sm font-bold text-gray-700">{{ restaurant.rating || '4.1' }}</span>
                <span class="text-gray-300">•</span>
                <span class="text-sm font-bold text-gray-700 uppercase tracking-widest text-[10px]">{{ restaurant.delivery_time || '30-35 MIN' }}</span>
              </div>
              <p class="text-gray-400 text-[11px] font-medium truncate mt-1 tracking-wide">{{ restaurant.description }}</p>
            </div>
          </div>
        </section>

      </div>

      <!-- No Location / Empty State -->
      <div v-else class="py-32 text-center max-w-xl mx-auto">
        <div class="w-32 h-32 bg-orange-50 rounded-full flex items-center justify-center mx-auto mb-10">
          <span class="material-symbols-outlined text-6xl text-primary opacity-20">location_off</span>
        </div>
        <h2 class="text-4xl font-black text-on-surface mb-6 leading-tight">Coordinates Unidentified</h2>
        <p class="text-gray-500 text-lg font-medium mb-12">Please select your coordinates to view the master artisans in your sector.</p>
        <button @click="resetLocation" class="bg-primary text-white px-12 py-5 rounded-2xl font-black text-xs tracking-[0.2em] uppercase shadow-xl shadow-primary/20 hover:scale-105 active:scale-95 transition-all">
          SET LOCATION
        </button>
      </div>
    </main>

    <Footer />
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import LocationSelector from '../components/LocationSelector.vue'
import Navbar from '../components/Navbar.vue'
import Footer from '../components/Footer.vue'
import { getRestaurants, getMe, isAuthenticated, getGlobalCategories } from '../api'
import { state as cart } from '../store/cart'

const router = useRouter()
const categoryScroll = ref(null)
const brandScroll = ref(null)

const currentLocation = ref(null)
const restaurants = ref([])
const userProfile = ref(null)
const loading = ref(true)
const selectedCategory = ref(null)
const gstOnly = ref(false)

const toggleGstFilter = () => {
  gstOnly.value = !gstOnly.value
  fetchRestaurants()
}

const cartItemsCount = computed(() => {
  return Object.values(cart.items).reduce((acc, item) => acc + item.quantity, 0)
})

const categories = ref([])

const filters = ['Filter', 'Sort By', 'Fast Delivery', 'New on UtterLazy', 'Ratings 4.0+', 'Pure Veg', 'Offers', 'Rs. 300-Rs. 600', 'Less than Rs. 300']

const topBrands = computed(() => restaurants.value.slice(0, 8))

const scrollCategories = (px) => categoryScroll.value?.scrollBy({ left: px, behavior: 'smooth' })
const scrollBrands = (px) => brandScroll.value?.scrollBy({ left: px, behavior: 'smooth' })

const handleCategoryClick = (category) => {
  if (selectedCategory.value === category) {
    selectedCategory.value = null
  } else {
    selectedCategory.value = category
    // Scroll to restaurant list
    const el = document.getElementById('restaurant-list')
    if (el) {
      const yOffset = -100
      const y = el.getBoundingClientRect().top + window.pageYOffset + yOffset
      window.scrollTo({ top: y, behavior: 'smooth' })
    }
  }
}

const resetLocation = () => {
  currentLocation.value = null
  localStorage.removeItem('utterlazy_location')
}

const fetchRestaurants = async () => {
  loading.value = true
  try {
    const loc = currentLocation.value
    restaurants.value = await getRestaurants(loc?.lat, loc?.lng, selectedCategory.value, gstOnly.value)
  } catch (e) { console.error(e) }
  finally { loading.value = false }
}

watch(selectedCategory, () => {
  fetchRestaurants()
})

watch(currentLocation, (newLoc) => {
  if (newLoc) fetchRestaurants()
})

onMounted(async () => {
  try {
    const savedLoc = localStorage.getItem('utterlazy_location')
    if (savedLoc) currentLocation.value = JSON.parse(savedLoc)
    
    if (currentLocation.value) await fetchRestaurants()
    if (isAuthenticated()) {
      userProfile.value = await getMe()
    }
    
    // Load categories dynamically from backend
    const cats = await getGlobalCategories()
    categories.value = cats.map(c => ({ ...c, image: c.image_url }))
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
})

const filteredRestaurants = computed(() => {
  if (!currentLocation.value) return []
  return restaurants.value
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
