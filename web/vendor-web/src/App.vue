<script setup>
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuth } from './auth'
import { toggleOnline } from './api'

const router = useRouter()
const route = useRoute()
const { restaurant, stats, loading, loadProfile, logout } = useAuth()
const toggling = ref(false)
const isDark = ref(localStorage.getItem('utterlazy_theme') === 'dark')

const hasSidebar = computed(() => route.meta?.layout !== 'none')

function toggleTheme() {
  isDark.value = !isDark.value
  document.documentElement.classList.toggle('dark', isDark.value)
  localStorage.setItem('utterlazy_theme', isDark.value ? 'dark' : 'light')
}

if (isDark.value) document.documentElement.classList.add('dark')

const navItems = [
  { to: '/', icon: '📊', label: 'Dashboard' },
  { to: '/orders', icon: '🛒', label: 'Orders' },
  { to: '/menu', icon: '📋', label: 'Menu' },
  { to: '/inventory', icon: '📦', label: 'Inventory' },
  { to: '/combos', icon: '🎯', label: 'Combos' },
  { to: '/analytics', icon: '📈', label: 'Analytics' },
  { to: '/reviews', icon: '⭐', label: 'Reviews' },
  { to: '/whatsapp-promotions', icon: '📣', label: 'WhatsApp Ads' },
  { to: '/staff', icon: '👥', label: 'Staff' },
  { to: '/earnings', icon: '💰', label: 'Earnings' },
  { to: '/settings', icon: '⚙️', label: 'Settings' },
]

async function handleToggle() {
  toggling.value = true
  try {
    const res = await toggleOnline()
    restaurant.value = { ...restaurant.value, is_accepting_orders: res.is_accepting_orders }
  } catch (e) { console.error(e) }
  toggling.value = false
}

function handleLogout() {
  logout()
  router.push('/login')
}
</script>

<template>
  <div v-if="route.meta.requiresAuth && loading" class="loading-screen">
    <div class="spinner" />
    <p>Loading...</p>
  </div>
  <div v-else-if="route.meta.requiresAuth && !restaurant" />
  <div v-else-if="route.meta.requiresAuth && hasSidebar" class="app-layout">
    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="brand">
          <img src="/logo.png" alt="UtterLazy" class="h-10 object-contain" />
        </div>
        <span class="brand-badge">VENDOR</span>
      </div>
      <div class="restaurant-info">
        <div class="restaurant-avatar">
          <img v-if="restaurant?.logo_url" :src="restaurant.logo_url" alt="" />
          <span v-else>{{ restaurant?.name?.charAt(0) || 'K' }}</span>
        </div>
        <h3 class="restaurant-name font-space">{{ restaurant?.name || 'Restaurant' }}</h3>
        <p class="restaurant-id">ID: {{ restaurant?.id?.slice(0, 8) }}</p>
      </div>
      <nav class="sidebar-nav">
        <router-link v-for="item in navItems" :key="item.to" :to="item.to" class="nav-item" :class="{ active: route.path === item.to }">
          <span class="nav-icon">{{ item.icon }}</span>
          <span class="font-space">{{ item.label }}</span>
        </router-link>
      </nav>
      <div class="sidebar-footer">
        <div class="online-status">
          <div class="flex gap-3 items-center">
            <div class="status-indicator" :class="restaurant?.is_accepting_orders ? 'online' : 'offline'" />
            <span class="font-space text-sm font-bold">{{ restaurant?.is_accepting_orders ? 'ONLINE' : 'OFFLINE' }}</span>
          </div>
          <button class="btn btn-xs btn-secondary" @click="handleToggle" :disabled="toggling">{{ restaurant?.is_accepting_orders ? 'GO OFFLINE' : 'GO ONLINE' }}</button>
        </div>
        <button class="btn btn-xs btn-secondary mt-2 w-full" @click="toggleTheme">{{ isDark ? '☀️ LIGHT MODE' : '🌙 DARK MODE' }}</button>
        <button class="logout-btn mt-2" @click="handleLogout">LOGOUT</button>
      </div>
    </aside>
    <main class="main-content"><router-view /></main>
  </div>
  <div v-else-if="route.meta.requiresAuth && !hasSidebar" style="min-height: 100vh; background: #0a0e1a;">
    <router-view />
  </div>
  <router-view v-else />
</template>
