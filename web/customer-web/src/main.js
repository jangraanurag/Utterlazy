import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import './index.css'
import App from './App.vue'

import Home from './pages/Home.vue'
import RestaurantDetail from './pages/RestaurantDetail.vue'
import Checkout from './pages/Checkout.vue'
import Orders from './pages/Orders.vue'
import OrderTracking from './pages/OrderTracking.vue'
import OrderSuccess from './pages/OrderSuccess.vue'
import Search from './pages/Search.vue'
import Login from './pages/Login.vue'
import Register from './pages/Register.vue'
import ForgotPassword from './pages/ForgotPassword.vue'
import Favorites from './pages/Favorites.vue'
import AccountSettings from './pages/AccountSettings.vue'
import Games from './pages/Games.vue'
import RestaurantOnboarding from './pages/RestaurantOnboarding.vue'
import Membership from './pages/Membership.vue'
import Referral from './pages/Referral.vue'
import GiftCards from './pages/GiftCards.vue'
import ScheduledOrders from './pages/ScheduledOrders.vue'
import GroupOrder from './pages/GroupOrder.vue'
import NotificationSettings from './pages/NotificationSettings.vue'
import MyReviews from './pages/MyReviews.vue'
import Offline from './pages/Offline.vue'
import WriteReview from './pages/WriteReview.vue'
import CompareRestaurants from './pages/CompareRestaurants.vue'
import DownloadInvoice from './pages/DownloadInvoice.vue'
import LanguageSettings from './pages/LanguageSettings.vue'
import Cart from './pages/Cart.vue'
import FileComplaint from './pages/FileComplaint.vue'
import MyComplaints from './pages/MyComplaints.vue'
import ScratchCardHistory from './pages/ScratchCardHistory.vue'

const routes = [
  { path: '/', component: Home },
  { path: '/offline', component: Offline },
  { path: '/restaurant/:id', component: RestaurantDetail },
  { path: '/checkout', component: Checkout, meta: { requiresAuth: true } },
  { path: '/orders', component: Orders, meta: { requiresAuth: true } },
  { path: '/order-success', component: OrderSuccess, meta: { requiresAuth: true } },
  { path: '/order/:id/track', component: OrderTracking, meta: { requiresAuth: true } },
  { path: '/search', component: Search },
  { path: '/login', component: Login, meta: { guest: true } },
  { path: '/register', component: Register, meta: { guest: true } },
  { path: '/forgot-password', component: ForgotPassword, meta: { guest: true } },
  { path: '/favorites', component: Favorites, meta: { requiresAuth: true } },
  { path: '/settings', component: AccountSettings, meta: { requiresAuth: true } },
  { path: '/games', component: Games },
  { path: '/partner', component: RestaurantOnboarding },
  { path: '/membership', component: Membership, meta: { requiresAuth: true } },
  { path: '/referral', component: Referral, meta: { requiresAuth: true } },
  { path: '/gift-cards', component: GiftCards, meta: { requiresAuth: true } },
  { path: '/scheduled-orders', component: ScheduledOrders, meta: { requiresAuth: true } },
  { path: '/group-order', component: GroupOrder, meta: { requiresAuth: true } },
  { path: '/notification-settings', component: NotificationSettings, meta: { requiresAuth: true } },
  { path: '/my-reviews', component: MyReviews, meta: { requiresAuth: true } },
  { path: '/write-review', component: WriteReview, meta: { requiresAuth: true } },
  { path: '/compare', component: CompareRestaurants },
  { path: '/invoice/:id', component: DownloadInvoice, meta: { requiresAuth: true } },
  { path: '/language', component: LanguageSettings },
  { path: '/cart', component: Cart },
  { path: '/file-complaint/:orderId', component: FileComplaint, meta: { requiresAuth: true } },
  { path: '/my-complaints', component: MyComplaints, meta: { requiresAuth: true } },
  { path: '/scratch-cards', component: ScratchCardHistory, meta: { requiresAuth: true } },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('utterlazy_token')
  if (to.meta.requiresAuth && !token) next('/login')
  else if (to.meta.guest && token) next('/')
  else next()
})

createApp(App).use(router).mount('#app')

// Register Service Worker for offline support
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch(() => {})
  })
}
