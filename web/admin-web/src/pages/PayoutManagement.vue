<template>
  <div>
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-3xl font-black text-white flex items-center gap-3">
          <span class="w-10 h-10 bg-gradient-to-br from-amber-400 to-orange-500 rounded-xl flex items-center justify-center text-lg">📊</span>
          Payout Cycles
        </h1>
        <p class="text-white/50 text-sm mt-1">Manage restaurant weekly payout cycles</p>
      </div>
      <div class="flex gap-3">
        <button @click="openGenerateModal"
          class="bg-gradient-to-r from-emerald-500 to-green-600 text-white font-bold px-5 py-2.5 rounded-xl flex items-center gap-2 hover:opacity-90 transition-opacity shadow-lg shadow-emerald-500/20">
          <span class="material-symbols-outlined text-[18px]">add_circle</span>
          Generate Cycle
        </button>
        <button @click="handleDownloadCsv" :disabled="dlBusy"
          class="bg-gradient-to-r from-amber-500 to-orange-600 text-white font-bold px-5 py-2.5 rounded-xl flex items-center gap-2 hover:opacity-90 transition-opacity shadow-lg shadow-amber-500/20 disabled:opacity-50">
          <span class="material-symbols-outlined text-[18px]">download</span>
          {{ dlBusy ? 'Downloading...' : 'Download CSV' }}
        </button>
      </div>
    </div>

    <div class="flex gap-4 mb-6">
      <input v-model="filters.q" @input="debouncedSearch" placeholder="Search restaurant..."
        class="bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm w-64 focus:outline-none focus:border-secondary/50" />
      <select v-model="filters.status" @change="fetchCycles"
        class="bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:border-secondary/50">
        <option value="">All Status</option>
        <option value="generated">Generated</option>
        <option value="paid">Paid</option>
      </select>
      <input type="date" v-model="filters.from_date" @change="fetchCycles"
        class="bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:border-secondary/50" />
      <input type="date" v-model="filters.to_date" @change="fetchCycles"
        class="bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:border-secondary/50" />
    </div>

    <div v-if="loading" class="text-center py-12 text-on-surface-variant">Loading...</div>
    <div v-else-if="error" class="text-center py-12 text-red-500">{{ error }}</div>

    <div v-else class="bg-white/5 border border-white/10 rounded-2xl overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-white/10 text-white/40 text-xs uppercase tracking-wider">
              <th class="text-left p-4">Restaurant</th>
              <th class="text-left p-4">Cycle</th>
              <th class="text-right p-4">Gross</th>
              <th class="text-right p-4">Deductions</th>
              <th class="text-right p-4">Additions</th>
              <th class="text-right p-4">Net Payable</th>
              <th class="text-right p-4">Balance Forward</th>
              <th class="text-center p-4">Status</th>
              <th class="text-center p-4">UTR</th>
              <th class="text-center p-4">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in cycles" :key="c.id"
              class="border-b border-white/5 hover:bg-white/5 transition-colors cursor-pointer"
              @click="openCycleDetail(c)">
              <td class="p-4 font-bold text-white">{{ c.restaurant?.name || c.restaurant_id?.slice(0,8) }}</td>
              <td class="p-4 text-white/70 text-xs">{{ fmtDate(c.cycle_start) }} → {{ fmtDate(c.cycle_end) }}</td>
              <td class="p-4 text-right text-white">₹{{ +c.gross_order_value | num }}</td>
              <td class="p-4 text-right text-red-400">-₹{{ +c.total_deductions | num }}</td>
              <td class="p-4 text-right text-green-400">+₹{{ +c.total_additions | num }}</td>
              <td class="p-4 text-right font-black text-lg text-cyan-400">₹{{ +c.net_payable | num }}</td>
              <td class="p-4 text-right text-white/60 text-xs">{{ +c.previous_balance_carried | num }}</td>
              <td class="p-4 text-center">
                <span :class="c.status === 'paid' ? 'bg-green-500/20 text-green-400' : 'bg-amber-500/20 text-amber-400'"
                  class="px-2 py-1 rounded-full text-[10px] font-bold uppercase">
                  {{ c.status }}
                </span>
              </td>
              <td class="p-4 text-center font-mono text-xs text-white/50">{{ c.utr_number || '—' }}</td>
              <td class="p-4 text-center">
                <div class="flex gap-1 justify-center" @click.stop>
                  <button v-if="c.status === 'generated'" @click="openMarkPaidModal(c)"
                    class="px-2 py-1 rounded-lg bg-blue-500/20 text-blue-400 text-xs font-bold hover:bg-blue-500/30">Pay</button>
                  <button @click="openCycleDetail(c)"
                    class="px-2 py-1 rounded-lg bg-white/10 text-white/60 text-xs font-bold hover:bg-white/20">View</button>
                </div>
              </td>
            </tr>
            <tr v-if="cycles.length === 0">
              <td colspan="10" class="p-12 text-center text-white/30">No payout cycles found.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="lastPage > 1" class="flex justify-center gap-2 mt-4">
      <button v-for="p in lastPage" :key="p" @click="page = p; fetchCycles()"
        :class="p === page ? 'bg-white text-black' : 'bg-white/10 text-white/60 hover:bg-white/20'"
        class="w-8 h-8 rounded-lg text-xs font-bold transition-colors">{{ p }}</button>
    </div>

    <!-- Generate Cycle Modal -->
    <div v-if="showGenerateModal" class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center" @click.self="showGenerateModal = false">
      <div class="bg-surface border border-white/10 rounded-2xl p-6 w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
          <h2 class="text-xl font-bold text-white">Generate Payout Cycle</h2>
          <button @click="showGenerateModal = false" class="text-white/40 hover:text-white"><span class="material-symbols-outlined">close</span></button>
        </div>
        <div class="space-y-4">
          <div>
            <label class="text-white/60 text-xs uppercase tracking-wider font-bold mb-1 block">Search Restaurant</label>
            <input v-model="genSearch" @input="debouncedRestaurantSearch" placeholder="Type restaurant name..."
              class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none focus:border-secondary/50" />
            <div v-if="restaurantResults.length > 0" class="mt-2 border border-white/10 rounded-xl overflow-hidden">
              <div v-for="r in restaurantResults" :key="r.id" @click="selectRestaurant(r)"
                :class="selectedRestaurant?.id === r.id ? 'bg-secondary/20' : 'hover:bg-white/5'"
                class="px-4 py-3 cursor-pointer">
                <p class="text-white font-bold text-sm">{{ r.name }}</p>
                <p class="text-white/40 text-xs">{{ r.email }} — {{ r.address }}</p>
              </div>
            </div>
            <p v-if="selectedRestaurant" class="mt-2 text-green-400 text-sm">✓ {{ selectedRestaurant.name }}</p>
          </div>
          <div>
            <label class="text-white/60 text-xs uppercase tracking-wider font-bold mb-1 block">Cycle Start</label>
            <input type="date" v-model="genCycleStart"
              class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none" />
          </div>
          <div>
            <label class="text-white/60 text-xs uppercase tracking-wider font-bold mb-1 block">Cycle End</label>
            <input type="date" v-model="genCycleEnd"
              class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none" />
          </div>
          <p v-if="genError" class="text-red-400 text-sm">{{ genError }}</p>
          <button @click="handleGenerate" :disabled="genBusy || !selectedRestaurant || !genCycleStart || !genCycleEnd"
            class="w-full bg-gradient-to-r from-emerald-500 to-green-600 text-white font-bold py-3 rounded-xl hover:opacity-90 disabled:opacity-50">
            {{ genBusy ? 'Generating...' : 'Generate Cycle' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Cycle Detail Modal -->
    <div v-if="showDetailModal" class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center" @click.self="showDetailModal = false">
      <div class="bg-surface border border-white/10 rounded-2xl p-6 w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
          <div>
            <h2 class="text-xl font-bold text-white">{{ detailCycle?.restaurant?.name || 'Payout Cycle' }}</h2>
            <p class="text-white/40 text-sm">{{ fmtDate(detailCycle?.cycle_start) }} → {{ fmtDate(detailCycle?.cycle_end) }}</p>
          </div>
          <div class="flex gap-2 items-center">
            <button v-if="detailCycle?.status === 'generated'" @click="openMarkPaidModal(detailCycle)"
              class="bg-blue-500/20 text-blue-400 px-3 py-1.5 rounded-xl text-sm font-bold hover:bg-blue-500/30">Mark Paid</button>
            <button @click="showDetailModal = false" class="text-white/40 hover:text-white"><span class="material-symbols-outlined">close</span></button>
          </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-4 gap-4 mb-6">
          <div class="bg-white/5 rounded-xl p-4">
            <p class="text-white/40 text-xs uppercase tracking-wider">Gross</p>
            <p class="text-white text-2xl font-black">₹{{ +detailCycle?.gross_order_value | num }}</p>
          </div>
          <div class="bg-white/5 rounded-xl p-4">
            <p class="text-white/40 text-xs uppercase tracking-wider">Deductions</p>
            <p class="text-red-400 text-2xl font-black">-₹{{ +detailCycle?.total_deductions | num }}</p>
          </div>
          <div class="bg-white/5 rounded-xl p-4">
            <p class="text-white/40 text-xs uppercase tracking-wider">Additions</p>
            <p class="text-green-400 text-2xl font-black">+₹{{ +detailCycle?.total_additions | num }}</p>
          </div>
          <div class="bg-white/5 rounded-xl p-4">
            <p class="text-white/40 text-xs uppercase tracking-wider">Net Payable</p>
            <p class="text-cyan-400 text-2xl font-black">₹{{ +detailCycle?.net_payable | num }}</p>
          </div>
        </div>

        <!-- Order Lines -->
        <h3 class="text-white font-bold mb-3">Order Lines ({{ detailCycle?.order_lines?.length || 0 }})</h3>
        <div class="bg-white/5 rounded-xl overflow-hidden mb-6">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-white/10 text-white/40 text-xs uppercase tracking-wider">
                <th class="text-left p-3">Order ID</th>
                <th class="text-right p-3">Value</th>
                <th class="text-right p-3">Discount</th>
                <th class="text-right p-3">Tax</th>
                <th class="text-right p-3">Commission</th>
                <th class="text-right p-3">Conv Fee</th>
                <th class="text-right p-3">Delivery</th>
                <th class="text-right p-3">Net</th>
                <th class="text-center p-3">Status</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="ol in detailCycle?.order_lines || []" :key="ol.id" class="border-b border-white/5 hover:bg-white/5">
                <td class="p-3 font-mono text-xs text-white/60">#{{ ol.order_id?.slice(0,8) }}</td>
                <td class="p-3 text-right text-white">₹{{ +ol.order_value | num }}</td>
                <td class="p-3 text-right text-red-400">-₹{{ +ol.discount | num }}</td>
                <td class="p-3 text-right text-orange-400">₹{{ +ol.tax | num }}</td>
                <td class="p-3 text-right text-amber-400">₹{{ +ol.commission | num }}</td>
                <td class="p-3 text-right text-white/70">₹{{ +ol.convenience_fee | num }}</td>
                <td class="p-3 text-right text-white/70">₹{{ +ol.delivery_charge_collected | num }}</td>
                <td class="p-3 text-right font-bold text-green-400">₹{{ +ol.net_settlement | num }}</td>
                <td class="p-3 text-center"><span class="text-[10px] px-2 py-0.5 rounded bg-white/10 text-white/60">{{ ol.order_status }}</span></td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Deductions -->
        <div class="flex gap-4 mb-6">
          <div class="flex-1">
            <div class="flex justify-between items-center mb-2">
              <h3 class="text-white font-bold">Deductions</h3>
              <button v-if="detailCycle?.status === 'generated'" @click="openAddDeductionModal(detailCycle)"
                class="text-red-400 text-xs font-bold flex items-center gap-1 hover:underline">
                <span class="material-symbols-outlined text-[14px]">add</span> Add
              </button>
            </div>
            <div class="bg-white/5 rounded-xl p-3 space-y-2">
              <div v-for="d in detailCycle?.deductions || []" :key="d.id"
                class="flex items-center justify-between py-1 border-b border-white/5 last:border-0">
                <div>
                  <p class="text-white text-sm font-bold capitalize">{{ d.type }}</p>
                  <p class="text-white/40 text-xs">{{ d.notes }}</p>
                </div>
                <p class="text-red-400 font-bold">-₹{{ +d.amount | num }}</p>
              </div>
              <p v-if="!detailCycle?.deductions?.length" class="text-white/30 text-sm text-center py-2">No deductions</p>
            </div>
          </div>
          <div class="flex-1">
            <div class="flex justify-between items-center mb-2">
              <h3 class="text-white font-bold">Additions</h3>
              <button v-if="detailCycle?.status === 'generated'" @click="openAddAdditionModal(detailCycle)"
                class="text-green-400 text-xs font-bold flex items-center gap-1 hover:underline">
                <span class="material-symbols-outlined text-[14px]">add</span> Add
              </button>
            </div>
            <div class="bg-white/5 rounded-xl p-3 space-y-2">
              <div v-for="a in detailCycle?.additions || []" :key="a.id"
                class="flex items-center justify-between py-1 border-b border-white/5 last:border-0">
                <div>
                  <p class="text-white text-sm font-bold capitalize">{{ a.type }}</p>
                  <p class="text-white/40 text-xs">{{ a.notes }}</p>
                </div>
                <p class="text-green-400 font-bold">+₹{{ +a.amount | num }}</p>
              </div>
              <p v-if="!detailCycle?.additions?.length" class="text-white/30 text-sm text-center py-2">No additions</p>
            </div>
          </div>
        </div>

        <!-- Payment Info -->
        <div v-if="detailCycle?.status === 'paid'" class="bg-green-500/10 border border-green-500/20 rounded-xl p-4">
          <h3 class="text-green-400 font-bold mb-2">Payment Details</h3>
          <div class="grid grid-cols-2 gap-2 text-sm">
            <p class="text-white/70">UTR: <span class="text-white font-bold">{{ detailCycle.utr_number }}</span></p>
            <p class="text-white/70">Mode: <span class="text-white font-bold capitalize">{{ detailCycle.payment_mode }}</span></p>
            <p class="text-white/70">Amount: <span class="text-white font-bold">₹{{ +detailCycle.amount_paid | num }}</span></p>
            <p class="text-white/70">Date: <span class="text-white font-bold">{{ fmtDate(detailCycle.payment_date) }}</span></p>
          </div>
        </div>
      </div>
    </div>

    <!-- Mark Paid Modal -->
    <div v-if="showPaidModal" class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center" @click.self="showPaidModal = false">
      <div class="bg-surface border border-white/10 rounded-2xl p-6 w-full max-w-md mx-4">
        <h2 class="text-xl font-bold text-white mb-6">Mark Payout Paid</h2>
        <div class="space-y-4">
          <div>
            <label class="text-white/60 text-xs uppercase tracking-wider font-bold mb-1 block">UTR Number *</label>
            <input v-model="paidForm.utr_number" placeholder="e.g., SBIN123456789"
              class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none" />
          </div>
          <div>
            <label class="text-white/60 text-xs uppercase tracking-wider font-bold mb-1 block">Payment Mode *</label>
            <select v-model="paidForm.payment_mode"
              class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none">
              <option value="NEFT">NEFT</option>
              <option value="RTGS">RTGS</option>
              <option value="IMPS">IMPS</option>
              <option value="UPI">UPI</option>
            </select>
          </div>
          <div>
            <label class="text-white/60 text-xs uppercase tracking-wider font-bold mb-1 block">Amount Paid</label>
            <input type="number" v-model="paidForm.amount_paid" placeholder="0.00"
              class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none" />
          </div>
          <div>
            <label class="text-white/60 text-xs uppercase tracking-wider font-bold mb-1 block">Payment Date</label>
            <input type="date" v-model="paidForm.payment_date"
              class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none" />
          </div>
          <div>
            <label class="text-white/60 text-xs uppercase tracking-wider font-bold mb-1 block">Notes</label>
            <input v-model="paidForm.payment_notes" placeholder="Optional notes"
              class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none" />
          </div>
          <p v-if="paidError" class="text-red-400 text-sm">{{ paidError }}</p>
          <button @click="handleMarkPaid" :disabled="paidBusy || !paidForm.utr_number || !paidForm.payment_mode"
            class="w-full bg-gradient-to-r from-blue-500 to-blue-600 text-white font-bold py-3 rounded-xl hover:opacity-90 disabled:opacity-50">
            {{ paidBusy ? 'Processing...' : 'Mark as Paid' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Add Deduction Modal -->
    <div v-if="showDeductionModal" class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center" @click.self="showDeductionModal = false">
      <div class="bg-surface border border-white/10 rounded-2xl p-6 w-full max-w-md mx-4">
        <h2 class="text-xl font-bold text-white mb-6">Add Deduction</h2>
        <div class="space-y-4">
          <div>
            <label class="text-white/60 text-xs uppercase tracking-wider font-bold mb-1 block">Type *</label>
            <select v-model="deductionForm.type"
              class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none">
              <option value="penalty">Penalty</option>
              <option value="fine">Fine</option>
              <option value="adjustment">Adjustment</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div>
            <label class="text-white/60 text-xs uppercase tracking-wider font-bold mb-1 block">Amount (₹) *</label>
            <input type="number" v-model="deductionForm.amount" placeholder="0.00"
              class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none" />
          </div>
          <div>
            <label class="text-white/60 text-xs uppercase tracking-wider font-bold mb-1 block">Reason / Notes</label>
            <input v-model="deductionForm.notes" placeholder="Reason for deduction"
              class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none" />
          </div>
          <p v-if="dedError" class="text-red-400 text-sm">{{ dedError }}</p>
          <button @click="handleAddDeduction" :disabled="dedBusy || !deductionForm.type || !deductionForm.amount"
            class="w-full bg-gradient-to-r from-red-500 to-red-600 text-white font-bold py-3 rounded-xl hover:opacity-90 disabled:opacity-50">
            {{ dedBusy ? 'Adding...' : 'Add Deduction' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Add Addition Modal -->
    <div v-if="showAdditionModal" class="fixed inset-0 z-50 bg-black/70 flex items-center justify-center" @click.self="showAdditionModal = false">
      <div class="bg-surface border border-white/10 rounded-2xl p-6 w-full max-w-md mx-4">
        <h2 class="text-xl font-bold text-white mb-6">Add Addition</h2>
        <div class="space-y-4">
          <div>
            <label class="text-white/60 text-xs uppercase tracking-wider font-bold mb-1 block">Type *</label>
            <select v-model="additionForm.type"
              class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none">
              <option value="bonus">Bonus</option>
              <option value="incentive">Incentive</option>
              <option value="correction">Correction</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div>
            <label class="text-white/60 text-xs uppercase tracking-wider font-bold mb-1 block">Amount (₹) *</label>
            <input type="number" v-model="additionForm.amount" placeholder="0.00"
              class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none" />
          </div>
          <div>
            <label class="text-white/60 text-xs uppercase tracking-wider font-bold mb-1 block">Reason / Notes</label>
            <input v-model="additionForm.notes" placeholder="Reason for addition"
              class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-white text-sm focus:outline-none" />
          </div>
          <p v-if="addError" class="text-red-400 text-sm">{{ addError }}</p>
          <button @click="handleAddAddition" :disabled="addBusy || !additionForm.type || !additionForm.amount"
            class="w-full bg-gradient-to-r from-green-500 to-emerald-600 text-white font-bold py-3 rounded-xl hover:opacity-90 disabled:opacity-50">
            {{ addBusy ? 'Adding...' : 'Add Addition' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import {
  searchRestaurantsForPayout, getPayoutCycles, getPayoutCycle,
  generatePayoutCycle, markPayoutPaid,
  addPayoutDeduction, addPayoutAddition, downloadPayoutsExcel
} from '../api'

const cycles = ref([])
const page = ref(1)
const lastPage = ref(1)
const loading = ref(true)
const error = ref(null)
const dlBusy = ref(false)

const filters = ref({ q: '', status: '', from_date: '', to_date: '' })

// Generate modal
const showGenerateModal = ref(false)
const genSearch = ref('')
const restaurantResults = ref([])
const selectedRestaurant = ref(null)
const genCycleStart = ref('')
const genCycleEnd = ref('')
const genBusy = ref(false)
const genError = ref('')

// Detail modal
const showDetailModal = ref(false)
const detailCycle = ref(null)

// Mark paid modal
const showPaidModal = ref(false)
const paidCycle = ref(null)
const paidForm = ref({ utr_number: '', payment_mode: 'neft', amount_paid: '', payment_date: '', payment_notes: '' })
const paidBusy = ref(false)
const paidError = ref('')

// Deduction modal
const showDeductionModal = ref(false)
const dedCycle = ref(null)
const deductionForm = ref({ type: 'penalty', amount: '', notes: '' })
const dedBusy = ref(false)
const dedError = ref('')

// Addition modal
const showAdditionModal = ref(false)
const addCycle = ref(null)
const additionForm = ref({ type: 'bonus', amount: '', notes: '' })
const addBusy = ref(false)
const addError = ref('')

let searchTimer = null
const debouncedSearch = () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(fetchCycles, 400)
}

const fetchCycles = async () => {
  loading.value = true
  error.value = null
  try {
    const params = { ...filters.value, page: page.value }
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })
    const data = await getPayoutCycles(params)
    cycles.value = data.data || []
    lastPage.value = data.last_page || 1
  } catch (e) {
    error.value = e.message || 'Failed to load'
    cycles.value = []
  } finally {
    loading.value = false
  }
}

const fmtDate = (d) => {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' })
}

// Restaurant search
let restSearchTimer = null
const debouncedRestaurantSearch = () => {
  clearTimeout(restSearchTimer)
  restSearchTimer = setTimeout(async () => {
    if (genSearch.value.length < 2) { restaurantResults.value = []; return }
    try {
      const data = await searchRestaurantsForPayout(genSearch.value)
      restaurantResults.value = data.data || []
    } catch { restaurantResults.value = [] }
  }, 300)
}

const selectRestaurant = (r) => {
  selectedRestaurant.value = r
  restaurantResults.value = []
  genSearch.value = r.name
}

// Generate
const openGenerateModal = () => {
  selectedRestaurant.value = null
  genSearch.value = ''
  genCycleStart.value = ''
  genCycleEnd.value = ''
  genError.value = ''
  showGenerateModal.value = true
}

const handleGenerate = async () => {
  genBusy.value = true
  genError.value = ''
  try {
    const result = await generatePayoutCycle(selectedRestaurant.value.id, genCycleStart.value, genCycleEnd.value)
    showGenerateModal.value = false
    await fetchCycles()
  } catch (e) {
    genError.value = e?.message || e?.error || 'Failed to generate'
  } finally {
    genBusy.value = false
  }
}

// Detail
const openCycleDetail = async (c) => {
  try {
    const data = await getPayoutCycle(c.id)
    detailCycle.value = data.cycle || data
  } catch {
    detailCycle.value = c
  }
  showDetailModal.value = true
}

// Mark paid
const openMarkPaidModal = (c) => {
  paidCycle.value = c
  paidForm.value = {
    utr_number: '',
    payment_mode: 'neft',
    amount_paid: c.net_payable,
    payment_date: new Date().toISOString().slice(0,10),
    payment_notes: ''
  }
  paidError.value = ''
  showPaidModal.value = true
}

const handleMarkPaid = async () => {
  paidBusy.value = true
  paidError.value = ''
  try {
    await markPayoutPaid(paidCycle.value.id, paidForm.value)
    showPaidModal.value = false
    showDetailModal.value = false
    await fetchCycles()
  } catch (e) {
    paidError.value = e?.message || e?.error || 'Failed to mark paid'
  } finally {
    paidBusy.value = false
  }
}

// Deduction
const openAddDeductionModal = (c) => {
  dedCycle.value = c
  deductionForm.value = { type: 'penalty', amount: '', notes: '' }
  dedError.value = ''
  showDeductionModal.value = true
}

const handleAddDeduction = async () => {
  dedBusy.value = true
  dedError.value = ''
  try {
    await addPayoutDeduction(dedCycle.value.id, deductionForm.value)
    showDeductionModal.value = false
    await openCycleDetail(dedCycle.value)
  } catch (e) {
    dedError.value = e?.message || e?.error || 'Failed to add deduction'
  } finally {
    dedBusy.value = false
  }
}

// Addition
const openAddAdditionModal = (c) => {
  addCycle.value = c
  additionForm.value = { type: 'bonus', amount: '', notes: '' }
  addError.value = ''
  showAdditionModal.value = true
}

const handleAddAddition = async () => {
  addBusy.value = true
  addError.value = ''
  try {
    await addPayoutAddition(addCycle.value.id, additionForm.value)
    showAdditionModal.value = false
    await openCycleDetail(addCycle.value)
  } catch (e) {
    addError.value = e?.message || e?.error || 'Failed to add addition'
  } finally {
    addBusy.value = false
  }
}

// CSV download
const handleDownloadCsv = async () => {
  dlBusy.value = true
  try {
    await downloadPayoutsExcel()
  } catch (e) {
    error.value = e.message || 'Download failed'
  } finally {
    dlBusy.value = false
  }
}

onMounted(fetchCycles)
</script>
