<template>
  <div>
    <h1 class="text-3xl font-serif font-bold text-on-surface mb-2">Ranking Algorithm Config</h1>
    <p class="text-on-surface-variant mb-8">Tune weights and thresholds for the restaurant ranking engine. Changes take effect immediately.</p>

    <div v-if="loading" class="py-12 text-center text-on-surface-variant">
      <span class="material-symbols-outlined text-4xl animate-spin mb-4 block">sync</span>
      <p>Loading...</p>
    </div>

    <form v-else @submit.prevent="saveAll" class="max-w-3xl space-y-6">
      <!-- Weights -->
      <section class="bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/30 shadow-sm space-y-5">
        <h2 class="text-xl font-bold border-b border-outline-variant/30 pb-3">Score Weights</h2>
        <p class="text-xs text-on-surface-variant -mt-3">Must sum to 1.0</p>

        <div v-for="w in weightFields" :key="w.key" class="flex items-center gap-4">
          <label class="w-48 text-sm font-bold">{{ w.label }}</label>
          <input type="range" :min="0" :max="1" step="0.01" v-model.number="config[w.key]"
            class="flex-1 accent-primary" />
          <span class="w-16 text-right font-mono text-sm">{{ Number(config[w.key]).toFixed(2) }}</span>
        </div>

        <div class="pt-2 text-xs font-mono">
          Sum: <span :class="weightSum === 1.0 ? 'text-green-400' : 'text-red-400'">{{ weightSum.toFixed(2) }}</span>
        </div>
      </section>

      <!-- Rating Thresholds -->
      <section class="bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/30 shadow-sm space-y-4">
        <h2 class="text-xl font-bold border-b border-outline-variant/30 pb-3">Rating Thresholds</h2>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-bold mb-1">Gold Threshold (≥ gets +25% bonus)</label>
            <input type="number" step="0.1" v-model.number="config.ranking_rating_gold_threshold" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2" />
          </div>
          <div>
            <label class="block text-sm font-bold mb-1">Bad Threshold (&lt; gets 50% penalty)</label>
            <input type="number" step="0.1" v-model.number="config.ranking_rating_bad_threshold" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2" />
          </div>
        </div>
      </section>

      <!-- Cancel Sigmoid -->
      <section class="bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/30 shadow-sm space-y-4">
        <h2 class="text-xl font-bold border-b border-outline-variant/30 pb-3">Cancellation Penalty (Sigmoid)</h2>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-bold mb-1">Theta (threshold point)</label>
            <input type="number" step="0.001" v-model.number="config.ranking_cancel_sigmoid_theta" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2" />
          </div>
          <div>
            <label class="block text-sm font-bold mb-1">K (steepness)</label>
            <input type="number" step="1" v-model.number="config.ranking_cancel_sigmoid_k" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2" />
          </div>
        </div>
      </section>

      <!-- EMA Alphas -->
      <section class="bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/30 shadow-sm space-y-4">
        <h2 class="text-xl font-bold border-b border-outline-variant/30 pb-3">EMA Smoothing Factors (α)</h2>
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-bold mb-1">High Volume</label>
            <input type="number" step="0.01" v-model.number="config.ranking_ema_high_vol_alpha" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2" />
          </div>
          <div>
            <label class="block text-sm font-bold mb-1">New Restaurant</label>
            <input type="number" step="0.01" v-model.number="config.ranking_ema_new_alpha" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2" />
          </div>
          <div>
            <label class="block text-sm font-bold mb-1">Default</label>
            <input type="number" step="0.01" v-model.number="config.ranking_ema_default_alpha" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2" />
          </div>
        </div>
      </section>

      <!-- Dynamic Weights -->
      <section class="bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/30 shadow-sm space-y-4">
        <h2 class="text-xl font-bold border-b border-outline-variant/30 pb-3">Dynamic Weights (Peak / Off-Peak)</h2>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-bold mb-1">Peak Hr - W_Operational</label>
            <input type="number" step="0.01" v-model.number="config.ranking_peak_w_operational" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2" />
          </div>
          <div>
            <label class="block text-sm font-bold mb-1">Peak Hr - W_Exploration</label>
            <input type="number" step="0.01" v-model.number="config.ranking_peak_w_exploration" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2" />
          </div>
          <div>
            <label class="block text-sm font-bold mb-1">Off-Peak - W_Exploration</label>
            <input type="number" step="0.01" v-model.number="config.ranking_offpeak_w_exploration" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2" />
          </div>
        </div>
      </section>

      <!-- GSP / Misc -->
      <section class="bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/30 shadow-sm space-y-4">
        <h2 class="text-xl font-bold border-b border-outline-variant/30 pb-3">Auction &amp; Misc</h2>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-bold mb-1">GSP Min Relevance</label>
            <input type="number" step="0.05" v-model.number="config.ranking_gsp_min_relevance" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2" />
          </div>
          <div>
            <label class="block text-sm font-bold mb-1">GSP Slot Positions (comma-separated)</label>
            <input type="text" v-model="config.ranking_gsp_slot_positions" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2" />
          </div>
          <div>
            <label class="block text-sm font-bold mb-1">Freshness (days)</label>
            <input type="number" step="1" v-model.number="config.ranking_freshness_days" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2" />
          </div>
          <div>
            <label class="block text-sm font-bold mb-1">Diversity Penalty</label>
            <input type="number" step="0.01" v-model.number="config.ranking_diversity_penalty" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2" />
          </div>
        </div>
      </section>

      <button type="submit" :disabled="saving"
        class="px-8 py-3 rounded-xl bg-primary text-on-primary font-bold text-sm hover:bg-primary/90 transition-colors disabled:opacity-50">
        {{ saving ? 'Saving...' : 'Save Configuration' }}
      </button>

      <div v-if="saveMsg" class="text-sm" :class="saveMsgType === 'success' ? 'text-green-400' : 'text-red-400'">
        {{ saveMsg }}
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { getRankingConfig, saveRankingConfig } from '../api.js'

const config = ref({})
const loading = ref(true)
const saving = ref(false)
const saveMsg = ref('')
const saveMsgType = ref('success')

const weightFields = [
  { key: 'ranking_w_organic', label: 'W_Organic (Quality)' },
  { key: 'ranking_w_operational', label: 'W_Operational (Prep)' },
  { key: 'ranking_w_rider_feedback', label: 'W_Rider Feedback' },
  { key: 'ranking_w_proximity', label: 'W_Proximity' },
  { key: 'ranking_w_exploration', label: 'W_Exploration' },
  { key: 'ranking_w_sponsored', label: 'W_Sponsored' },
]

const weightSum = computed(() => {
  let sum = 0
  for (const w of weightFields) {
    sum += Number(config.value[w.key]) || 0
  }
  return sum
})

const fetchData = async () => {
  loading.value = true
  try {
    const data = await getRankingConfig()
    config.value = data
  } catch (e) {
    saveMsg.value = 'Failed to load: ' + e.message
    saveMsgType.value = 'error'
  } finally {
    loading.value = false
  }
}

const saveAll = async () => {
  saving.value = true
  saveMsg.value = ''
  try {
    await saveRankingConfig(config.value)
    saveMsg.value = 'Configuration saved successfully!'
    saveMsgType.value = 'success'
  } catch (e) {
    saveMsg.value = 'Failed to save: ' + e.message
    saveMsgType.value = 'error'
  } finally {
    saving.value = false
  }
}

onMounted(fetchData)
</script>
