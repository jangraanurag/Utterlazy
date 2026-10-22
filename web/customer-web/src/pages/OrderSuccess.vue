<template>
  <div class="bg-background text-on-surface min-h-screen flex items-center justify-center px-4 py-8 relative overflow-hidden">
    <!-- Floating Toast Notification -->
    <transition name="toast-fade">
      <div v-if="showToast" class="fixed top-6 left-1/2 -translate-x-1/2 z-[100] bg-surface-container-highest text-on-surface border border-outline-variant/30 py-3 px-6 rounded-full shadow-2xl flex items-center gap-2 max-w-sm">
        <span class="material-symbols-outlined text-secondary text-lg">check_circle</span>
        <span class="font-sans font-bold text-xs tracking-wide">{{ toastMsg }}</span>
      </div>
    </transition>

    <div class="w-full max-w-lg text-center relative z-10">
      <!-- Animated Check -->
      <div class="w-24 h-24 rounded-full bg-secondary/10 mx-auto flex items-center justify-center mb-8 animate-bounce">
        <span class="material-symbols-outlined text-secondary text-5xl" style="font-variation-settings: 'FILL' 1;">check_circle</span>
      </div>

      <h1 class="font-serif text-4xl font-bold text-on-surface mb-3">Order Placed!</h1>
      <p class="text-on-surface-variant text-lg mb-8">Your culinary masterpiece is being prepared with love.</p>

      <!-- Scratch Card Section -->
      <div v-if="showScratchCard" class="relative bg-surface-container-lowest rounded-3xl border border-outline-variant/30 p-8 mb-8 text-center overflow-hidden flex flex-col items-center">
         
         <!-- Confetti rendering -->
         <div v-for="c in confettis" :key="c.id" 
           :style="{
             left: c.x + '%',
             top: '-10px',
             backgroundColor: c.color,
             width: c.size + 'px',
             height: c.shape === 'circle' ? c.size + 'px' : c.shape === 'triangle' ? '0px' : c.size * 0.6 + 'px',
             borderLeft: c.shape === 'triangle' ? c.size/2 + 'px solid transparent' : 'none',
             borderRight: c.shape === 'triangle' ? c.size/2 + 'px solid transparent' : 'none',
             borderBottom: c.shape === 'triangle' ? c.size + 'px solid ' + c.color : 'none',
             borderRadius: c.shape === 'circle' ? '50%' : '0px',
             transform: 'rotate(' + c.tilt + 'deg)',
             animationDelay: c.delay + 's',
             animationDuration: c.duration + 's'
           }"
           class="confetti-particle absolute pointer-events-none z-50">
         </div>

         <h3 class="font-serif text-xl font-bold text-on-surface mb-2">🎁 Mystery Scratch Card!</h3>
         <p class="text-[10px] font-black text-on-surface-variant uppercase tracking-widest mb-6">Scratch to reveal a premium coupon reward</p>
         
         <div class="relative w-[280px] h-[180px] rounded-2xl overflow-hidden shadow-2xl border border-outline-variant/30 bg-gradient-to-br from-amber-50/50 to-orange-50/50 flex items-center justify-center">
            
            <!-- Underneath Content (Premium Voucher Design) -->
            <div class="w-full h-full p-4 flex flex-col items-center justify-between text-center select-none bg-white relative">
               <!-- Side Ticket Cutouts -->
               <div class="absolute left-[-6px] top-1/2 -translate-y-1/2 w-3 h-6 rounded-full bg-surface-container-lowest border-r border-outline-variant/20"></div>
               <div class="absolute right-[-6px] top-1/2 -translate-y-1/2 w-3 h-6 rounded-full bg-surface-container-lowest border-l border-outline-variant/20"></div>

               <div class="flex-1 flex flex-col items-center justify-center pt-1">
                  <div class="flex items-center gap-1 bg-primary/10 text-primary py-0.5 px-2.5 rounded-full text-[9px] font-black uppercase tracking-wider mb-1.5 shadow-sm">
                     <span class="material-symbols-outlined text-[10px] animate-pulse">local_activity</span>
                     {{ scratchReward?.brand }}
                  </div>
                  <p class="font-black text-sm text-on-surface leading-tight px-1 font-serif">{{ scratchReward?.title }}</p>
                  <p class="text-[9px] font-medium text-on-surface-variant mt-1.5 px-3 leading-relaxed max-w-[240px] truncate-3-lines">{{ scratchReward?.description }}</p>
               </div>
               
               <!-- Dashed Divider -->
               <div class="w-full border-t border-dashed border-outline-variant/40 my-2"></div>
               
               <div class="w-full flex items-center gap-2">
                  <div class="flex-1 bg-surface-container-low py-1.5 px-2.5 rounded-lg border border-outline-variant/15 flex items-center justify-between min-w-0 transition-all hover:bg-surface-container-high">
                     <span class="font-mono font-black text-[11px] text-on-surface tracking-wider select-all truncate mr-1">{{ scratchReward?.code }}</span>
                     <button @click="copyCode(scratchReward?.code)" class="material-symbols-outlined text-[16px] text-primary hover:scale-125 active:scale-95 transition-all shrink-0">content_copy</button>
                  </div>
                  <a :href="scratchReward?.link" target="_blank" class="primary-gradient hover:opacity-90 text-white text-[9px] font-black tracking-widest uppercase py-2.5 px-4 rounded-lg transition-all shadow-md shadow-primary/10 shrink-0">REDEEM</a>
               </div>
            </div>

            <!-- Canvas Overlay (Silver/Gold scratchable layer) -->
            <canvas v-if="!isScratched" ref="scratchCanvas" width="280" height="180" 
              @mousedown="startScratch" 
              @mousemove="scratching" 
              @mouseup="endScratch" 
              @mouseleave="endScratch" 
              @touchstart.prevent="startScratchTouch" 
              @touchmove.prevent="scratchingTouch" 
              @touchend="endScratch" 
              class="absolute inset-0 cursor-pointer rounded-2xl touch-none"></canvas>
         </div>

         <!-- Instructions / Status -->
         <p class="text-[10px] font-black tracking-widest uppercase mt-4" :class="isScratched ? 'text-green-600' : 'text-secondary'">
            {{ isScratched ? 'Coupon Revealed! Copy the code to redeem.' : 'Swipe or drag to scratch' }}
         </p>
      </div>

      <!-- Order Details Card -->
      <div class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 p-8 mb-8 text-left space-y-4">
        <div class="flex justify-between items-center">
          <span class="text-on-surface-variant text-sm font-medium">Order ID</span>
          <span class="font-bold text-on-surface font-mono text-sm">{{ orderId?.slice(0, 12)?.toUpperCase() || '—' }}</span>
        </div>
        <div class="border-t border-outline-variant/20"></div>
        <div class="flex justify-between items-center">
          <span class="text-on-surface-variant text-sm font-medium">Grand Total</span>
          <span class="font-bold text-primary text-xl">₹{{ grandTotal }}</span>
        </div>
        <div class="border-t border-outline-variant/20"></div>
        <div class="flex justify-between items-center">
          <span class="text-on-surface-variant text-sm font-medium flex items-center gap-1">
            <span class="material-symbols-outlined text-[16px]" style="font-variation-settings: 'FILL' 1;">stars</span> Coins Earned
          </span>
          <span class="font-bold text-secondary">+{{ coinsEarned }}</span>
        </div>
        <div v-if="coinsRedeemed > 0" class="flex justify-between items-center">
          <span class="text-on-surface-variant text-sm font-medium">Coins Redeemed</span>
          <span class="font-bold text-tertiary">-{{ coinsRedeemed }}</span>
        </div>
        <div class="flex justify-between items-center">
          <span class="text-on-surface-variant text-sm font-medium">New Balance</span>
          <span class="font-bold text-on-surface">{{ newBalance }} coins</span>
        </div>
      </div>

      <!-- Actions -->
      <div class="space-y-3">
        <router-link v-if="orderId" :to="'/order/' + orderId + '/track'"
          class="block w-full primary-gradient text-on-primary font-bold py-4 rounded-xl shadow-lg shadow-primary/20 text-center">
          Track Your Order
        </router-link>
        <router-link to="/"
          class="block w-full bg-surface-container-high text-on-surface font-bold py-4 rounded-xl text-center hover:bg-surface-container-highest transition-colors">
          Back to Home
        </router-link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { getScratchCard } from '../api'

const route = useRoute()
const orderId = route.query.orderId || ''
const grandTotal = route.query.total || '0.00'
const coinsEarned = route.query.earned || 0
const coinsRedeemed = route.query.redeemed || 0
const newBalance = route.query.balance || 0

const scratchReward = ref(null)
const showScratchCard = ref(false)
const isScratched = ref(false)
const confettis = ref([])

const showToast = ref(false)
const toastMsg = ref('')
const triggerToast = (msg) => {
  toastMsg.value = msg
  showToast.value = true
  setTimeout(() => {
    showToast.value = false
  }, 2500)
}

const scratchCanvas = ref(null)
let ctx = null
let isDrawing = false

const loadScratchCard = async () => {
  try {
    const res = await getScratchCard()
    if (res.success) {
      scratchReward.value = res.reward
      showScratchCard.value = true
      // Save to localStorage for history
      try {
        const raw = localStorage.getItem('scratch_cards') || '[]'
        const cards = JSON.parse(raw)
        cards.unshift({
          ...res.reward,
          saved_at: new Date().toISOString(),
          scratched: false,
        })
        localStorage.setItem('scratch_cards', JSON.stringify(cards))
      } catch {}
    }
  } catch (e) {
    console.error('Scratch card fetch error:', e)
  }
}

const initCanvas = () => {
  const canvas = scratchCanvas.value
  if (!canvas) return
  ctx = canvas.getContext('2d')
  
  // Luxury metallic gold gradient
  const grad = ctx.createLinearGradient(0, 0, canvas.width, canvas.height)
  grad.addColorStop(0, '#FFE082') // Light Gold
  grad.addColorStop(0.3, '#FFD54F')
  grad.addColorStop(0.5, '#FFF8E1') // Glitter highlight line
  grad.addColorStop(0.7, '#FFC107')
  grad.addColorStop(1, '#FFA000') // Dark Gold
  
  ctx.fillStyle = grad
  ctx.fillRect(0, 0, canvas.width, canvas.height)
  
  // Decorative diagonal mesh pattern
  ctx.strokeStyle = 'rgba(255, 255, 255, 0.25)'
  ctx.lineWidth = 1.5
  for (let i = -canvas.width; i < canvas.width; i += 24) {
    ctx.beginPath()
    ctx.moveTo(i, 0)
    ctx.lineTo(i + canvas.height, canvas.height)
    ctx.stroke()
    
    ctx.beginPath()
    ctx.moveTo(i + canvas.width, 0)
    ctx.lineTo(i, canvas.height)
    ctx.stroke()
  }

  // Draw sparkly glitter stars
  ctx.fillStyle = 'rgba(255, 255, 255, 0.6)'
  for (let i = 0; i < 20; i++) {
    const x = Math.random() * canvas.width
    const y = Math.random() * canvas.height
    const r = Math.random() * 2 + 1
    ctx.beginPath()
    ctx.arc(x, y, r, 0, Math.PI * 2)
    ctx.fill()
  }
  
  // DrawCentered Emoji & text instructions
  ctx.shadowColor = 'rgba(0, 0, 0, 0.15)'
  ctx.shadowBlur = 6
  ctx.shadowOffsetY = 2
  
  ctx.font = '48px sans-serif'
  ctx.fillStyle = '#111827'
  ctx.textAlign = 'center'
  ctx.textBaseline = 'middle'
  ctx.fillText('🎁', canvas.width / 2, canvas.height / 2 - 16)
  
  ctx.font = 'bold 11px sans-serif'
  ctx.fillStyle = '#5c4308'
  ctx.fillText('LUCKY SCRATCH CARD', canvas.width / 2, canvas.height / 2 + 28)
  
  ctx.font = '900 10px sans-serif'
  ctx.fillStyle = '#9c7310'
  ctx.fillText('SWIPE TO REVEAL REWARD', canvas.width / 2, canvas.height / 2 + 45)
  
  ctx.shadowBlur = 0
  ctx.shadowOffsetY = 0
}

watch(scratchCanvas, (canvas) => {
  if (canvas) {
    initCanvas()
  }
})

const scratch = (x, y) => {
  if (!ctx || isScratched.value) return
  ctx.globalCompositeOperation = 'destination-out'
  ctx.beginPath()
  ctx.arc(x, y, 22, 0, Math.PI * 2)
  ctx.fill()
  
  checkScratchPercentage()
}

const startScratch = (e) => {
  isDrawing = true
  const rect = scratchCanvas.value.getBoundingClientRect()
  scratch(e.clientX - rect.left, e.clientY - rect.top)
}

const scratching = (e) => {
  if (!isDrawing) return
  const rect = scratchCanvas.value.getBoundingClientRect()
  scratch(e.clientX - rect.left, e.clientY - rect.top)
}

const endScratch = () => {
  isDrawing = false
}

const startScratchTouch = (e) => {
  isDrawing = true
  const rect = scratchCanvas.value.getBoundingClientRect()
  const touch = e.touches[0]
  scratch(touch.clientX - rect.left, touch.clientY - rect.top)
}

const scratchingTouch = (e) => {
  if (!isDrawing) return
  const rect = scratchCanvas.value.getBoundingClientRect()
  const touch = e.touches[0]
  scratch(touch.clientX - rect.left, touch.clientY - rect.top)
}

const checkScratchPercentage = () => {
  const canvas = scratchCanvas.value
  if (!canvas || isScratched.value) return
  const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height)
  const pixels = imgData.data
  let cleared = 0
  
  for (let i = 3; i < pixels.length; i += 4) {
    if (pixels[i] === 0) {
      cleared++
    }
  }
  
  const percentage = (cleared / (pixels.length / 4)) * 100
  if (percentage > 45) {
    isScratched.value = true
    isDrawing = false
    ctx.clearRect(0, 0, canvas.width, canvas.height)
    triggerConfetti()
    // Mark scratched in localStorage
    try {
      const raw = localStorage.getItem('scratch_cards') || '[]'
      const cards = JSON.parse(raw)
      if (cards.length > 0) {
        cards[0].scratched = true
        localStorage.setItem('scratch_cards', JSON.stringify(cards))
      }
    } catch {}
  }
}

const triggerConfetti = () => {
  const colors = ['#FFC107', '#FF5722', '#E91E63', '#4CAF50', '#00BCD4', '#3F51B5']
  const shapes = ['square', 'circle', 'triangle']
  
  for (let i = 0; i < 50; i++) {
    confettis.value.push({
      id: i,
      x: Math.random() * 100,
      size: Math.random() * 8 + 6,
      color: colors[Math.floor(Math.random() * colors.length)],
      shape: shapes[Math.floor(Math.random() * shapes.length)],
      delay: Math.random() * 0.5,
      duration: Math.random() * 1.5 + 1.5,
      tilt: Math.random() * 360,
    })
  }
  
  setTimeout(() => {
    confettis.value = []
  }, 4000)
}

const copyCode = (code) => {
  if (!code) return
  navigator.clipboard.writeText(code)
  triggerToast('Promo code copied successfully!')
}

onMounted(() => {
  loadScratchCard()
})
</script>

<style scoped>
@keyframes fall {
  0% {
    transform: translateY(-20px) rotate(0deg);
    opacity: 1;
  }
  100% {
    transform: translateY(600px) rotate(360deg);
    opacity: 0;
  }
}

.confetti-particle {
  animation-name: fall;
  animation-timing-function: linear;
  animation-fill-mode: forwards;
}

.truncate-3-lines {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;  
  overflow: hidden;
}

.toast-fade-enter-active,
.toast-fade-leave-active {
  transition: all 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.toast-fade-enter-from {
  transform: translate(-50%, -24px);
  opacity: 0;
}

.toast-fade-leave-to {
  transform: translate(-50%, -24px);
  opacity: 0;
}
</style>
