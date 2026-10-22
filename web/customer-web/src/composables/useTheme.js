import { ref, watch, onMounted } from 'vue'

const isDark = ref(false)

export function useTheme() {
  onMounted(() => {
    const saved = localStorage.getItem('utterlazy_theme')
    if (saved === 'dark' || (!saved && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
      isDark.value = true
    }
    applyTheme()
  })

  watch(isDark, () => {
    applyTheme()
    localStorage.setItem('utterlazy_theme', isDark.value ? 'dark' : 'light')
  })

  function applyTheme() {
    document.documentElement.classList.toggle('dark', isDark.value)
  }

  function toggle() {
    isDark.value = !isDark.value
  }

  return { isDark, toggle }
}
