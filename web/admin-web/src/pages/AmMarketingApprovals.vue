<template>
  <div class="p-6">
    <div class="mb-6">
      <h1 class="font-display font-bold text-2xl text-primary uppercase tracking-wide">AM Marketing Approvals</h1>
      <p class="text-sm text-secondary mt-1">Account Management reference implementation (§6) — AM-created ads &amp; offers routed through the org hierarchy + DOA engine instead of the restaurant OTP flow. Final approval publishes the item (approved_via: org_engine).</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <section>
        <h2 class="font-bold text-primary text-sm uppercase tracking-wide mb-3">Sponsored Listings (Ads)</h2>
        <div v-if="loadingAds" class="text-secondary text-sm py-6 text-center">Loading pending ads…</div>
        <div v-else-if="ads.length === 0" class="text-secondary text-sm py-6 text-center border border-white/10 rounded-2xl">No pending sponsored listings.</div>
        <div class="space-y-3">
          <div v-for="a in ads" :key="a.id" class="rounded-2xl border border-white/10 bg-surface/50 p-4 flex items-center gap-4">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <span class="font-bold text-primary text-sm">{{ a.ad_type === 'card' ? 'Card Ad' : 'PPC Ad' }}</span>
                <span class="text-[10px] px-2 py-0.5 rounded-full bg-white/10 text-secondary">{{ a.district || 'no district' }}, {{ a.state || 'no state' }}</span>
              </div>
              <p class="text-[11px] text-secondary mt-1 truncate">{{ a.restaurant }}</p>
              <p class="text-[10px] text-secondary">daily ₹{{ Number(a.daily_budget).toLocaleString('en-IN') }} · weekly ₹{{ Number(a.weekly_budget).toLocaleString('en-IN') }}</p>
            </div>
            <div class="text-right">
              <p class="font-bold text-primary">₹{{ Number(a.route_amount).toLocaleString('en-IN') }}</p>
              <p class="text-[10px] text-secondary">{{ a.approval_status }}</p>
            </div>
            <button class="bg-primary text-black font-bold px-4 py-2 rounded-lg text-sm hover:opacity-90 disabled:opacity-40" @click="originateAd(a)" :disabled="busy === a.id">
              {{ busy === a.id ? 'Routing…' : 'Route for Approval' }}
            </button>
          </div>
        </div>
      </section>

      <section>
        <h2 class="font-bold text-primary text-sm uppercase tracking-wide mb-3">Offers</h2>
        <div v-if="loadingOffers" class="text-secondary text-sm py-6 text-center">Loading pending offers…</div>
        <div v-else-if="offers.length === 0" class="text-secondary text-sm py-6 text-center border border-white/10 rounded-2xl">No pending offers.</div>
        <div class="space-y-3">
          <div v-for="o in offers" :key="o.id" class="rounded-2xl border border-white/10 bg-surface/50 p-4 flex items-center gap-4">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <span class="font-bold text-primary text-sm">{{ o.title }}</span>
                <span class="text-[10px] px-2 py-0.5 rounded-full bg-white/10 text-secondary">{{ o.type }}</span>
              </div>
              <p class="text-[11px] text-secondary mt-1 truncate">{{ o.restaurant }}</p>
              <p class="text-[10px] text-secondary">{{ o.district || 'no district' }}, {{ o.state || 'no state' }}</p>
            </div>
            <div class="text-right">
              <p class="font-bold text-primary">₹{{ Number(o.route_amount).toLocaleString('en-IN') }}</p>
              <p class="text-[10px] text-secondary">{{ o.approval_status }}</p>
            </div>
            <button class="bg-primary text-black font-bold px-4 py-2 rounded-lg text-sm hover:opacity-90 disabled:opacity-40" @click="originateOffer(o)" :disabled="busy === o.id">
              {{ busy === o.id ? 'Routing…' : 'Route for Approval' }}
            </button>
          </div>
        </div>
      </section>
    </div>

    <div v-if="result" class="mt-6 rounded-2xl border border-emerald-500/30 bg-emerald-500/5 p-4 text-sm">
      <p class="text-emerald-400 font-bold mb-1">✓ Originated at {{ result.origin_org_unit }}</p>
      <p class="text-secondary">Status: {{ result.status_label }} · reviewer: {{ result.reviewer || 'auto-escalating…' }} · SLA due: {{ result.sla_due_at }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { getPendingSponsoredListings, initiateSponsoredListingApproval, getPendingOffers, initiateOfferApproval } from '../api'

const ads = ref([])
const offers = ref([])
const loadingAds = ref(true)
const loadingOffers = ref(true)
const busy = ref(null)
const result = ref(null)

const loadAds = async () => {
  loadingAds.value = true
  try { ads.value = await getPendingSponsoredListings() }
  catch (e) { alert(e.message || 'Failed to load ads') }
  finally { loadingAds.value = false }
}

const loadOffers = async () => {
  loadingOffers.value = true
  try { offers.value = await getPendingOffers() }
  catch (e) { alert(e.message || 'Failed to load offers') }
  finally { loadingOffers.value = false }
}

const originateAd = async (a) => {
  busy.value = a.id
  result.value = null
  try {
    result.value = await initiateSponsoredListingApproval(a.id, false)
    await loadAds()
  } catch (e) {
    alert(e.message || JSON.stringify(e.errors || e))
  } finally {
    busy.value = null
  }
}

const originateOffer = async (o) => {
  busy.value = o.id
  result.value = null
  try {
    result.value = await initiateOfferApproval(o.id, false)
    await loadOffers()
  } catch (e) {
    alert(e.message || JSON.stringify(e.errors || e))
  } finally {
    busy.value = null
  }
}

onMounted(() => { loadAds(); loadOffers() })
</script>
