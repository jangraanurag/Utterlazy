<template>
  <div class="space-y-3">
    <!-- Search Input with Autocomplete -->
    <div class="relative">
      <div class="flex items-center gap-2 bg-surface-container-lowest border border-outline-variant/30 rounded-xl px-4 py-3 focus-within:ring-2 focus-within:ring-primary/30 focus-within:border-primary transition-all">
        <span class="material-symbols-outlined text-primary text-[20px]">search</span>
        <input
          v-model="searchQuery"
          type="text"
          :placeholder="placeholder"
          class="flex-1 bg-transparent text-on-surface font-medium text-sm focus:outline-none"
          @input="handleInput"
          @focus="showDropdown = true"
        />
        <button v-if="searchQuery" @click="clearSearch" class="material-symbols-outlined text-on-surface-variant text-[18px] hover:text-error transition-colors">close</button>
        <button @click="useCurrentLocation" :disabled="locating" class="flex items-center gap-1 text-primary font-bold text-xs hover:bg-primary/10 px-2 py-1 rounded-lg transition-colors whitespace-nowrap" title="Use current location">
          <span class="material-symbols-outlined text-[16px]" :class="locating ? 'animate-spin' : ''">{{ locating ? 'progress_activity' : 'my_location' }}</span>
          <span class="hidden sm:inline">Current</span>
        </button>
      </div>

      <!-- Autocomplete Dropdown -->
      <div v-if="showDropdown && suggestions.length > 0" class="absolute top-full left-0 right-0 mt-1 bg-surface-container-lowest rounded-xl border border-outline-variant/30 shadow-[0_20px_40px_rgba(0,0,0,0.12)] z-50 max-h-64 overflow-y-auto">
        <div
          v-for="(s, i) in suggestions" :key="i"
          @click="selectSuggestion(s)"
          class="px-4 py-3 flex items-start gap-3 cursor-pointer hover:bg-surface-container-low transition-colors border-b border-outline-variant/10 last:border-0"
        >
          <span class="material-symbols-outlined text-primary mt-0.5 text-[18px]">location_on</span>
          <div class="min-w-0">
            <p class="font-bold text-sm text-on-surface truncate">{{ s.mainText }}</p>
            <p class="text-xs text-on-surface-variant truncate">{{ s.secondaryText }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Click outside to close -->
    <div v-if="showDropdown && suggestions.length > 0" class="fixed inset-0 z-40" @click="showDropdown = false"></div>

    <!-- Selected Location Preview -->
    <div v-if="selectedLocation" class="flex items-center gap-3 p-3 rounded-xl bg-primary/5 border border-primary/20">
      <div class="w-10 h-10 rounded-full bg-primary flex items-center justify-center shrink-0">
        <span class="material-symbols-outlined text-on-primary text-[20px]" style="font-variation-settings: 'FILL' 1;">pin_drop</span>
      </div>
      <div class="flex-1 min-w-0">
        <p class="font-bold text-sm text-on-surface truncate">{{ selectedLocation.mainText || selectedLocation.formatted_address }}</p>
        <p class="text-xs text-on-surface-variant truncate">{{ selectedLocation.secondaryText || '' }}</p>
        <p v-if="selectedLocation.lat" class="text-[10px] text-primary font-mono mt-0.5">{{ selectedLocation.lat.toFixed(5) }}, {{ selectedLocation.lng.toFixed(5) }}</p>
      </div>
      <button @click="clearSelection" class="material-symbols-outlined text-on-surface-variant hover:text-error text-[18px]">close</button>
    </div>

    <!-- Error message -->
    <p v-if="error" class="text-xs text-error font-bold flex items-center gap-1">
      <span class="material-symbols-outlined text-[14px]">error</span> {{ error }}
    </p>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { olaAutocomplete, olaReverseGeocode } from '../olaMaps'

const props = defineProps({
  placeholder: { type: String, default: 'Search for area, street name...' },
  modelValue: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'locationSelected'])

const searchQuery = ref('')
const suggestions = ref([])
const showDropdown = ref(false)
const selectedLocation = ref(props.modelValue)
const locating = ref(false)
const error = ref('')
let debounceTimer = null

const handleInput = () => {
  error.value = ''
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(async () => {
    if (searchQuery.value.length < 3) {
      suggestions.value = []
      return
    }
    try {
      suggestions.value = await olaAutocomplete(searchQuery.value)
      showDropdown.value = true
    } catch (e) {
      console.error(e)
    }
  }, 350)
}

const selectSuggestion = (s) => {
  selectedLocation.value = s
  searchQuery.value = ''
  suggestions.value = []
  showDropdown.value = false
  emit('update:modelValue', s)
  emit('locationSelected', s)
}

const useCurrentLocation = () => {
  if (!navigator.geolocation) {
    error.value = 'Geolocation not supported by your browser.'
    return
  }
  locating.value = true
  error.value = ''
  navigator.geolocation.getCurrentPosition(
    async (pos) => {
      try {
        const result = await olaReverseGeocode(pos.coords.latitude, pos.coords.longitude)
        if (result) {
          const loc = {
            mainText: result.components?.area || 'Current Location',
            secondaryText: result.formatted_address,
            lat: pos.coords.latitude,
            lng: pos.coords.longitude,
            components: result.components,
          }
          selectedLocation.value = loc
          emit('update:modelValue', loc)
          emit('locationSelected', loc)
        } else {
          error.value = 'Could not determine your location.'
        }
      } catch (e) {
        error.value = 'Failed to reverse geocode.'
      } finally {
        locating.value = false
      }
    },
    (err) => {
      locating.value = false
      error.value = err.code === 1 ? 'Location permission denied.' : 'Could not get your location.'
    },
    { enableHighAccuracy: true, timeout: 10000 }
  )
}

const clearSearch = () => {
  searchQuery.value = ''
  suggestions.value = []
  showDropdown.value = false
}

const clearSelection = () => {
  selectedLocation.value = null
  emit('update:modelValue', null)
}

watch(() => props.modelValue, (v) => { selectedLocation.value = v })
</script>
