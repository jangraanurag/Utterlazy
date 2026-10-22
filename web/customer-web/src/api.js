const API_BASE = 'https://catalog.utterlazy.com/api'

export const sanitizeImageUrl = (url) => {
  if (!url) return ''
  return url
    .replace(/^http:\/\/localhost:8001/i, 'https://catalog.utterlazy.com')
    .replace(/^http:\/\/localhost:8000/i, 'https://catalog.utterlazy.com')
    .replace(/^http:\/\/127\.0\.0\.1:8001/i, 'https://catalog.utterlazy.com')
    .replace(/^http:\/\/127\.0\.0\.1:8000/i, 'https://catalog.utterlazy.com')
}

const getToken = () => localStorage.getItem('utterlazy_token')
const setToken = (token) => localStorage.setItem('utterlazy_token', token)
const clearToken = () => localStorage.removeItem('utterlazy_token')

const authHeaders = () => {
  const token = getToken()
  const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' }
  if (token) headers['Authorization'] = `Bearer ${token}`
  return headers
}

// ─── Auth ───
export const register = async (data) => {
  const res = await fetch(`${API_BASE}/auth/register`, {
    method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify(data)
  })
  const json = await res.json()
  if (!res.ok) throw json
  setToken(json.token)
  return json
}

export const login = async (email, password) => {
  const res = await fetch(`${API_BASE}/auth/login`, {
    method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify({ email, password })
  })
  const json = await res.json()
  if (!res.ok) throw json
  setToken(json.token)
  return json
}

export const getMe = async () => {
  const res = await fetch(`${API_BASE}/auth/me`, { headers: authHeaders() })
  if (!res.ok) {
    if (res.status === 401) { clearToken(); throw new Error('Session expired') }
    throw new Error('Failed to fetch profile')
  }
  const json = await res.json()
  if (json.recent_orders) {
    json.recent_orders.forEach(order => {
      if (order.items) {
        order.items.forEach(item => {
          if (item.menu_item && item.menu_item.image_url) item.menu_item.image_url = sanitizeImageUrl(item.menu_item.image_url)
        })
      }
    })
  }
  return json
}

export const updateProfile = async (data) => {
  const res = await fetch(`${API_BASE}/auth/profile`, {
    method: 'PATCH', headers: authHeaders(),
    body: JSON.stringify(data)
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const changePassword = async (data) => {
  const res = await fetch(`${API_BASE}/auth/change-password`, {
    method: 'POST', headers: authHeaders(),
    body: JSON.stringify(data)
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const logout = async () => {
  try {
    await fetch(`${API_BASE}/auth/logout`, { method: 'POST', headers: authHeaders() })
  } catch { /* ignore */ }
  clearToken()
}

export const isAuthenticated = () => !!getToken()

// ─── Catalog ───
export const getRestaurants = async (lat, lng, category = '', gstReady = false) => {
  let url = `${API_BASE}/restaurants`
  const params = new URLSearchParams()
  if (lat && lng) { params.append('lat', lat); params.append('lng', lng) }
  if (category) params.append('category', category)
  if (gstReady) params.append('gst_ready', 'true')
  if (params.toString()) url += `?${params.toString()}`
  const res = await fetch(url)
  if (!res.ok) throw new Error('Failed to fetch restaurants')
  const json = await res.json()
  const data = json.data || []
  data.forEach(r => { if (r.logo_url) r.logo_url = sanitizeImageUrl(r.logo_url) })
  return data
}

export const getGlobalCategories = async () => {
  const res = await fetch(`${API_BASE}/global-categories`)
  if (!res.ok) throw new Error('Failed to fetch global categories')
  const json = await res.json()
  const data = json.data || []
  data.forEach(cat => { if (cat.image_url) cat.image_url = sanitizeImageUrl(cat.image_url) })
  return data
}

export const getRestaurant = async (id) => {
  const res = await fetch(`${API_BASE}/restaurants/${id}`)
  if (!res.ok) throw new Error('Failed to fetch restaurant')
  const json = await res.json()
  const data = json.data
  if (data && data.logo_url) data.logo_url = sanitizeImageUrl(data.logo_url)
  return data
}

export const getMenuItems = async (restaurantId) => {
  const res = await fetch(`${API_BASE}/menu-items`)
  if (!res.ok) throw new Error('Failed to fetch menu items')
  const json = await res.json()
  const data = json.data || []
  data.forEach(item => { if (item.image_url) item.image_url = sanitizeImageUrl(item.image_url) })
  return data.filter(item => item.restaurant_id === restaurantId)
}

// ─── Search ───
export const searchCatalog = async (query) => {
  const res = await fetch(`${API_BASE}/search?q=${encodeURIComponent(query)}`)
  if (!res.ok) throw new Error('Search failed')
  const json = await res.json()
  if (json.restaurants) {
    json.restaurants.forEach(r => { if (r.logo_url) r.logo_url = sanitizeImageUrl(r.logo_url) })
  }
  if (json.dishes) {
    json.dishes.forEach(d => { if (d.image_url) d.image_url = sanitizeImageUrl(d.image_url) })
  }
  return json
}

export const voiceSearch = async (text) => {
  const res = await fetch(`${API_BASE}/search/voice`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ text }),
  })
  if (!res.ok) throw new Error('Voice search failed')
  const json = await res.json()
  if (json.restaurants) {
    json.restaurants.forEach(r => { if (r.logo_url) r.logo_url = sanitizeImageUrl(r.logo_url) })
  }
  if (json.menu_items) {
    json.menu_items.forEach(m => { if (m.image_url) m.image_url = sanitizeImageUrl(m.image_url); if (m.restaurant?.logo_url) m.restaurant.logo_url = sanitizeImageUrl(m.restaurant.logo_url) })
  }
  return json
}

// ─── Cart ───
export const getCart = async () => {
  const res = await fetch(`${API_BASE}/cart`, { headers: authHeaders() })
  if (!res.ok) return { cart: null, items: [], subtotal: 0 }
  const json = await res.json()
  if (json.items) {
    json.items.forEach(item => {
      if (item.image_url) item.image_url = sanitizeImageUrl(item.image_url)
      if (item.menu_item && item.menu_item.image_url) item.menu_item.image_url = sanitizeImageUrl(item.menu_item.image_url)
    })
  }
  return json
}

export const addToCart = async (menuItemId, quantity = 1) => {
  const res = await fetch(`${API_BASE}/cart/items`, {
    method: 'POST', headers: authHeaders(),
    body: JSON.stringify({ menu_item_id: menuItemId, quantity })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const updateCartItem = async (cartItemId, quantity) => {
  const res = await fetch(`${API_BASE}/cart/items/${cartItemId}`, {
    method: 'PATCH', headers: authHeaders(),
    body: JSON.stringify({ quantity })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const removeFromCart = async (cartItemId) => {
  const res = await fetch(`${API_BASE}/cart/items/${cartItemId}`, {
    method: 'DELETE', headers: authHeaders()
  })
  if (!res.ok) throw new Error('Failed to remove item')
  return await res.json()
}

export const clearCart = async () => {
  const res = await fetch(`${API_BASE}/cart`, {
    method: 'DELETE', headers: authHeaders()
  })
  if (!res.ok) throw new Error('Failed to clear cart')
  return await res.json()
}

// ─── Orders ───
export const getOrders = async (page = 1, status = 'all') => {
  let url = `${API_BASE}/orders?page=${page}`
  if (status !== 'all') url += `&status=${status}`
  const res = await fetch(url, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch orders')
  const json = await res.json()
  if (json.recent_orders) {
    json.recent_orders.forEach(order => {
      if (order.items) {
        order.items.forEach(item => {
          if (item.menu_item && item.menu_item.image_url) item.menu_item.image_url = sanitizeImageUrl(item.menu_item.image_url)
        })
      }
    })
  }
  return json
}

export const checkoutOrder = async (payload) => {
  const res = await fetch(`${API_BASE}/checkout`, {
    method: 'POST', headers: authHeaders(),
    body: JSON.stringify(payload)
  })
  if (!res.ok) {
    const errorData = await res.json()
    throw new Error(errorData.error || 'Failed to process checkout')
  }
  return await res.json()
}

export const reorderOrder = async (orderId) => {
  const res = await fetch(`${API_BASE}/orders/${orderId}/reorder`, {
    method: 'POST', headers: authHeaders(),
  })
  if (!res.ok) throw new Error('Reorder failed')
  return await res.json()
}

export const getOrder = async (id) => {
  const res = await fetch(`${API_BASE}/orders/${id}`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch order')
  const json = await res.json()
  if (json.order && json.order.items) {
    json.order.items.forEach(item => {
      if (item.menu_item && item.menu_item.image_url) item.menu_item.image_url = sanitizeImageUrl(item.menu_item.image_url)
    })
  }
  return json
}

export const trackOrder = async (id) => {
  const res = await fetch(`${API_BASE}/orders/${id}/track`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Tracking failed')
  return await res.json()
}

export const cancelOrder = async (id) => {
  const res = await fetch(`${API_BASE}/orders/${id}/cancel`, {
    method: 'PATCH', headers: authHeaders()
  })
  if (!res.ok) {
    const errorData = await res.json()
    throw new Error(errorData.error || 'Failed to cancel order')
  }
  return await res.json()
}

export const payOrderOnline = async (id) => {
  const res = await fetch(`${API_BASE}/orders/${id}/pay-online`, {
    method: 'POST', headers: authHeaders()
  })
  if (!res.ok) {
    const errorData = await res.json()
    throw new Error(errorData.error || 'Payment failed')
  }
  return await res.json()
}

export const createPaymentOrder = async (orderId) => {
  const res = await fetch(`${API_BASE}/orders/${orderId}/pay/create`, {
    method: 'POST',
    headers: authHeaders()
  })
  if (!res.ok) {
    const errorData = await res.json()
    throw new Error(errorData.error || 'Failed to create payment order')
  }
  return await res.json()
}

export const verifyPayment = async (orderId, payload) => {
  const res = await fetch(`${API_BASE}/orders/${orderId}/pay/verify`, {
    method: 'POST',
    headers: authHeaders(),
    body: JSON.stringify(payload)
  })
  if (!res.ok) {
    const errorData = await res.json()
    throw new Error(errorData.error || 'Payment verification failed')
  }
  return await res.json()
}

// ─── Addresses ───
export const getAddresses = async () => {
  const res = await fetch(`${API_BASE}/addresses`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch addresses')
  return await res.json()
}

export const addAddress = async (data) => {
  const res = await fetch(`${API_BASE}/addresses`, {
    method: 'POST', headers: authHeaders(),
    body: JSON.stringify(data)
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const updateAddress = async (id, data) => {
  const res = await fetch(`${API_BASE}/addresses/${id}`, {
    method: 'PUT', headers: authHeaders(),
    body: JSON.stringify(data)
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const deleteAddress = async (id) => {
  const res = await fetch(`${API_BASE}/addresses/${id}`, {
    method: 'DELETE', headers: authHeaders()
  })
  if (!res.ok) throw new Error('Failed to delete address')
  return await res.json()
}

export const setDefaultAddress = async (id) => {
  const res = await fetch(`${API_BASE}/addresses/${id}/default`, {
    method: 'PATCH', headers: authHeaders()
  })
  if (!res.ok) throw new Error('Failed to set default address')
  return await res.json()
}

// ─── Coupons ───
export const getAvailableCoupons = async (subtotal) => {
  const res = await fetch(`${API_BASE}/coupons/available?subtotal=${subtotal}`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch coupons')
  return await res.json()
}

export const validateCoupon = async (code, subtotal) => {
  const res = await fetch(`${API_BASE}/coupons/validate`, {
    method: 'POST', headers: authHeaders(),
    body: JSON.stringify({ code, subtotal })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const getScratchCard = async () => {
  const res = await fetch(`${API_BASE}/scratch-card`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch scratch card')
  return await res.json()
}

// ─── Ratings ───
export const rateRestaurant = async (restaurantId, rating, feedback = '', orderId = null) => {
  const res = await fetch(`${API_BASE}/ratings`, {
    method: 'POST', headers: authHeaders(),
    body: JSON.stringify({ restaurant_id: restaurantId, rating, feedback, order_id: orderId })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const getRestaurantRatings = async (restaurantId) => {
  const res = await fetch(`${API_BASE}/restaurants/${restaurantId}/ratings`)
  if (!res.ok) throw new Error('Failed to fetch ratings')
  return await res.json()
}

// ─── Favorites ───
export const getFavorites = async () => {
  const res = await fetch(`${API_BASE}/favorites`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch favorites')
  return await res.json()
}

export const toggleFavorite = async (restaurantId) => {
  const res = await fetch(`${API_BASE}/favorites/toggle`, {
    method: 'POST', headers: authHeaders(),
    body: JSON.stringify({ restaurant_id: restaurantId })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

// ─── Restaurant Onboarding ───
export const applyAsRestaurant = async (data) => {
  const res = await fetch(`${API_BASE}/onboarding/apply`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
    body: JSON.stringify(data)
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const getOnboardingStatus = async () => {
  const res = await fetch(`${API_BASE}/onboarding/status`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch status')
  return await res.json()
}

// ─── Health Check ───
export const healthCheck = async () => {
  const res = await fetch(`${API_BASE}/health`)
  return await res.json()
}

// ─── Membership ───
export const getMembership = async () => {
  const res = await fetch(`${API_BASE}/membership`, { headers: authHeaders() })
  if (!res.ok) return { membership: null }
  return await res.json()
}

export const subscribeMembership = async (plan) => {
  const res = await fetch(`${API_BASE}/membership/subscribe`, {
    method: 'POST', headers: authHeaders(),
    body: JSON.stringify({ plan })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const cancelMembership = async () => {
  const res = await fetch(`${API_BASE}/membership/cancel`, {
    method: 'POST', headers: authHeaders()
  })
  if (!res.ok) throw new Error('Failed to cancel')
  return await res.json()
}

// ─── Referrals ───
export const getReferralCode = async () => {
  const res = await fetch(`${API_BASE}/referrals/my-code`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch referral code')
  return await res.json()
}

export const applyReferral = async (code) => {
  const res = await fetch(`${API_BASE}/referrals/apply`, {
    method: 'POST', headers: authHeaders(),
    body: JSON.stringify({ code })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

// ─── Gift Cards ───
export const purchaseGiftCard = async (data) => {
  const res = await fetch(`${API_BASE}/gift-cards/purchase`, {
    method: 'POST', headers: authHeaders(),
    body: JSON.stringify(data)
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const redeemGiftCard = async (code) => {
  const res = await fetch(`${API_BASE}/gift-cards/redeem`, {
    method: 'POST', headers: authHeaders(),
    body: JSON.stringify({ code })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

// ─── Complaints ───
export const submitComplaint = async (data) => {
  const res = await fetch(`${API_BASE}/complaints`, {
    method: 'POST', headers: authHeaders(),
    body: JSON.stringify(data)
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const getMyComplaints = async (page = 1) => {
  const res = await fetch(`${API_BASE}/complaints/my?page=${page}`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch complaints')
  return await res.json()
}

export const getGiftCardBalance = async () => {
  const res = await fetch(`${API_BASE}/gift-cards/balance`, { headers: authHeaders() })
  if (!res.ok) return { balance: 0 }
  return await res.json()
}

// ─── Search History ───
export const getRecentSearches = async () => {
  const res = await fetch(`${API_BASE}/search/recent`, { headers: authHeaders() })
  if (!res.ok) return { recent_searches: [] }
  return await res.json()
}

export const saveSearch = async (query) => {
  await fetch(`${API_BASE}/search/recent`, {
    method: 'POST', headers: authHeaders(),
    body: JSON.stringify({ query })
  })
}

export const clearSearchHistory = async () => {
  await fetch(`${API_BASE}/search/recent`, { method: 'DELETE', headers: authHeaders() })
}

// ─── Notification Preferences ───
export const getNotificationPrefs = async () => {
  const res = await fetch(`${API_BASE}/notification-preferences`, { headers: authHeaders() })
  if (!res.ok) return { preferences: {} }
  return await res.json()
}

export const updateNotificationPrefs = async (prefs) => {
  const res = await fetch(`${API_BASE}/notification-preferences`, {
    method: 'PATCH', headers: authHeaders(),
    body: JSON.stringify(prefs)
  })
  if (!res.ok) throw new Error('Failed to update')
  return await res.json()
}

// ─── Invoice ───
export const getInvoice = async (orderId) => {
  const res = await fetch(`${API_BASE}/orders/${orderId}/invoice`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to generate invoice')
  return await res.json()
}

// ─── Reviews ───
export const getRestaurantReviews = async (restaurantId) => {
  const res = await fetch(`${API_BASE}/restaurants/${restaurantId}/reviews`)
  if (!res.ok) throw new Error('Failed to fetch reviews')
  return await res.json()
}

export const submitReview = async (data) => {
  const res = await fetch(`${API_BASE}/reviews`, {
    method: 'POST', headers: authHeaders(),
    body: JSON.stringify(data)
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

// ─── Scheduled Orders ───
export const getScheduledOrders = async () => {
  const res = await fetch(`${API_BASE}/scheduled-orders`, { headers: authHeaders() })
  if (!res.ok) return { data: [] }
  return await res.json()
}

export const scheduleOrder = async (data) => {
  const res = await fetch(`${API_BASE}/scheduled-orders`, {
    method: 'POST', headers: authHeaders(),
    body: JSON.stringify(data)
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

// ─── Group Orders ───
export const createGroupOrder = async (data) => {
  const res = await fetch(`${API_BASE}/group-orders`, {
    method: 'POST', headers: authHeaders(),
    body: JSON.stringify(data)
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const joinGroupOrder = async (inviteCode) => {
  const res = await fetch(`${API_BASE}/group-orders/${inviteCode}/join`, {
    method: 'GET', headers: authHeaders()
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

// ─── Wallet ───
export const getWalletData = async () => {
  const res = await fetch(`${API_BASE}/wallet/balance`, { headers: authHeaders() })
  if (!res.ok) throw new Error('Failed to fetch wallet balance')
  return await res.json()
}

export const addWalletMoney = async (amount) => {
  const res = await fetch(`${API_BASE}/wallet/add`, {
    method: 'POST',
    headers: authHeaders(),
    body: JSON.stringify({ amount })
  })
  if (!res.ok) {
    const err = await res.json()
    throw new Error(err.error || 'Failed to deposit money')
  }
  return await res.json()
}

export const transferWalletMoney = async (recipient, amount) => {
  const res = await fetch(`${API_BASE}/wallet/transfer`, {
    method: 'POST',
    headers: authHeaders(),
    body: JSON.stringify({ recipient, amount })
  })
  if (!res.ok) {
    const err = await res.json()
    throw new Error(err.error || 'Failed to transfer money')
  }
  return await res.json()
}

export const requestPasswordResetOtp = async (email) => {
  const res = await fetch(`${API_BASE}/auth/password-reset/request`, {
    method: 'POST', headers: authHeaders(),
    body: JSON.stringify({ email })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const verifyPasswordResetOtp = async (email, otp) => {
  const res = await fetch(`${API_BASE}/auth/password-reset/verify`, {
    method: 'POST', headers: authHeaders(),
    body: JSON.stringify({ email, otp })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}

export const resetPassword = async (email, resetToken, password) => {
  const res = await fetch(`${API_BASE}/auth/password-reset/reset`, {
    method: 'POST', headers: authHeaders(),
    body: JSON.stringify({ email, reset_token: resetToken, password, password_confirmation: password })
  })
  const json = await res.json()
  if (!res.ok) throw json
  return json
}
