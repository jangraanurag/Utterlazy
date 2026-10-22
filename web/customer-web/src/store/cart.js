import { reactive, computed, watch } from 'vue'

const savedCart = localStorage.getItem('utterlazy_cart')

export const state = reactive({
  items: savedCart ? JSON.parse(savedCart) : [],
  restaurantId: null,
  restaurantName: null,
})

// Restore restaurant info from items
if (state.items.length > 0) {
  state.restaurantId = state.items[0].restaurant_id || null
  state.restaurantName = state.items[0].restaurantName || null
}

watch(() => state.items, (newItems) => {
  localStorage.setItem('utterlazy_cart', JSON.stringify(newItems))
}, { deep: true })

export const addToCart = (product, restaurant = null) => {
  // Enforce single-restaurant cart
  if (state.items.length > 0 && product.restaurant_id && state.restaurantId && product.restaurant_id !== state.restaurantId) {
    if (!confirm('Adding from a different restaurant will clear your current cart. Continue?')) {
      return false
    }
    state.items = []
  }

  // Track restaurant
  if (product.restaurant_id) {
    state.restaurantId = product.restaurant_id
  }
  if (restaurant) {
    state.restaurantName = restaurant.name || restaurant
  }

  const existingItem = state.items.find(item => item.id === product.id)
  if (existingItem) {
    existingItem.quantity += 1
  } else {
    state.items.push({
      ...product,
      restaurantName: state.restaurantName,
      quantity: 1
    })
  }
  return true
}

export const removeFromCart = (productId) => {
  const index = state.items.findIndex(item => item.id === productId)
  if (index !== -1) {
    if (state.items[index].quantity > 1) {
      state.items[index].quantity -= 1
    } else {
      state.items.splice(index, 1)
    }
  }
  // Reset restaurant if cart is empty
  if (state.items.length === 0) {
    state.restaurantId = null
    state.restaurantName = null
  }
}

export const clearCart = () => {
  state.items = []
  state.restaurantId = null
  state.restaurantName = null
}

export const cartTotal = computed(() => {
  return state.items.reduce((total, item) => total + (item.price * item.quantity), 0)
})

export const cartItemCount = computed(() => {
  return state.items.reduce((count, item) => count + item.quantity, 0)
})
