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
  const isEditing = ref(false)
  const editingEntry = ref<SubtitleEntry | null>(null)

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

  // Editing actions
  function startEditing(entry: SubtitleEntry) {
    isEditing.value = true
    editingEntry.value = { ...entry }
  }

  function stopEditing() {
    isEditing.value = false
    editingEntry.value = null
  }

  function updateEntry(updatedEntry: SubtitleEntry) {
    if (!subtitleData.value) return

    const index = subtitleData.value.entries.findIndex(entry => entry.id === updatedEntry.id)
    if (index !== -1) {
      subtitleData.value.entries[index] = updatedEntry
      // Update total duration if needed
      subtitleData.value.totalDuration = Math.max(
        subtitleData.value.totalDuration,
        ...subtitleData.value.entries.map(e => e.endTime)
      )
    }
  }

  function addEntry(newEntry: SubtitleEntry) {
    if (!subtitleData.value) return

    subtitleData.value.entries.push(newEntry)
    // Sort entries by start time
    subtitleData.value.entries.sort((a, b) => a.startTime - b.startTime)
    // Update total duration
    subtitleData.value.totalDuration = Math.max(
      subtitleData.value.totalDuration,
      ...subtitleData.value.entries.map(e => e.endTime)
    )
  }

  function removeEntry(entryId: string) {
    if (!subtitleData.value) return

    subtitleData.value.entries = subtitleData.value.entries.filter(entry => entry.id !== entryId)
    // Update total duration
    if (subtitleData.value.entries.length > 0) {
      subtitleData.value.totalDuration = Math.max(
        ...subtitleData.value.entries.map(e => e.endTime)
      )
    } else {
      subtitleData.value.totalDuration = 0
    }
  }

  function generateNewId(): string {
    if (!subtitleData.value) return `subtitle-${Date.now()}`
    const existingIds = subtitleData.value.entries.map(e => parseInt(e.id.split('-')[1])).filter(id => !isNaN(id))
    const maxId = existingIds.length > 0 ? Math.max(...existingIds) : 0
    return `subtitle-${maxId + 1}`
  }

  return {
    // State
    subtitleData,
    subtitleText,
    currentTime,
    isPlaying,
    isEditing,
    editingEntry,

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

    // Editing actions
    startEditing,
    stopEditing,
    updateEntry,
    addEntry,
    removeEntry,
    generateNewId,
  }
})