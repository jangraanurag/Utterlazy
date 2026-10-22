import { ref, reactive } from 'vue'
import { getVendorProfile } from './api'

const restaurant = ref(null)
const stats = ref(null)
const loading = ref(true)

export function useAuth() {
  async function loadProfile() {
    try {
      const data = await getVendorProfile()
      restaurant.value = data.restaurant
      stats.value = data.stats
    } catch {
      localStorage.removeItem('vendor_token')
      restaurant.value = null
      stats.value = null
    } finally {
      loading.value = false
    }
  }

  function logout() {
    localStorage.removeItem('vendor_token')
    restaurant.value = null
    stats.value = null
  }

  return { restaurant, stats, loading, loadProfile, logout }
}
