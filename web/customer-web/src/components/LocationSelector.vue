<template>
  <div v-if="!modelValue" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm p-4 overflow-hidden">
    <div class="bg-white w-full max-w-md rounded-[3rem] shadow-2xl transform transition-all border border-gray-100 flex flex-col max-h-[90vh]">
      <div class="p-8 md:p-10 overflow-y-auto no-scrollbar">
        <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-orange-50 text-primary mb-6 shadow-soft">
          <span class="material-symbols-outlined text-4xl">location_on</span>
        </div>
        <h2 class="text-3xl font-black text-on-surface mb-3 tracking-tight uppercase">Where to?</h2>
        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400">Discover culinary artisans near you.</p>
      </div>

      <!-- Ola Maps Search -->
      <div class="relative mb-8">
        <div class="flex items-center gap-4 bg-gray-50 border border-gray-100 rounded-2xl px-6 py-5 focus-within:ring-2 focus-within:ring-primary/20 focus-within:bg-white focus-within:border-primary/50 transition-all">
          <span class="material-symbols-outlined text-primary text-[24px]">search</span>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search for area, street, landmark..."
            class="flex-1 bg-transparent text-on-surface font-bold text-sm focus:outline-none placeholder:text-gray-300 placeholder:font-medium"
            @input="handleInput"
            @focus="showDropdown = true"
          />
          <button v-if="searchQuery" @click="clearSearch" class="material-symbols-outlined text-gray-300 text-[18px] hover:text-error transition-colors">close</button>
        </div>

        <!-- Autocomplete Dropdown -->
        <div v-if="showDropdown && suggestions.length > 0" class="absolute top-full left-0 right-0 mt-2 bg-white rounded-2xl border border-gray-100 shadow-2xl z-50 max-h-64 overflow-y-auto no-scrollbar">
          <div
            v-for="(s, i) in suggestions" :key="i"
            @click="selectSuggestion(s)"
            class="px-8 py-5 flex items-start gap-4 cursor-pointer hover:bg-orange-50/30 transition-colors border-b border-gray-50 last:border-0"
          >
            <span class="material-symbols-outlined text-primary mt-1 text-[20px]">location_on</span>
            <div class="min-w-0">
              <p class="font-black text-sm text-on-surface truncate uppercase tracking-tight">{{ s.mainText }}</p>
              <p class="text-[10px] text-gray-400 truncate font-bold uppercase tracking-widest">{{ s.secondaryText }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Use Current Location -->
      <button @click="useCurrentLocation" :disabled="locating"
        class="w-full flex items-center gap-5 p-6 rounded-3xl border border-orange-100 bg-orange-50/50 hover:bg-orange-100/50 transition-all text-left mb-8 group relative overflow-hidden">
        <div class="w-14 h-14 rounded-2xl bg-primary flex items-center justify-center shrink-0 shadow-lg shadow-primary/20">
          <span class="material-symbols-outlined text-white text-[24px]" :class="locating ? 'animate-spin' : ''">{{ locating ? 'progress_activity' : 'my_location' }}</span>
        </div>
        <div class="relative z-10">
          <div class="font-black text-primary text-[11px] uppercase tracking-[0.15em] mb-1">{{ locating ? 'Synchronizing GPS...' : 'Use Current Location' }}</div>
          <div class="text-[9px] text-gray-400 font-black uppercase tracking-widest">Powered by Ola Intelligence</div>
        </div>
        <div class="absolute right-8 top-1/2 -translate-y-1/2 opacity-20 group-hover:opacity-100 group-hover:translate-x-2 transition-all">
           <span class="material-symbols-outlined text-primary">arrow_forward</span>
        </div>
      </button>

      <!-- Error -->
      <div v-if="error" class="mb-4 p-3 rounded-xl bg-error/10 border border-error/20 flex items-center gap-3">
        <span class="material-symbols-outlined text-error text-[18px]">error</span>
        <p class="text-[11px] text-error font-bold uppercase tracking-widest">{{ error }}</p>
      </div>

      <!-- Real Nearby Areas -->
      <div class="border-t border-gray-50 pt-8">
        <div class="flex items-center justify-between mb-6 px-1">
          <p class="text-[9px] uppercase tracking-[0.3em] font-black text-gray-400">
            {{ nearbyLoading ? 'Mapping Sector...' : 'Nearby Sectors' }}
          </p>
          <div v-if="gpsDetected" class="flex items-center gap-1.5 bg-green-50 px-3 py-1.5 rounded-full">
            <div class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></div>
            <span class="text-[8px] font-black text-green-600 uppercase tracking-[0.1em]">Live GPS</span>
          </div>
        </div>
        
        <div class="grid grid-cols-1 gap-4 max-h-64 overflow-y-auto no-scrollbar">
          <button
            v-for="loc in displayAreas"
            :key="loc.id || loc.placeId"
            @click="selectLocation(loc)"
            class="w-full flex items-center gap-5 p-5 rounded-2xl border border-gray-50 hover:border-primary/20 hover:bg-orange-50/30 transition-all text-left group"
          >
            <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 bg-gray-50 group-hover:bg-primary/10 transition-colors">
              <span class="material-symbols-outlined text-[20px] text-gray-400 group-hover:text-primary transition-colors">
                {{ gpsDetected ? 'near_me' : 'location_city' }}
              </span>
            </div>
            <div class="min-w-0">
              <div class="font-black text-on-surface text-sm truncate uppercase tracking-tight">{{ loc.name }}</div>
              <div class="text-[9px] text-gray-400 font-black truncate uppercase tracking-widest mt-0.5">{{ loc.city }}</div>
            </div>
          </button>

          <div v-if="nearbyLoading" class="space-y-4">
             <div v-for="i in 3" :key="i" class="w-full h-20 rounded-2xl bg-gray-50 animate-pulse"></div>
          </div>

          <div v-if="!nearbyLoading && displayAreas.length === 0" class="py-12 text-center">
             <p class="text-[10px] font-black text-gray-300 uppercase tracking-[0.2em]">Scanning for Sectors...</p>
          </div>
        </div>
      </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { olaAutocomplete, olaReverseGeocode, olaNearbySearch } from '../olaMaps'

const props = defineProps(['modelValue'])
const emit = defineEmits(['update:modelValue'])

const searchQuery = ref('')
const suggestions = ref([])
const showDropdown = ref(false)
const locating = ref(false)
const error = ref('')
const nearbyAreas = ref([])
const nearbyLoading = ref(false)
const gpsDetected = ref(false)
let debounceTimer = null

// Fallback Locations
const fallbackLocations = [
  { id: 'indiranagar', name: 'Indiranagar', city: 'Bengaluru', lat: 12.9716, lng: 77.6412 },
  { id: 'koramangala', name: 'Koramangala', city: 'Bengaluru', lat: 12.9352, lng: 77.6245 },
  { id: 'hitech_city', name: 'Hitech City', city: 'Hyderabad', lat: 17.4483, lng: 78.3915 },
]

const displayAreas = computed(() => {
  // If we have real nearby areas from API, use them.
  if (nearbyAreas.value.length > 0) return nearbyAreas.value
  
  // If GPS is detected but nearby search is still fetching, don't show fallbacks
  if (gpsDetected.value && nearbyLoading.value) return []
  
  // Only show fallbacks if GPS is not yet detected or not supported
  return gpsDetected.value ? [] : fallbackLocations
})

onMounted(() => {
  const savedLocation = localStorage.getItem('utterlazy_location')
  if (savedLocation) {
    emit('update:modelValue', JSON.parse(savedLocation))
    return
  }
  // Auto-detect nearby areas on mount
  autoDetectNearby()
})

const autoDetectNearby = () => {
  if (!navigator.geolocation) {
    // If no GPS, try to get rough city via IP (optional, but for now fallback)
    return
  }
  
  nearbyLoading.value = true
  navigator.geolocation.getCurrentPosition(
    async (pos) => {
      try {
        const { latitude: lat, longitude: lng } = pos.coords
        gpsDetected.value = true
        
        // Show current precise area as first option immediately
        const rev = await olaReverseGeocode(lat, lng)
        if (rev?.components?.area) {
          nearbyAreas.value = [{
            id: 'current_precise',
            name: rev.components.area,
            city: rev.components.city || 'Your Location',
            lat, lng,
          }]
        }

        // Fetch other nearby points of interest
        const places = await olaNearbySearch(lat, lng)
        if (places && places.length > 0) {
          const mapped = places.map(p => ({
            id: p.placeId || p.name.toLowerCase().replace(/\s+/g, '_'),
            name: p.name,
            city: p.city || rev?.components?.city || 'Nearby',
            lat: p.lat,
            lng: p.lng,
          }))
          // Merge with current area, avoiding duplicates
          const existingNames = nearbyAreas.value.map(a => a.name.toLowerCase())
          const uniqueNew = mapped.filter(m => !existingNames.includes(m.name.toLowerCase()))
          nearbyAreas.value = [...nearbyAreas.value, ...uniqueNew].slice(0, 5)
        }
      } catch (e) { 
        console.error('Detection failed:', e)
        error.value = 'Precision scan failed.'
      } finally { 
        nearbyLoading.value = false 
      }
    },
    (err) => { 
      nearbyLoading.value = false
      gpsDetected.value = false
      console.warn('Geolocation blocked or failed', err)
    },
    { enableHighAccuracy: true, timeout: 10000 }
  )
}

const handleInput = () => {
  error.value = ''
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(async () => {
    if (searchQuery.value.length < 3) { suggestions.value = []; return }
    try {
      suggestions.value = await olaAutocomplete(searchQuery.value)
      showDropdown.value = true
    } catch (e) { console.error(e) }
  }, 350)
}

const selectSuggestion = (s) => {
  const loc = {
    id: s.placeId || s.mainText.toLowerCase().replace(/\s+/g, '_'),
    name: s.mainText,
    city: s.secondaryText?.split(',').pop()?.trim() || '',
    lat: s.lat,
    lng: s.lng,
  }
  searchQuery.value = ''
  suggestions.value = []
  showDropdown.value = false
  selectLocation(loc)
}

const useCurrentLocation = () => {
  if (!navigator.geolocation) { error.value = 'GPS hardware not detected.'; return }
  locating.value = true; error.value = ''
  navigator.geolocation.getCurrentPosition(
    async (pos) => {
      try {
        const result = await olaReverseGeocode(pos.coords.latitude, pos.coords.longitude)
        if (result) {
          selectLocation({
            id: 'current_location',
            name: result.components?.area || 'Current Sector',
            city: result.components?.city || '',
            lat: pos.coords.latitude,
            lng: pos.coords.longitude,
          })
        } else { error.value = 'Signal lost. Try again.' }
      } catch (e) { error.value = 'Intelligence failure.' }
      finally { locating.value = false }
    },
    (err) => {
      locating.value = false
      error.value = err.code === 1 ? 'Permission denied.' : 'GPS signal weak.'
    },
    { enableHighAccuracy: true, timeout: 10000 }
  )
}

const clearSearch = () => { searchQuery.value = ''; suggestions.value = []; showDropdown.value = false }

const selectLocation = (loc) => {
  localStorage.setItem('utterlazy_location', JSON.stringify(loc))
  emit('update:modelValue', loc)
}
</script>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
</style>
