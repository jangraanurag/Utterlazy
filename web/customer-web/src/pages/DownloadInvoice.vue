<template>
  <div class="bg-background text-on-surface min-h-screen">
    <nav class="fixed top-0 left-0 w-full flex justify-between items-center px-8 py-4 bg-[#fdf9f4]/60 dark:bg-stone-950/60 backdrop-blur-xl z-50">
      <div class="flex items-center gap-6">
        <router-link to="/" class="font-serif text-3xl italic font-bold text-[#c04828]">UtterLazy</router-link>
        <router-link to="/orders" class="flex items-center gap-1 text-on-surface-variant hover:text-primary transition-colors font-medium">
          <span class="material-symbols-outlined text-[18px]">arrow_back</span> Back to Orders
        </router-link>
      </div>
    </nav>

    <main class="pt-28 px-8 max-w-3xl mx-auto pb-16">
      <div v-if="loading" class="py-20 text-center text-on-surface-variant">
        <span class="material-symbols-outlined text-5xl animate-spin block mb-4">progress_activity</span>
        <p class="text-lg">Generating tax invoice...</p>
      </div>

      <div v-else-if="error" class="py-20 text-center">
        <span class="material-symbols-outlined text-5xl text-red-400 block mb-4">error</span>
        <p class="text-lg text-on-surface-variant">{{ error }}</p>
        <router-link to="/orders" class="mt-4 inline-block text-primary font-bold hover:underline">Go to Orders</router-link>
      </div>

      <div v-else-if="invoice" class="bg-surface rounded-3xl p-10 border border-outline-variant/30 shadow-lg bg-white">
        <!-- Header -->
        <div class="flex justify-between items-start mb-8">
          <div>
            <h1 class="font-serif text-3xl font-bold text-primary">UTTERLAZY</h1>
            <p class="text-on-surface-variant text-sm mt-1">Tax Invoice No: <span class="font-black text-on-surface">{{ invoice.invoice_number }}</span></p>
            <p class="text-on-surface-variant text-xs mt-0.5">Order ID: <span class="select-all">{{ invoice.order_id }}</span></p>
          </div>
          <div class="text-right text-sm text-on-surface-variant">
            <p class="font-extrabold text-on-surface">DATE: {{ invoice.invoice_date }}</p>
          </div>
        </div>

        <!-- Seller vs Buyer Section -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 my-8 py-8 border-t border-b border-gray-100">
          <div>
            <h3 class="font-black text-xs uppercase tracking-widest text-gray-400 mb-2">Seller (Restaurant)</h3>
            <p class="font-extrabold text-sm text-on-surface uppercase">{{ invoice.seller.name }}</p>
            <p class="text-xs text-gray-500 mt-1 whitespace-pre-line">{{ invoice.seller.address }}</p>
            <p v-if="invoice.seller.gstin && invoice.seller.gstin !== 'N/A'" class="text-xs font-black text-primary mt-2">GSTIN: {{ invoice.seller.gstin }}</p>
          </div>
          <div>
            <h3 class="font-black text-xs uppercase tracking-widest text-gray-400 mb-2">Buyer (Customer)</h3>
            <p class="font-extrabold text-sm text-on-surface uppercase">{{ invoice.buyer.name }}</p>
            
            <div v-if="invoice.buyer.is_business" class="mt-2 p-4 bg-orange-50/20 border border-orange-100/50 rounded-2xl">
               <p class="text-[9px] font-black text-primary uppercase tracking-widest">Business Invoice (GST ITC)</p>
               <p class="font-extrabold text-xs text-on-surface mt-1 uppercase">{{ invoice.buyer.company_name }}</p>
               <p class="text-xs font-bold text-gray-600 mt-0.5">GSTIN: {{ invoice.buyer.gstin }}</p>
            </div>
            <p v-else class="text-xs text-gray-500 mt-1">{{ invoice.buyer.address || 'N/A' }}</p>
          </div>
        </div>

        <!-- Items Table -->
        <div class="mb-8">
          <h3 class="font-black text-xs uppercase tracking-widest text-gray-400 mb-4">Items Manifest</h3>
          <table class="w-full text-left border-collapse">
             <thead>
                <tr class="border-b border-gray-100 text-[10px] font-black text-gray-400 uppercase tracking-wider">
                   <th class="pb-3">Item</th>
                   <th class="pb-3 text-center">Qty</th>
                   <th class="pb-3 text-right">Unit Price</th>
                   <th class="pb-3 text-right">GST %</th>
                   <th class="pb-3 text-right">GST Amt</th>
                   <th class="pb-3 text-right">Total</th>
                </tr>
             </thead>
             <tbody class="divide-y divide-gray-50">
                <tr v-for="item in invoice.items" :key="item.name" class="text-xs font-bold text-on-surface">
                   <td class="py-3 font-extrabold">{{ item.name }} <span class="text-[9px] font-medium text-gray-400 block mt-0.5">HSN: {{ item.hsn_code }}</span></td>
                   <td class="py-3 text-center">{{ item.quantity }}</td>
                   <td class="py-3 text-right">₹{{ parseFloat(item.unit_price).toFixed(2) }}</td>
                   <td class="py-3 text-right">{{ item.gst_percent }}%</td>
                   <td class="py-3 text-right">₹{{ parseFloat(item.gst_amount).toFixed(2) }}</td>
                   <td class="py-3 text-right font-black">₹{{ parseFloat(item.total).toFixed(2) }}</td>
                </tr>
             </tbody>
          </table>
        </div>

        <!-- Summary -->
        <div class="border-t border-gray-100 pt-6 space-y-3">
          <div class="flex justify-between text-xs font-bold text-gray-500">
            <span>Subtotal</span><span>₹{{ parseFloat(invoice.summary.subtotal).toFixed(2) }}</span>
          </div>
          <div class="flex justify-between text-xs font-bold text-gray-500">
            <span>CGST</span><span>₹{{ parseFloat(invoice.summary.cgst).toFixed(2) }}</span>
          </div>
          <div class="flex justify-between text-xs font-bold text-gray-500">
            <span>SGST</span><span>₹{{ parseFloat(invoice.summary.sgst).toFixed(2) }}</span>
          </div>
          <div v-if="invoice.summary.delivery_fee > 0" class="flex justify-between text-xs font-bold text-gray-500">
            <span>Delivery Fee</span><span>₹{{ parseFloat(invoice.summary.delivery_fee).toFixed(2) }}</span>
          </div>
          <div v-if="invoice.summary.discount > 0" class="flex justify-between text-xs font-bold text-green-600">
            <span>Discount</span><span>-₹{{ parseFloat(invoice.summary.discount).toFixed(2) }}</span>
          </div>
          <div class="flex justify-between text-base font-black pt-3 border-t border-gray-100">
            <span>Grand Total</span><span class="text-primary text-lg text-primary">₹{{ parseFloat(invoice.summary.grand_total).toFixed(2) }}</span>
          </div>
        </div>

        <!-- Payment Details -->
        <div v-if="invoice.payment" class="mt-8 pt-4 border-t border-gray-100 flex items-center justify-between text-[11px] font-black tracking-wider text-gray-400 uppercase">
          <span>PAYMENT MODE: <span class="text-on-surface font-extrabold">{{ invoice.payment.mode }}</span></span>
          <span>PAYMENT STATUS: <span :class="invoice.payment.status === 'paid' ? 'text-green-600' : 'text-primary'" class="font-extrabold">{{ invoice.payment.status }}</span></span>
        </div>

        <!-- Action Button -->
        <button @click="downloadPdf" class="mt-8 w-full py-4 rounded-xl bg-primary text-white font-serif font-bold uppercase tracking-widest text-sm flex items-center justify-center gap-2 hover:scale-[1.02] active:scale-[0.98] transition-all">
          <span class="material-symbols-outlined text-[18px]">download</span> Download PDF / Print
        </button>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { getInvoice } from '../api'

const route = useRoute()
const invoice = ref(null)
const loading = ref(true)
const error = ref('')

onMounted(async () => {
  const orderId = route.query.order_id || route.params.id
  if (!orderId) {
    error.value = 'No order ID provided'
    loading.value = false
    return
  }
  try {
    const res = await getInvoice(orderId)
    invoice.value = res.invoice
  } catch (e) {
    error.value = e.message || 'Failed to load invoice'
  } finally {
    loading.value = false
  }
})

const downloadPdf = () => {
  window.print()
}
</script>
