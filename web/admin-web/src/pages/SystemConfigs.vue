<template>
  <div>
    <h1 class="text-3xl font-serif font-bold text-on-surface mb-2">System Configurations</h1>
    <p class="text-on-surface-variant mb-8">Manage the global platform levers for UtterLazy. Changes take effect immediately.</p>

    <div v-if="loading" class="py-12 text-center text-on-surface-variant">
      <span class="material-symbols-outlined text-4xl animate-spin mb-4 block">sync</span>
      <p>Loading Configurations...</p>
    </div>

    <form v-else @submit.prevent="saveConfigs" class="max-w-3xl space-y-6">
      <!-- Feature Toggles -->
      <section class="bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/30 shadow-sm space-y-6">
        <h2 class="text-xl font-bold border-b border-outline-variant/30 pb-3">Feature Toggles</h2>
        
        <div class="flex items-center justify-between">
          <div>
            <h3 class="font-bold text-on-surface">Culinary Reels</h3>
            <p class="text-sm text-on-surface-variant">Enable TikTok-style discovery feeds on the customer app.</p>
          </div>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" v-model="localConfigs.reels_enabled" class="sr-only peer">
            <div class="w-14 h-7 bg-surface-container-highest peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-primary"></div>
          </label>
        </div>
      </section>

      <!-- Financials -->
      <section class="bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/30 shadow-sm space-y-6">
        <h2 class="text-xl font-bold border-b border-outline-variant/30 pb-3">Economics & Taxes</h2>
        
        <div>
          <label class="block text-sm font-bold text-on-surface mb-2">Platform Tax Rate (%)</label>
          <input type="number" step="0.1" v-model.number="localConfigs.platform_tax_rate" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2 focus:ring-2 focus:ring-primary focus:outline-none">
        </div>
        
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-bold text-on-surface mb-2">Standard Commission (%)</label>
            <input type="number" step="0.1" v-model.number="localConfigs.standard_commission_rate" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2 focus:ring-2 focus:ring-primary focus:outline-none">
          </div>
          <div>
            <label class="block text-sm font-bold text-on-surface mb-2">MRP Commission (%)</label>
            <input type="number" step="0.1" v-model.number="localConfigs.mrp_commission_rate" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2 focus:ring-2 focus:ring-primary focus:outline-none">
          </div>
        </div>
      </section>

      <!-- UtterLazy Coins -->
      <section class="bg-surface-container-lowest p-6 rounded-xl border border-outline-variant/30 shadow-sm space-y-6">
        <h2 class="text-xl font-bold border-b border-outline-variant/30 pb-3">UtterLazy Coins Logic</h2>
        
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-bold text-on-surface mb-2">Reward Rate (Coins per ₹100)</label>
            <input type="number" v-model.number="localConfigs.coin_reward_rate" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2 focus:ring-2 focus:ring-primary focus:outline-none">
          </div>
          <div>
            <label class="block text-sm font-bold text-on-surface mb-2">Redemption Value (₹ per Coin)</label>
            <input type="number" step="0.1" v-model.number="localConfigs.coin_redemption_rate" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2 focus:ring-2 focus:ring-primary focus:outline-none">
          </div>
        </div>
      </section>

      <!-- ═══════ Rider Payout Rates ═══════ -->
      <section class="bg-surface-container-lowest p-6 rounded-xl border border-cyan-500/20 shadow-sm space-y-6">
        <h2 class="text-xl font-bold border-b border-cyan-500/20 pb-3 flex items-center gap-2">
          <span class="material-symbols-outlined text-cyan-600">two_wheeler</span>
          Rider Payout Rates
        </h2>
        <p class="text-xs text-on-surface-variant -mt-3">Control how much riders earn per delivery. Changes apply to future deliveries.</p>
        
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-bold text-on-surface mb-2">Base Pay (₹)</label>
            <input type="number" step="1" v-model.number="localConfigs.rider_base_pay" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 focus:outline-none">
            <p class="text-[10px] text-on-surface-variant mt-1">Includes first 2km</p>
          </div>
          <div>
            <label class="block text-sm font-bold text-on-surface mb-2">Per KM Rate (₹)</label>
            <input type="number" step="0.5" v-model.number="localConfigs.rider_per_km_rate" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 focus:outline-none">
            <p class="text-[10px] text-on-surface-variant mt-1">Beyond 2km, capped at 7km</p>
          </div>
          <div>
            <label class="block text-sm font-bold text-on-surface mb-2">Max Payout Cap (₹)</label>
            <input type="number" step="5" v-model.number="localConfigs.rider_max_payout_cap" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 focus:outline-none">
            <p class="text-[10px] text-on-surface-variant mt-1">Excl. tips</p>
          </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-bold text-on-surface mb-2">Wait Pay/Min (₹)</label>
            <input type="number" step="0.5" v-model.number="localConfigs.rider_wait_pay_per_min" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 focus:outline-none">
            <p class="text-[10px] text-on-surface-variant mt-1">After 15 min at restaurant</p>
          </div>
          <div>
            <label class="block text-sm font-bold text-on-surface mb-2">Wait Pay Cap (₹)</label>
            <input type="number" step="5" v-model.number="localConfigs.rider_wait_pay_cap" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 focus:outline-none">
          </div>
          <div>
            <label class="block text-sm font-bold text-on-surface mb-2">Late Penalty (₹)</label>
            <input type="number" step="1" v-model.number="localConfigs.rider_penalty_late" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 focus:outline-none">
            <p class="text-[10px] text-on-surface-variant mt-1">If delivery >45 min</p>
          </div>
        </div>

        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-bold text-on-surface mb-2">Incentive Target</label>
            <input type="number" step="1" v-model.number="localConfigs.rider_incentive_target" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 focus:outline-none">
            <p class="text-[10px] text-on-surface-variant mt-1">Deliveries/day for bonus</p>
          </div>
          <div>
            <label class="block text-sm font-bold text-on-surface mb-2">Incentive Bonus (₹)</label>
            <input type="number" step="5" v-model.number="localConfigs.rider_incentive_bonus" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 focus:outline-none">
          </div>
          <div>
            <label class="block text-sm font-bold text-on-surface mb-2">Assignment Timeout (s)</label>
            <input type="number" step="10" v-model.number="localConfigs.rider_assignment_timeout" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2 focus:ring-2 focus:ring-cyan-500 focus:outline-none">
          </div>
        </div>

        <!-- Live preview -->
        <div class="bg-cyan-50 dark:bg-cyan-900/20 rounded-lg p-4 border border-cyan-200 dark:border-cyan-800/30">
          <h4 class="font-bold text-sm text-cyan-800 dark:text-cyan-300 mb-2">📊 Estimated Payout Preview (4km delivery)</h4>
          <div class="grid grid-cols-4 gap-3 text-xs">
            <div>
              <p class="text-cyan-600 dark:text-cyan-400">Base</p>
              <p class="font-bold text-on-surface">₹{{ localConfigs.rider_base_pay }}</p>
            </div>
            <div>
              <p class="text-cyan-600 dark:text-cyan-400">Distance (2km×₹{{ localConfigs.rider_per_km_rate }})</p>
              <p class="font-bold text-on-surface">₹{{ (Math.max(0, Math.min(4, 7) - 2) * localConfigs.rider_per_km_rate).toFixed(2) }}</p>
            </div>
            <div>
              <p class="text-cyan-600 dark:text-cyan-400">Estimated Total</p>
              <p class="font-black text-lg text-cyan-700 dark:text-cyan-300">₹{{ Math.min(localConfigs.rider_base_pay + Math.max(0, Math.min(4, 7) - 2) * localConfigs.rider_per_km_rate, localConfigs.rider_max_payout_cap).toFixed(2) }}</p>
            </div>
            <div>
              <p class="text-cyan-600 dark:text-cyan-400">Monthly (25/day)</p>
              <p class="font-bold text-on-surface">₹{{ (Math.min(localConfigs.rider_base_pay + Math.max(0, Math.min(4, 7) - 2) * localConfigs.rider_per_km_rate, localConfigs.rider_max_payout_cap) * 25 * 26).toLocaleString('en-IN') }}</p>
            </div>
          </div>
        </div>
      </section>

      <!-- ═══════ Delivery Fee Split ═══════ -->
      <section class="bg-surface-container-lowest p-6 rounded-xl border border-emerald-500/20 shadow-sm space-y-6">
        <h2 class="text-xl font-bold border-b border-emerald-500/20 pb-3 flex items-center gap-2">
          <span class="material-symbols-outlined text-emerald-600">receipt_long</span>
          Delivery Fee Split
        </h2>
        <p class="text-xs text-on-surface-variant -mt-3">Configure who bears the delivery cost — Customer, Restaurant, or UtterLazy.</p>

        <!-- Split percentages -->
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-bold text-on-surface mb-2">Customer Bears (%)</label>
            <input type="number" step="5" v-model.number="localConfigs.delivery_fee_customer_pct" @input="validateSplit('customer')" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
          </div>
          <div>
            <label class="block text-sm font-bold text-on-surface mb-2">Restaurant Bears (%)</label>
            <input type="number" step="5" v-model.number="localConfigs.delivery_fee_restaurant_pct" @input="validateSplit('restaurant')" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
          </div>
          <div>
            <label class="block text-sm font-bold text-on-surface mb-2">UtterLazy Bears (%)</label>
            <input type="number" step="5" v-model.number="localConfigs.delivery_fee_platform_pct" @input="validateSplit('platform')" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
          </div>
        </div>

        <p v-if="splitTotal !== 100" class="text-red-500 text-xs font-bold">
          ⚠️ Split must add up to 100% (currently {{ splitTotal }}%)
        </p>

        <!-- Fee limits -->
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-bold text-on-surface mb-2">Min Delivery Fee (₹)</label>
            <input type="number" step="5" v-model.number="localConfigs.delivery_fee_min" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            <p class="text-[10px] text-on-surface-variant mt-1">Floor for customer fee</p>
          </div>
          <div>
            <label class="block text-sm font-bold text-on-surface mb-2">Max Delivery Fee (₹)</label>
            <input type="number" step="5" v-model.number="localConfigs.delivery_fee_max" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            <p class="text-[10px] text-on-surface-variant mt-1">Ceiling for delivery fee</p>
          </div>
          <div>
            <label class="block text-sm font-bold text-on-surface mb-2">Free Delivery Above (₹)</label>
            <input type="number" step="50" v-model.number="localConfigs.delivery_fee_free_above" class="w-full bg-surface-container border border-outline-variant/50 rounded-lg px-4 py-2 focus:ring-2 focus:ring-emerald-500 focus:outline-none">
            <p class="text-[10px] text-on-surface-variant mt-1">Order value threshold</p>
          </div>
        </div>

        <!-- Live split preview -->
        <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-lg p-4 border border-emerald-200 dark:border-emerald-800/30">
          <h4 class="font-bold text-sm text-emerald-800 dark:text-emerald-300 mb-3">📊 Delivery Fee Split Preview (₹{{ previewDeliveryFee }} per order)</h4>
          <div class="grid grid-cols-3 gap-4">
            <div class="text-center bg-white dark:bg-surface-container rounded-lg p-3">
              <p class="text-[10px] uppercase tracking-wider text-on-surface-variant mb-1">Customer Pays</p>
              <p class="text-2xl font-black text-blue-600">₹{{ previewCustomerShare }}</p>
              <p class="text-[10px] text-on-surface-variant">{{ localConfigs.delivery_fee_customer_pct }}% of ₹{{ previewDeliveryFee }}</p>
            </div>
            <div class="text-center bg-white dark:bg-surface-container rounded-lg p-3">
              <p class="text-[10px] uppercase tracking-wider text-on-surface-variant mb-1">Restaurant Bears</p>
              <p class="text-2xl font-black text-amber-600">₹{{ previewRestaurantShare }}</p>
              <p class="text-[10px] text-on-surface-variant">{{ localConfigs.delivery_fee_restaurant_pct }}% of ₹{{ previewDeliveryFee }}</p>
            </div>
            <div class="text-center bg-white dark:bg-surface-container rounded-lg p-3">
              <p class="text-[10px] uppercase tracking-wider text-on-surface-variant mb-1">UtterLazy Bears</p>
              <p class="text-2xl font-black text-purple-600">₹{{ previewPlatformShare }}</p>
              <p class="text-[10px] text-on-surface-variant">{{ localConfigs.delivery_fee_platform_pct }}% of ₹{{ previewDeliveryFee }}</p>
            </div>
          </div>
          <!-- Visual bar -->
          <div class="mt-3 flex rounded-full overflow-hidden h-3">
            <div class="bg-blue-500 transition-all" :style="{width: localConfigs.delivery_fee_customer_pct + '%'}"></div>
            <div class="bg-amber-500 transition-all" :style="{width: localConfigs.delivery_fee_restaurant_pct + '%'}"></div>
            <div class="bg-purple-500 transition-all" :style="{width: localConfigs.delivery_fee_platform_pct + '%'}"></div>
          </div>
          <div class="flex justify-between text-[9px] mt-1">
            <span class="text-blue-600">Customer {{ localConfigs.delivery_fee_customer_pct }}%</span>
            <span class="text-amber-600">Restaurant {{ localConfigs.delivery_fee_restaurant_pct }}%</span>
            <span class="text-purple-600">UtterLazy {{ localConfigs.delivery_fee_platform_pct }}%</span>
          </div>
        </div>
      </section>

      <div class="flex justify-end pt-4">
        <button type="submit" :disabled="saving || splitTotal !== 100" class="bg-primary text-white font-bold px-8 py-3 rounded-lg hover:bg-primary/90 transition-colors flex items-center gap-2 disabled:opacity-50">
          <span v-if="saving" class="material-symbols-outlined animate-spin">progress_activity</span>
          Save All Configurations
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { getConfigs, updateConfigs } from '../api'

const loading = ref(true)
const saving = ref(false)

const localConfigs = ref({
  reels_enabled: false,
  platform_tax_rate: 5.0,
  standard_commission_rate: 15.0,
  mrp_commission_rate: 2.0,
  coin_reward_rate: 10,
  coin_redemption_rate: 1.0,
  // Rider payout rates
  rider_base_pay: 15,
  rider_per_km_rate: 4,
  rider_max_payout_cap: 60,
  rider_wait_pay_per_min: 2,
  rider_wait_pay_cap: 20,
  rider_penalty_late: 5,
  rider_incentive_target: 15,
  rider_incentive_bonus: 25,
  rider_assignment_timeout: 60,
  // Delivery fee split
  delivery_fee_customer_pct: 60,
  delivery_fee_restaurant_pct: 25,
  delivery_fee_platform_pct: 15,
  delivery_fee_min: 10,
  delivery_fee_max: 50,
  delivery_fee_free_above: 500,
})

const splitTotal = computed(() =>
  (localConfigs.value.delivery_fee_customer_pct || 0) +
  (localConfigs.value.delivery_fee_restaurant_pct || 0) +
  (localConfigs.value.delivery_fee_platform_pct || 0)
)

const previewDeliveryFee = computed(() => {
  const base = localConfigs.value.rider_base_pay + (3 * localConfigs.value.rider_per_km_rate)
  return Math.max(localConfigs.value.delivery_fee_min, Math.min(base, localConfigs.value.delivery_fee_max)).toFixed(0)
})

const previewCustomerShare = computed(() =>
  (previewDeliveryFee.value * localConfigs.value.delivery_fee_customer_pct / 100).toFixed(2)
)
const previewRestaurantShare = computed(() =>
  (previewDeliveryFee.value * localConfigs.value.delivery_fee_restaurant_pct / 100).toFixed(2)
)
const previewPlatformShare = computed(() =>
  (previewDeliveryFee.value - parseFloat(previewCustomerShare.value) - parseFloat(previewRestaurantShare.value)).toFixed(2)
)

const validateSplit = (changed) => {
  // Auto-adjust platform % to make sum = 100
  const sum = localConfigs.value.delivery_fee_customer_pct + localConfigs.value.delivery_fee_restaurant_pct + localConfigs.value.delivery_fee_platform_pct
  if (changed !== 'platform') {
    localConfigs.value.delivery_fee_platform_pct = Math.max(0, 100 - localConfigs.value.delivery_fee_customer_pct - localConfigs.value.delivery_fee_restaurant_pct)
  }
}

onMounted(async () => {
  try {
    const data = await getConfigs()
    const cfg = localConfigs.value

    // Map all configs from API response
    const mappings = {
      reels_enabled: (v) => v === 'true',
      platform_tax_rate: parseFloat,
      standard_commission_rate: parseFloat,
      mrp_commission_rate: parseFloat,
      coin_reward_rate: parseInt,
      coin_redemption_rate: parseFloat,
      rider_base_pay: parseFloat,
      rider_per_km_rate: parseFloat,
      rider_max_payout_cap: parseFloat,
      rider_wait_pay_per_min: parseFloat,
      rider_wait_pay_cap: parseFloat,
      rider_penalty_late: parseFloat,
      rider_incentive_target: parseInt,
      rider_incentive_bonus: parseFloat,
      rider_assignment_timeout: parseInt,
      delivery_fee_customer_pct: parseFloat,
      delivery_fee_restaurant_pct: parseFloat,
      delivery_fee_platform_pct: parseFloat,
      delivery_fee_min: parseFloat,
      delivery_fee_max: parseFloat,
      delivery_fee_free_above: parseFloat,
    }

    for (const [key, parser] of Object.entries(mappings)) {
      if (data[key]) cfg[key] = parser(data[key].config_value)
    }
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
})

const saveConfigs = async () => {
  saving.value = true
  const cfg = localConfigs.value
  const payload = [
    // Existing configs
    { config_key: 'reels_enabled', config_value: cfg.reels_enabled ? 'true' : 'false', type: 'boolean' },
    { config_key: 'platform_tax_rate', config_value: cfg.platform_tax_rate.toString(), type: 'float' },
    { config_key: 'standard_commission_rate', config_value: cfg.standard_commission_rate.toString(), type: 'float' },
    { config_key: 'mrp_commission_rate', config_value: cfg.mrp_commission_rate.toString(), type: 'float' },
    { config_key: 'coin_reward_rate', config_value: cfg.coin_reward_rate.toString(), type: 'integer' },
    { config_key: 'coin_redemption_rate', config_value: cfg.coin_redemption_rate.toString(), type: 'float' },
    // Rider payout rates
    { config_key: 'rider_base_pay', config_value: cfg.rider_base_pay.toString(), type: 'number' },
    { config_key: 'rider_per_km_rate', config_value: cfg.rider_per_km_rate.toString(), type: 'number' },
    { config_key: 'rider_max_payout_cap', config_value: cfg.rider_max_payout_cap.toString(), type: 'number' },
    { config_key: 'rider_wait_pay_per_min', config_value: cfg.rider_wait_pay_per_min.toString(), type: 'number' },
    { config_key: 'rider_wait_pay_cap', config_value: cfg.rider_wait_pay_cap.toString(), type: 'number' },
    { config_key: 'rider_penalty_late', config_value: cfg.rider_penalty_late.toString(), type: 'number' },
    { config_key: 'rider_incentive_target', config_value: cfg.rider_incentive_target.toString(), type: 'number' },
    { config_key: 'rider_incentive_bonus', config_value: cfg.rider_incentive_bonus.toString(), type: 'number' },
    { config_key: 'rider_assignment_timeout', config_value: cfg.rider_assignment_timeout.toString(), type: 'number' },
    // Delivery fee split
    { config_key: 'delivery_fee_customer_pct', config_value: cfg.delivery_fee_customer_pct.toString(), type: 'number' },
    { config_key: 'delivery_fee_restaurant_pct', config_value: cfg.delivery_fee_restaurant_pct.toString(), type: 'number' },
    { config_key: 'delivery_fee_platform_pct', config_value: cfg.delivery_fee_platform_pct.toString(), type: 'number' },
    { config_key: 'delivery_fee_min', config_value: cfg.delivery_fee_min.toString(), type: 'number' },
    { config_key: 'delivery_fee_max', config_value: cfg.delivery_fee_max.toString(), type: 'number' },
    { config_key: 'delivery_fee_free_above', config_value: cfg.delivery_fee_free_above.toString(), type: 'number' },
  ]
  
  try {
    await updateConfigs(payload)
    alert('All Configurations Saved Successfully!')
  } catch (e) {
    alert('Error saving configs: ' + e.message)
  } finally {
    saving.value = false
  }
}
</script>
