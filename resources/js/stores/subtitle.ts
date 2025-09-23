import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { SubtitleService } from '../services/subtitle.service'
import type { SubtitleData, SubtitleEntry } from '../types/subtitle'

export const useSubtitleStore = defineStore('subtitle', () => {
  // State
  const subtitleData = ref<SubtitleData | null>(null)
  const subtitleText = ref('')
  const currentTime = ref(0)
  const isPlaying = ref(false)

  // Getters
  const currentSubtitle = computed(() => {
    if (!subtitleData.value) return null
    return SubtitleService.getSubtitleAtTime(subtitleData.value, currentTime.value)
  })

  const totalDuration = computed(() => {
    return subtitleData.value?.totalDuration || 0
  })

  const subtitleCount = computed(() => {
    return subtitleData.value?.entries.length || 0
  })

  const hasSubtitles = computed(() => {
    return subtitleData.value !== null && subtitleData.value.entries.length > 0
  })

  // Actions
  function setSubtitleText(text: string) {
    subtitleText.value = text
  }

  function parseSubtitles() {
    if (subtitleText.value.trim()) {
      try {
        subtitleData.value = SubtitleService.parseSubtitleText(subtitleText.value, 'user-input')
      } catch (error) {
        console.error('Error parsing subtitles:', error)
        subtitleData.value = null
      }
    } else {
      subtitleData.value = null
    }
  }

  function setSubtitleData(data: SubtitleData) {
    subtitleData.value = data
  }

  function setCurrentTime(time: number) {
    currentTime.value = time
  }

  function setPlaying(playing: boolean) {
    isPlaying.value = playing
  }

  function getSubtitleAtTime(time: number): SubtitleEntry | null {
    if (!subtitleData.value) return null
    return SubtitleService.getSubtitleAtTime(subtitleData.value, time)
  }

  function getSubtitlesInRange(startTime: number, endTime: number): SubtitleEntry[] {
    if (!subtitleData.value) return []
    return SubtitleService.getSubtitlesInRange(subtitleData.value, startTime, endTime)
  }

  function exportSubtitles(): string {
    if (!subtitleData.value) return ''
    return SubtitleService.exportToText(subtitleData.value)
  }

  function clearSubtitles() {
    subtitleData.value = null
    subtitleText.value = ''
    currentTime.value = 0
    isPlaying.value = false
  }

  function loadFromSession() {
    try {
      const stored = sessionStorage.getItem('subtitleData')
      if (stored) {
        const data = JSON.parse(stored) as SubtitleData
        subtitleData.value = data
      }
    } catch (error) {
      console.error('Error loading subtitles from session:', error)
    }
  }

  function saveToSession() {
    if (subtitleData.value) {
      sessionStorage.setItem('subtitleData', JSON.stringify(subtitleData.value))
    }
  }

  return {
    // State
    subtitleData,
    subtitleText,
    currentTime,
    isPlaying,

    // Getters
    currentSubtitle,
    totalDuration,
    subtitleCount,
    hasSubtitles,

    // Actions
    setSubtitleText,
    parseSubtitles,
    setSubtitleData,
    setCurrentTime,
    setPlaying,
    getSubtitleAtTime,
    getSubtitlesInRange,
    exportSubtitles,
    clearSubtitles,
    loadFromSession,
    saveToSession,
  }
})