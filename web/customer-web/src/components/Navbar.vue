<template>
  <nav class="fixed top-0 left-0 w-full bg-white border-b border-gray-100 z-50 h-20">
    <div class="max-w-[1400px] mx-auto px-6 md:px-12 h-full flex items-center justify-between">
      
      <!-- Left: Logo & Location -->
      <div class="flex items-center gap-10">
        <router-link to="/" class="flex items-center gap-2 group transition-transform active:scale-95">
          <img src="/logo.png" alt="UtterLazy" class="h-10 object-contain" />
        </router-link>

        <div v-if="location" @click="$emit('resetLocation')" 
          class="hidden lg:flex items-center gap-2 group cursor-pointer hover:text-primary transition-colors max-w-[300px]">
          <span class="font-bold text-xs tracking-widest text-on-surface border-b-2 border-on-surface group-hover:border-primary transition-colors pb-0.5 whitespace-nowrap">
            {{ location.type || 'HOME' }}
          </span>
          <span class="text-xs text-on-surface-variant truncate font-medium group-hover:text-primary/70 transition-colors">
            {{ location.name }}
          </span>
          <span class="material-symbols-outlined text-primary text-xl font-bold group-hover:translate-y-0.5 transition-transform">expand_more</span>
        </div>
      </div>

      <!-- Right: Actions -->
      <div class="flex items-center gap-8 md:gap-12">
        <!-- Desktop Search -->
        <router-link to="/search" class="hidden md:flex items-center gap-3 group text-on-surface hover:text-primary transition-all">
          <span class="material-symbols-outlined text-2xl font-medium group-hover:scale-110 transition-transform">search</span>
          <span class="text-sm font-bold tracking-wide">Search</span>
        </router-link>

        <!-- Offers -->
        <router-link to="/offers" class="hidden md:flex items-center gap-3 group text-on-surface hover:text-primary transition-all">
          <span class="material-symbols-outlined text-2xl font-medium group-hover:scale-110 transition-transform">percent</span>
          <span class="text-sm font-bold tracking-wide">Offers</span>
          <span class="bg-orange-100 text-primary text-[9px] font-black px-1.5 py-0.5 rounded-md -mt-4 animate-pulse">NEW</span>
        </router-link>

        <!-- Games -->
        <router-link to="/games" class="hidden md:flex items-center gap-3 group text-on-surface hover:text-primary transition-all">
          <span class="material-symbols-outlined text-2xl font-medium group-hover:rotate-12 transition-transform">sports_esports</span>
          <span class="text-sm font-bold tracking-wide">Arena</span>
        </router-link>

        <!-- Sign In / User -->
        <template v-if="isLoggedIn">
          <button @click="showUserPanel = !showUserPanel" class="flex items-center gap-3 group text-on-surface hover:text-primary transition-all">
            <span class="material-symbols-outlined text-2xl font-medium group-hover:scale-110 transition-transform" :class="{'text-primary': showUserPanel}">person_outline</span>
            <span class="text-sm font-bold tracking-wide" :class="{'text-primary': showUserPanel}">{{ userName.split(' ')[0] }}</span>
          </button>
        </template>
        <template v-else>
          <router-link to="/login" class="flex items-center gap-3 group text-on-surface hover:text-primary transition-all">
            <span class="material-symbols-outlined text-2xl font-medium group-hover:scale-110 transition-transform">person_outline</span>
            <span class="text-sm font-bold tracking-wide">Sign In</span>
          </router-link>
        </template>

        <!-- Cart -->
        <router-link to="/checkout" class="flex items-center gap-3 group text-on-surface hover:text-primary transition-all relative">
          <div class="relative">
            <span class="material-symbols-outlined text-2xl font-medium group-hover:scale-110 transition-transform">shopping_bag</span>
            <span v-if="cartCount > 0" 
              class="absolute -top-1 -right-1 w-5 h-5 bg-green-500 text-white text-[10px] font-black rounded-full flex items-center justify-center border-2 border-white shadow-sm animate-in zoom-in-50 duration-300">
              {{ cartCount }}
            </span>
          </div>
          <span class="text-sm font-bold tracking-wide hidden sm:block">Cart</span>
        </router-link>

        <!-- Dark Mode Toggle -->
        <button @click="toggleTheme" class="flex items-center gap-2 group text-on-surface hover:text-primary transition-all" :title="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'">
          <span class="material-symbols-outlined text-2xl font-medium group-hover:scale-110 transition-transform">
            {{ isDark ? 'light_mode' : 'dark_mode' }}
          </span>
        </button>
      </div>
    </div>

    <!-- User Panel Dropdown (Swiggy Style) -->
    <div v-if="showUserPanel && isLoggedIn" class="fixed top-20 right-12 w-64 bg-white border border-gray-100 rounded-xl shadow-2xl z-[60] py-4">
      <div class="px-6 py-4 border-b border-gray-50 mb-2">
        <p class="font-black text-[9px] tracking-[0.2em] text-primary mb-1 uppercase">Sovereign Member</p>
        <p class="font-bold text-lg text-on-surface">{{ userName }}</p>
      </div>
      <div class="space-y-1">
        <button @click="handleNav('/orders')" class="w-full flex items-center gap-4 px-6 py-3 hover:bg-orange-50/50 transition-colors group text-left">
          <span class="material-symbols-outlined text-gray-400 group-hover:text-primary transition-colors">receipt_long</span>
          <span class="text-sm font-bold text-gray-600 group-hover:text-on-surface transition-colors">Orders</span>
        </button>
        <button @click="handleNav('/favorites')" class="w-full flex items-center gap-4 px-6 py-3 hover:bg-orange-50/50 transition-colors group text-left">
          <span class="material-symbols-outlined text-gray-400 group-hover:text-primary transition-colors">favorite_border</span>
          <span class="text-sm font-bold text-gray-600 group-hover:text-on-surface transition-colors">Favorites</span>
        </button>
        <button @click="handleNav('/my-complaints')" class="w-full flex items-center gap-4 px-6 py-3 hover:bg-orange-50/50 transition-colors group text-left">
          <span class="material-symbols-outlined text-gray-400 group-hover:text-primary transition-colors">report_problem</span>
          <span class="text-sm font-bold text-gray-600 group-hover:text-on-surface transition-colors">My Complaints</span>
        </button>
        <button @click="handleNav('/settings')" class="w-full flex items-center gap-4 px-6 py-3 hover:bg-orange-50/50 transition-colors group text-left">
          <span class="material-symbols-outlined text-gray-400 group-hover:text-primary transition-colors">settings</span>
          <span class="text-sm font-bold text-gray-600 group-hover:text-on-surface transition-colors">Settings</span>
        </button>
        <button @click="handleNav('/compare')" class="w-full flex items-center gap-4 px-6 py-3 hover:bg-orange-50/50 transition-colors group text-left">
          <span class="material-symbols-outlined text-gray-400 group-hover:text-primary transition-colors">compare_arrows</span>
          <span class="text-sm font-bold text-gray-600 group-hover:text-on-surface transition-colors">Compare Restaurants</span>
        </button>
        <button @click="handleNav('/scratch-cards')" class="w-full flex items-center gap-4 px-6 py-3 hover:bg-orange-50/50 transition-colors group text-left">
          <span class="material-symbols-outlined text-gray-400 group-hover:text-primary transition-colors">card_giftcard</span>
          <span class="text-sm font-bold text-gray-600 group-hover:text-on-surface transition-colors">Scratch Cards</span>
        </button>
      </div>
      <div class="mt-4 pt-2 border-t border-gray-50 px-6">
        <button @click="handleLogout" class="text-[10px] font-black text-gray-400 hover:text-error transition-colors uppercase tracking-[0.2em]">Logout</button>
      </div>
    </div>

    <div v-if="showUserPanel" class="fixed inset-0 z-50" @click="showUserPanel = false"></div>
  </nav>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { logout as apiLogout } from '../api'
import { useTheme } from '../composables/useTheme'

const { isDark, toggle: toggleTheme } = useTheme()

defineProps({
  location: { type: Object, default: null },
  isLoggedIn: { type: Boolean, default: false },
  userName: { type: String, default: '' },
  cartCount: { type: Number, default: 0 },
})

const emit = defineEmits(['resetLocation'])
const router = useRouter()
const showUserPanel = ref(false)

const handleNav = (path) => {
  showUserPanel.value = false
  router.push(path)
}

const handleLogout = async () => {
  showUserPanel.value = false
  await apiLogout()
  window.location.reload()
}
</script>
