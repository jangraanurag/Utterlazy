<template>
  <section class="bg-surface-container-lowest rounded-2xl border border-outline-variant/30 overflow-hidden mb-8">
    <!-- Header -->
    <div class="p-6 border-b border-outline-variant/20 flex items-center justify-between">
      <div>
        <h3 class="font-serif text-xl font-bold flex items-center gap-2">
          <span class="material-symbols-outlined text-primary" style="font-variation-settings: 'FILL' 1;">stadia_controller</span>
          Play While You Wait
        </h3>
        <p class="text-sm text-on-surface-variant mt-1">Your food is on its way — have some fun!</p>
      </div>
      <div class="flex items-center gap-2 bg-primary/10 px-3 py-1.5 rounded-full">
        <span class="material-symbols-outlined text-primary text-[16px]" style="font-variation-settings: 'FILL' 1;">stars</span>
        <span class="font-bold text-sm text-primary">{{ totalScore }} pts</span>
      </div>
    </div>

    <!-- Game Selector -->
    <div class="flex border-b border-outline-variant/20">
      <button v-for="game in games" :key="game.id"
        @click="activeGame = game.id"
        :class="activeGame === game.id ? 'bg-primary/10 text-primary border-b-2 border-primary' : 'text-on-surface-variant hover:bg-surface-container-low'"
        class="flex-1 py-3 text-center font-bold text-sm transition-colors">
        <span class="block text-lg mb-0.5">{{ game.emoji }}</span>
        {{ game.name }}
      </button>
    </div>

    <!-- Game Area -->
    <div class="p-6">

      <!-- ═══ GAME 1: Food Catch ═══ -->
      <div v-if="activeGame === 'catch'" class="text-center">
        <div class="relative w-full h-72 bg-gradient-to-b from-sky-100 to-green-50 rounded-xl overflow-hidden border border-outline-variant/20 select-none" 
          ref="catchArea" @click="handleCatchClick" @touchstart.prevent="handleCatchTouch">
          
          <!-- Falling items -->
          <div v-for="item in fallingItems" :key="item.id"
            class="absolute text-3xl transition-none pointer-events-none"
            :style="{ left: item.x + '%', top: item.y + '%' }">
            {{ item.emoji }}
          </div>

          <!-- Basket -->
          <div class="absolute bottom-2 transition-all duration-100 pointer-events-none"
            :style="{ left: basketX + '%', transform: 'translateX(-50%)' }">
            <div class="text-4xl">🧺</div>
          </div>

          <!-- Score overlay -->
          <div class="absolute top-3 right-3 bg-white/80 backdrop-blur-sm px-3 py-1 rounded-full text-sm font-bold text-primary shadow-sm">
            🍕 {{ catchScore }}
          </div>

          <!-- Lives -->
          <div class="absolute top-3 left-3 bg-white/80 backdrop-blur-sm px-3 py-1 rounded-full text-sm font-bold shadow-sm">
            {{ '❤️'.repeat(catchLives) }}{{ '🖤'.repeat(Math.max(0, 3 - catchLives)) }}
          </div>

          <!-- Game Over -->
          <div v-if="catchGameOver" class="absolute inset-0 bg-black/40 backdrop-blur-sm flex flex-col items-center justify-center">
            <p class="text-white text-3xl font-black mb-2">Game Over!</p>
            <p class="text-white/80 font-bold mb-4">Score: {{ catchScore }}</p>
            <button @click.stop="startCatchGame" class="bg-primary text-white font-bold px-6 py-2 rounded-xl shadow-lg hover:bg-primary/90 transition-colors">
              Play Again
            </button>
          </div>

          <!-- Start -->
          <div v-if="!catchStarted && !catchGameOver" class="absolute inset-0 bg-white/60 backdrop-blur-sm flex flex-col items-center justify-center">
            <p class="text-4xl mb-3">🍕</p>
            <p class="font-bold text-on-surface mb-1">Food Catch</p>
            <p class="text-sm text-on-surface-variant mb-4">Click/tap to move the basket</p>
            <button @click.stop="startCatchGame" class="bg-primary text-white font-bold px-6 py-2 rounded-xl shadow-lg hover:bg-primary/90 transition-colors">
              Start Game
            </button>
          </div>
        </div>
      </div>

      <!-- ═══ GAME 2: Memory Match ═══ -->
      <div v-if="activeGame === 'memory'" class="text-center">
        <div class="flex items-center justify-between mb-4">
          <div class="text-sm font-bold text-on-surface-variant">Moves: <span class="text-primary">{{ memoryMoves }}</span></div>
          <button v-if="memoryComplete || memoryCards.length > 0" @click="startMemoryGame"
            class="text-xs font-bold text-primary bg-primary/10 px-3 py-1 rounded-full hover:bg-primary/20 transition-colors">
            {{ memoryComplete ? '🎉 Play Again' : 'Restart' }}
          </button>
        </div>

        <div v-if="memoryCards.length === 0" class="py-10">
          <p class="text-4xl mb-3">🧠</p>
          <p class="font-bold text-on-surface mb-1">Memory Match</p>
          <p class="text-sm text-on-surface-variant mb-4">Match food pairs to win!</p>
          <button @click="startMemoryGame" class="bg-primary text-white font-bold px-6 py-2 rounded-xl shadow-lg hover:bg-primary/90 transition-colors">
            Start Game
          </button>
        </div>

        <div v-else class="grid grid-cols-4 gap-2 max-w-xs mx-auto">
          <button v-for="(card, i) in memoryCards" :key="i"
            @click="flipCard(i)"
            :disabled="card.matched || card.flipped || memoryBusy"
            class="aspect-square rounded-xl text-2xl font-bold flex items-center justify-center transition-all duration-300 border-2 disabled:cursor-default"
            :class="card.flipped || card.matched
              ? 'bg-white border-primary/30 scale-95 shadow-inner'
              : 'bg-gradient-to-br from-primary to-primary/80 border-primary/20 text-white hover:scale-105 shadow-md cursor-pointer'">
            <span v-if="card.flipped || card.matched">{{ card.emoji }}</span>
            <span v-else class="material-symbols-outlined text-white/60">help</span>
          </button>
        </div>

        <div v-if="memoryComplete" class="mt-4 p-3 bg-green-50 rounded-xl border border-green-200">
          <p class="font-bold text-green-700">🎉 Perfect! Completed in {{ memoryMoves }} moves!</p>
        </div>
      </div>

      <!-- ═══ GAME 3: Emoji Trivia ═══ -->
      <div v-if="activeGame === 'trivia'" class="text-center">
        <div v-if="!triviaActive" class="py-10">
          <p class="text-4xl mb-3">🤔</p>
          <p class="font-bold text-on-surface mb-1">Food Trivia</p>
          <p class="text-sm text-on-surface-variant mb-4">Guess the dish from emoji clues!</p>
          <button @click="startTrivia" class="bg-primary text-white font-bold px-6 py-2 rounded-xl shadow-lg hover:bg-primary/90 transition-colors">
            Start Quiz
          </button>
        </div>

        <div v-else>
          <!-- Progress -->
          <div class="flex gap-1 mb-4">
            <div v-for="(q, i) in triviaQuestions" :key="i"
              class="flex-1 h-1.5 rounded-full transition-colors"
              :class="i < triviaIndex ? 'bg-primary' : i === triviaIndex ? 'bg-primary/40' : 'bg-surface-container-high'">
            </div>
          </div>

          <div v-if="triviaIndex < triviaQuestions.length">
            <p class="text-5xl mb-4 py-4">{{ triviaQuestions[triviaIndex].emoji }}</p>
            <p class="text-sm font-bold text-on-surface-variant mb-4">What dish is this?</p>
            <div class="grid grid-cols-2 gap-2 max-w-sm mx-auto">
              <button v-for="(opt, i) in triviaQuestions[triviaIndex].options" :key="i"
                @click="answerTrivia(opt)"
                :disabled="triviaAnswered"
                class="px-4 py-3 rounded-xl text-sm font-bold transition-all border-2"
                :class="triviaAnswered
                  ? opt === triviaQuestions[triviaIndex].answer
                    ? 'bg-green-100 border-green-400 text-green-700 scale-105'
                    : opt === triviaSelected ? 'bg-red-100 border-red-400 text-red-600' : 'bg-surface-container border-outline-variant/20 opacity-50'
                  : 'bg-surface-container-lowest border-outline-variant/30 hover:border-primary/40 hover:bg-primary/5 cursor-pointer'">
                {{ opt }}
              </button>
            </div>
          </div>

          <!-- Quiz Complete -->
          <div v-else class="py-8">
            <p class="text-4xl mb-3">{{ triviaScore >= 4 ? '🏆' : triviaScore >= 2 ? '👏' : '🤷' }}</p>
            <p class="font-bold text-xl text-on-surface mb-1">{{ triviaScore }} / {{ triviaQuestions.length }} Correct!</p>
            <p class="text-sm text-on-surface-variant mb-4">{{ triviaScore >= 4 ? 'You\'re a food genius!' : triviaScore >= 2 ? 'Not bad, foodie!' : 'Better luck next time!' }}</p>
            <button @click="startTrivia" class="bg-primary text-white font-bold px-6 py-2 rounded-xl shadow-lg hover:bg-primary/90 transition-colors">
              Play Again
            </button>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref, onUnmounted, watch } from 'vue'

const props = defineProps({
  initialGame: {
    type: String,
    default: 'catch'
  }
})

const activeGame = ref(props.initialGame || 'catch')

watch(() => props.initialGame, (newVal) => {
  if (newVal) activeGame.value = newVal
})
const totalScore = ref(0)

const games = [
  { id: 'catch', name: 'Food Catch', emoji: '🍕' },
  { id: 'memory', name: 'Memory Match', emoji: '🧠' },
  { id: 'trivia', name: 'Food Trivia', emoji: '🤔' },
]

// ═══════════════════════════════════════
// GAME 1: Food Catch
// ═══════════════════════════════════════
const catchArea = ref(null)
const basketX = ref(50)
const fallingItems = ref([])
const catchScore = ref(0)
const catchLives = ref(3)
const catchStarted = ref(false)
const catchGameOver = ref(false)
let catchInterval = null
let fallInterval = null
let itemIdCounter = 0

const foodEmojis = ['🍕', '🍔', '🌮', '🍜', '🍩', '🍣', '🧁', '🍗', '🍱', '🥟']
const badEmojis = ['💣', '🧨']

const handleCatchClick = (e) => {
  if (!catchStarted.value || catchGameOver.value) return
  const rect = catchArea.value.getBoundingClientRect()
  basketX.value = Math.max(5, Math.min(95, ((e.clientX - rect.left) / rect.width) * 100))
}

const handleCatchTouch = (e) => {
  if (!catchStarted.value || catchGameOver.value) return
  const rect = catchArea.value.getBoundingClientRect()
  const touch = e.touches[0]
  basketX.value = Math.max(5, Math.min(95, ((touch.clientX - rect.left) / rect.width) * 100))
}

const startCatchGame = () => {
  catchScore.value = 0
  catchLives.value = 3
  catchGameOver.value = false
  catchStarted.value = true
  fallingItems.value = []
  itemIdCounter = 0

  clearInterval(catchInterval)
  clearInterval(fallInterval)

  // Spawn items
  catchInterval = setInterval(() => {
    if (catchGameOver.value) return
    const isBad = Math.random() < 0.15
    fallingItems.value.push({
      id: itemIdCounter++,
      x: Math.random() * 85 + 5,
      y: -5,
      emoji: isBad ? badEmojis[Math.floor(Math.random() * badEmojis.length)] : foodEmojis[Math.floor(Math.random() * foodEmojis.length)],
      bad: isBad,
    })
  }, 800)

  // Move items down
  fallInterval = setInterval(() => {
    if (catchGameOver.value) return
    fallingItems.value = fallingItems.value.filter(item => {
      item.y += 2.5
      // Caught?
      if (item.y >= 85 && Math.abs(item.x - basketX.value) < 10) {
        if (item.bad) {
          catchLives.value--
          if (catchLives.value <= 0) {
            catchGameOver.value = true
            totalScore.value += catchScore.value
            clearInterval(catchInterval)
            clearInterval(fallInterval)
          }
        } else {
          catchScore.value++
        }
        return false
      }
      // Missed?
      if (item.y > 105) {
        if (!item.bad) {
          catchLives.value--
          if (catchLives.value <= 0) {
            catchGameOver.value = true
            totalScore.value += catchScore.value
            clearInterval(catchInterval)
            clearInterval(fallInterval)
          }
        }
        return false
      }
      return true
    })
  }, 50)
}

// ═══════════════════════════════════════
// GAME 2: Memory Match
// ═══════════════════════════════════════
const memoryCards = ref([])
const memoryMoves = ref(0)
const memoryBusy = ref(false)
const memoryComplete = ref(false)
let flippedIndices = []

const memoryEmojis = ['🍕', '🍔', '🌮', '🍜', '🍩', '🍣', '🧁', '🍗']

const startMemoryGame = () => {
  const pairs = memoryEmojis.slice(0, 6)
  const deck = [...pairs, ...pairs].map(emoji => ({ emoji, flipped: false, matched: false }))
  // Shuffle
  for (let i = deck.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [deck[i], deck[j]] = [deck[j], deck[i]]
  }
  memoryCards.value = deck
  memoryMoves.value = 0
  memoryComplete.value = false
  flippedIndices = []
}

const flipCard = (index) => {
  if (memoryBusy.value || memoryCards.value[index].flipped || memoryCards.value[index].matched) return

  memoryCards.value[index].flipped = true
  flippedIndices.push(index)

  if (flippedIndices.length === 2) {
    memoryMoves.value++
    memoryBusy.value = true
    const [a, b] = flippedIndices

    if (memoryCards.value[a].emoji === memoryCards.value[b].emoji) {
      memoryCards.value[a].matched = true
      memoryCards.value[b].matched = true
      flippedIndices = []
      memoryBusy.value = false

      if (memoryCards.value.every(c => c.matched)) {
        memoryComplete.value = true
        const bonus = Math.max(1, 12 - memoryMoves.value)
        totalScore.value += bonus
      }
    } else {
      setTimeout(() => {
        memoryCards.value[a].flipped = false
        memoryCards.value[b].flipped = false
        flippedIndices = []
        memoryBusy.value = false
      }, 800)
    }
  }
}

// ═══════════════════════════════════════
// GAME 3: Food Trivia
// ═══════════════════════════════════════
const triviaActive = ref(false)
const triviaIndex = ref(0)
const triviaScore = ref(0)
const triviaAnswered = ref(false)
const triviaSelected = ref('')

const allTriviaQuestions = [
  { emoji: '🍚🍛🫚🧅', answer: 'Biryani', options: ['Biryani', 'Pulao', 'Fried Rice', 'Khichdi'] },
  { emoji: '🫓🧈🔥', answer: 'Butter Naan', options: ['Roti', 'Paratha', 'Butter Naan', 'Kulcha'] },
  { emoji: '🐔🍢🔥🟢', answer: 'Chicken Tikka', options: ['Tandoori', 'Chicken Tikka', 'Kebab', 'Seekh'] },
  { emoji: '🥘🍅🧈🧀', answer: 'Paneer Butter Masala', options: ['Dal Makhani', 'Kadai Paneer', 'Paneer Butter Masala', 'Shahi Paneer'] },
  { emoji: '🍞🧅🫚🌶️', answer: 'Pav Bhaji', options: ['Pav Bhaji', 'Vada Pav', 'Chole Bhature', 'Misal Pav'] },
  { emoji: '🫘🍛🧈🔥', answer: 'Dal Makhani', options: ['Rajma', 'Dal Makhani', 'Chana Dal', 'Dal Tadka'] },
  { emoji: '🥟🫚🌶️💨', answer: 'Momos', options: ['Samosa', 'Momos', 'Dumpling', 'Spring Roll'] },
  { emoji: '🍦🥛🌹🥜', answer: 'Kulfi', options: ['Ice Cream', 'Kulfi', 'Falooda', 'Rabri'] },
  { emoji: '🫓🥔🧅🌶️', answer: 'Aloo Paratha', options: ['Aloo Paratha', 'Gobi Paratha', 'Thepla', 'Naan'] },
  { emoji: '🍗🍚🥥🍃', answer: 'Chicken Curry', options: ['Butter Chicken', 'Chicken Curry', 'Chicken Biryani', 'Chicken Korma'] },
]

const triviaQuestions = ref([])

const startTrivia = () => {
  // Pick 5 random questions
  const shuffled = [...allTriviaQuestions].sort(() => Math.random() - 0.5)
  triviaQuestions.value = shuffled.slice(0, 5).map(q => ({
    ...q,
    options: [...q.options].sort(() => Math.random() - 0.5)
  }))
  triviaIndex.value = 0
  triviaScore.value = 0
  triviaAnswered.value = false
  triviaSelected.value = ''
  triviaActive.value = true
}

const answerTrivia = (opt) => {
  if (triviaAnswered.value) return
  triviaAnswered.value = true
  triviaSelected.value = opt
  if (opt === triviaQuestions.value[triviaIndex.value].answer) {
    triviaScore.value++
    totalScore.value += 2
  }
  setTimeout(() => {
    triviaIndex.value++
    triviaAnswered.value = false
    triviaSelected.value = ''
  }, 1200)
}

// Cleanup
onUnmounted(() => {
  clearInterval(catchInterval)
  clearInterval(fallInterval)
})
</script>
