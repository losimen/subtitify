import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { SubtitleService } from '../services/subtitle.service'
import type { SubtitleData, SubtitleEntry } from '../types/subtitle'

export const useSubtitleStore = defineStore('subtitle', () => {
  // State
  const subtitleData = ref<SubtitleData | null>(null)
  const currentTime = ref(0)
  const isPlaying = ref(false)
  const isEditing = ref(false)
  const editingEntry = ref<SubtitleEntry | null>(null)
  const activeChunkIndex = ref(0)
  const videoDuration = ref(0)
  
  // Global validation state
  const hasTimelineExceedingChunks = ref(false)
  const hasTextChanges = ref(false)
  const isExporting = ref(false)

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

  const activeChunk = computed(() => {
    if (!subtitleData.value || activeChunkIndex.value >= subtitleData.value.entries.length) {
      return null
    }
    return subtitleData.value.entries[activeChunkIndex.value]
  })

  // Global validation computed properties
  const hasValidationIssues = computed(() => {
    return hasTimelineExceedingChunks.value || hasTextChanges.value
  })

  const canExport = computed(() => {
    return !hasValidationIssues.value && !isExporting.value
  })

  // Actions

  function setSubtitleData(data: SubtitleData) {
    subtitleData.value = data
  }

  function setCurrentTime(time: number) {
    currentTime.value = time
  }

  function setPlaying(playing: boolean) {
    isPlaying.value = playing
  }

  function setVideoDuration(duration: number) {
    videoDuration.value = duration
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
    // Initialize subtitle data if it doesn't exist
    if (!subtitleData.value) {
      subtitleData.value = {
        entries: [],
        totalDuration: videoDuration.value || 0
      }
    }

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

  // Active chunk management
  function setActiveChunkIndex(index: number) {
    if (subtitleData.value && index >= 0 && index < subtitleData.value.entries.length) {
      activeChunkIndex.value = index
    }
  }

  function setActiveChunkById(chunkId: string) {
    if (subtitleData.value) {
      const index = subtitleData.value.entries.findIndex(entry => entry.id === chunkId)
      if (index !== -1) {
        activeChunkIndex.value = index
      }
    }
  }

  function getChunkIndexById(chunkId: string): number {
    if (!subtitleData.value) return -1
    return subtitleData.value.entries.findIndex(entry => entry.id === chunkId)
  }

  // Validation functions
  function validateChunk(entry: SubtitleEntry): { isValid: boolean; errors: string[] } {
    const errors: string[] = []

    // Check if chunk exceeds video duration
    if (entry.endTime > videoDuration.value + 1) {
      errors.push(`Chunk ends at ${SubtitleService.secondsToTimeString(entry.endTime)} but video is only ${SubtitleService.secondsToTimeString(videoDuration.value)} long`)
    }

    // Check if chunk starts after video duration
    if (entry.startTime > videoDuration.value) {
      errors.push(`Chunk starts at ${SubtitleService.secondsToTimeString(entry.startTime)} but video is only ${SubtitleService.secondsToTimeString(videoDuration.value)} long`)
    }

    // Check if start time is negative
    if (entry.startTime < 0) {
      errors.push('Start time cannot be negative')
    }

    // Check if end time is before start time
    if (entry.endTime <= entry.startTime) {
      errors.push('End time must be after start time')
    }

    // Check for intersections with other chunks
    if (subtitleData.value) {
      const intersectingChunks = subtitleData.value.entries.filter(other =>
        other.id !== entry.id &&
        ((entry.startTime >= other.startTime && entry.startTime < other.endTime) ||
         (entry.endTime > other.startTime && entry.endTime <= other.endTime) ||
         (entry.startTime <= other.startTime && entry.endTime >= other.endTime))
      )

      if (intersectingChunks.length > 0) {
        errors.push('Chunk intersects with other chunks')
      }
    }

    return {
      isValid: errors.length === 0,
      errors
    }
  }

  function validateAllChunks(): { isValid: boolean; invalidChunks: string[]; errors: Record<string, string[]> } {
    if (!subtitleData.value) {
      return { isValid: true, invalidChunks: [], errors: {} }
    }

    const invalidChunks: string[] = []
    const errors: Record<string, string[]> = {}

    subtitleData.value.entries.forEach(entry => {
      const validation = validateChunk(entry)
      if (!validation.isValid) {
        invalidChunks.push(entry.id)
        errors[entry.id] = validation.errors
      }
    })

    return {
      isValid: invalidChunks.length === 0,
      invalidChunks,
      errors
    }
  }

  function getChunkValidationStatus(chunkId: string): { isValid: boolean; errors: string[] } {
    if (!subtitleData.value) {
      return { isValid: true, errors: [] }
    }

    const entry = subtitleData.value.entries.find(e => e.id === chunkId)
    if (!entry) {
      return { isValid: true, errors: [] }
    }

    return validateChunk(entry)
  }

  // Global validation state management
  function setHasTimelineExceedingChunks(hasExceeding: boolean) {
    hasTimelineExceedingChunks.value = hasExceeding
  }

  function setHasTextChanges(hasChanges: boolean) {
    hasTextChanges.value = hasChanges
  }

  function setExporting(exporting: boolean) {
    isExporting.value = exporting
  }

  function resetValidationState() {
    hasTimelineExceedingChunks.value = false
    hasTextChanges.value = false
  }

  return {
    // State
    subtitleData,
    currentTime,
    isPlaying,
    isEditing,
    editingEntry,
    activeChunkIndex,
    videoDuration,
    hasTimelineExceedingChunks,
    hasTextChanges,
    isExporting,

    // Getters
    currentSubtitle,
    totalDuration,
    subtitleCount,
    hasSubtitles,
    activeChunk,
    hasValidationIssues,
    canExport,

    // Actions
    setSubtitleData,
    setCurrentTime,
    setPlaying,
    setVideoDuration,
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

    // Active chunk management
    setActiveChunkIndex,
    setActiveChunkById,
    getChunkIndexById,

    // Validation functions
    validateChunk,
    validateAllChunks,
    getChunkValidationStatus,

    // Global validation state management
    setHasTimelineExceedingChunks,
    setHasTextChanges,
    setExporting,
    resetValidationState,
  }
})
