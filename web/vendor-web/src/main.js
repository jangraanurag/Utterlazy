import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import './index.css'
import App from './App.vue'
import { useAuth } from './auth'

import Login from './pages/Login.vue'
import Dashboard from './pages/Dashboard.vue'
import Orders from './pages/Orders.vue'
import MenuManager from './pages/MenuManager.vue'
import Earnings from './pages/Earnings.vue'
import Settings from './pages/Settings.vue'
import Inventory from './pages/Inventory.vue'
import ComboOffers from './pages/ComboOffers.vue'
import Analytics from './pages/Analytics.vue'
import Reviews from './pages/Reviews.vue'
import StaffManagement from './pages/StaffManagement.vue'
import KDS from './pages/KDS.vue'
import VideoMenu from './pages/VideoMenu.vue'
import WhatsAppPromotions from './pages/WhatsAppPromotions.vue'

const routes = [
  { path: '/login', component: Login, meta: { guest: true } },
  { path: '/', component: Dashboard, meta: { requiresAuth: true } },
  { path: '/orders', component: Orders, meta: { requiresAuth: true } },
  { path: '/menu', component: MenuManager, meta: { requiresAuth: true } },
  { path: '/video-menu', component: VideoMenu, meta: { requiresAuth: true } },
  { path: '/whatsapp-promotions', component: WhatsAppPromotions, meta: { requiresAuth: true } },
  { path: '/earnings', component: Earnings, meta: { requiresAuth: true } },
  { path: '/settings', component: Settings, meta: { requiresAuth: true } },
  { path: '/inventory', component: Inventory, meta: { requiresAuth: true } },
  { path: '/combos', component: ComboOffers, meta: { requiresAuth: true } },
  { path: '/analytics', component: Analytics, meta: { requiresAuth: true } },
  { path: '/reviews', component: Reviews, meta: { requiresAuth: true } },
  { path: '/staff', component: StaffManagement, meta: { requiresAuth: true } },
  { path: '/kds', component: KDS, meta: { requiresAuth: true, layout: 'none' } },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  const { loading, loadProfile } = useAuth()
  if (to.meta.requiresAuth) {
    if (!localStorage.getItem('vendor_token')) return '/login'
    if (loading.value) await loadProfile()
  }
  if (to.meta.guest && localStorage.getItem('vendor_token')) return '/'
})

createApp(App).use(router).mount('#app')
