<template>
  <div class="chunked-video-editor">
    <div class="editor-header">
      <div class="header-left">
        <h2>Chunked Video Editor</h2>
      </div>
      <div class="header-actions">
        <button
          @click="handleExportClick"
          class="export-button"
          :disabled="!subtitleStore.canExport"
          :title="subtitleStore.hasValidationIssues ? 'Cannot export: Please fix validation issues first' : 'Export video with subtitles'"
        >
          📤 Export
        </button>
        <button @click="goBack" class="back-button">
          ← Back to Video
        </button>
      </div>
    </div>

    <div class="editor-content">
      <!-- Chunk Navigation -->
      <div class="chunk-navigation">
        <div class="chunk-list">
          <div
            v-for="(entry, index) in subtitleStore.subtitleData?.entries || []"
            :key="entry.id"
            class="chunk-nav-item"
            :class="{
              'active': currentChunkIndex === index,
              'invalid': !subtitleStore.getChunkValidationStatus(entry.id).isValid
            }"
          >
            <div class="chunk-content" @click="selectChunk(index)">
              <div class="chunk-number">{{ index + 1 }}</div>
              <div class="chunk-timing">{{ entry.startTimeFormatted }} - {{ entry.endTimeFormatted }}</div>
              <div class="chunk-text-preview">{{ entry.text.substring(0, 30) }}{{ entry.text.length > 30 ? '...' : '' }}</div>
            </div>
            <button
              @click.stop="deleteChunk(entry.id, index)"
              class="delete-chunk-btn"
              :class="{ 'disabled': subtitleStore.subtitleCount <= 1 }"
              :disabled="subtitleStore.subtitleCount <= 1"
              :title="subtitleStore.subtitleCount <= 1 ? 'Cannot delete the last chunk' : 'Delete chunk'"
            >
              🗑️
            </button>
          </div>
        </div>
      </div>

      <!-- Video Player Area -->
      <div class="video-player-area">
        <div class="video-container">
          <video
            ref="videoPlayer"
            :src="videoUrl"
            class="video-player"
            @loadedmetadata="onVideoLoaded"
            @timeupdate="onTimeUpdate"
            @play="onPlay"
            @pause="onPause"
          >
            Your browser does not support the video tag.
          </video>

          <!-- Current Subtitle Display -->
          <div v-if="currentSubtitle" class="subtitle-display">
            <p class="subtitle-text">{{ currentSubtitle.text }}</p>
            <div class="subtitle-timing">
              {{ currentSubtitle.startTimeFormatted }} - {{ currentSubtitle.endTimeFormatted }}
            </div>
          </div>
        </div>

        <!-- Video Controls -->
        <div class="video-controls">
          <button @click="playChunk" class="play-chunk-btn" :disabled="!activeChunk">
            ▶️ Play This Chunk
          </button>
          <button @click="playPause" class="play-pause-btn">
            {{ isPlaying ? '⏸️ Pause' : '▶️ Play' }}
          </button>
          <div class="time-display">
            {{ formatTime(currentTime) }} / {{ formatTime(subtitleStore.totalDuration) }}
          </div>
        </div>

        <!-- Chunk Info and Editing -->
        <div class="chunk-info-editing">
          <!-- Chunk Info Section (1/3) -->
          <div class="chunk-info">
            <h3>Chunk {{ currentChunkIndex + 1 }} of {{ subtitleStore.subtitleCount }}</h3>
            <div class="chunk-details">
              <div class="detail-item">
                <label>Start Time:</label>
                <span>{{ activeChunk?.startTimeFormatted || '00:00' }}</span>
              </div>
              <div class="detail-item">
                <label>End Time:</label>
                <span>{{ activeChunk?.endTimeFormatted || '00:00' }}</span>
              </div>
              <div class="detail-item">
                <label>Duration:</label>
                <span>{{ activeChunk ? formatDuration(activeChunk.endTime - activeChunk.startTime) : '00:00' }}</span>
              </div>
            </div>
          </div>

          <!-- Text Editing Section -->
          <div class="chunk-text-editing">
            <div class="text-editing-header">
              <h4>Edit Chunk Text</h4>
              <div class="styling-controls">
                <div class="styling-group">
                  <label>Size</label>
                  <select v-model="textStyling.size" @change="onStylingChange" class="styling-select">
                    <option value="small">Small</option>
                    <option value="medium">Medium</option>
                    <option value="large">Large</option>
                  </select>
                </div>
                <div class="styling-group">
                  <label>Color</label>
                  <select v-model="textStyling.color" @change="onStylingChange" class="styling-select">
                    <option value="white">⚪ White</option>
                    <option value="black">⚫ Black</option>
                    <option value="red">🔴 Red</option>
                    <option value="blue">🔵 Blue</option>
                    <option value="green">🟢 Green</option>
                    <option value="yellow">🟡 Yellow</option>
                    <option value="orange">🟠 Orange</option>
                    <option value="purple">🟣 Purple</option>
                    <option value="cyan">🔵 Cyan</option>
                    <option value="magenta">🟣 Magenta</option>
                  </select>
                </div>
                <div class="styling-group">
                  <label>Position</label>
                  <select v-model="textStyling.position" @change="onStylingChange" class="styling-select">
                    <option value="top">👆 Top</option>
                    <option value="center">🫵 Center</option>
                    <option value="bottom">👇 Bottom</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="text-editor-container">
              <div class="text-editor-wrapper">
                <textarea
                  v-model="editingText"
                  @input="onTextChange"
                  class="text-editor"
                  placeholder="Enter chunk text..."
                  rows="2"
                ></textarea>
                <div class="text-editor-overlay">
                  <button @click="saveTextChanges" class="save-text-btn" :disabled="!hasTextChanges" title="Save Changes">
                    ✓
                  </button>
                  <button @click="resetTextChanges" class="reset-text-btn" :disabled="!hasTextChanges" title="Reset">
                    ↺
                  </button>
                  <span v-if="hasTextChanges" class="unsaved-indicator">●</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Timeline Editor - Full Width Bottom -->
    <div class="timeline-editor-container">
      <TimelineEditor :video-player="videoPlayer" />
    </div>

    <div class="editor-footer">
      <div class="subtitle-stats">
        <span>{{ subtitleStore.subtitleCount }} chunks</span>
        <span>Total Duration: {{ formatDuration(subtitleStore.totalDuration) }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, onUnmounted, computed, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useSubtitleStore } from '../stores/subtitle'
import { SubtitleService } from '../services/subtitle.service'
import TimelineEditor from './TimelineEditor.vue'
import type { SubtitleEntry } from '../types/subtitle'

const subtitleStore = useSubtitleStore()
const videoPlayer = ref<HTMLVideoElement | null>(null)
const videoUrl = ref<string>('')

// Text editing state
const editingText = ref<string>('')
const originalText = ref<string>('')
const hasTextChanges = ref<boolean>(false)

// Text styling state
const textStyling = reactive({
  size: 'medium',
  color: 'white',
  position: 'bottom'
})

// Computed properties
const currentSubtitle = computed(() => subtitleStore.currentSubtitle)
const activeChunk = computed(() => subtitleStore.activeChunk)
const currentChunkIndex = computed(() => subtitleStore.activeChunkIndex)

const currentTime = computed(() => subtitleStore.currentTime)
const isPlaying = computed(() => subtitleStore.isPlaying)

onMounted(() => {
  // Load subtitles from session storage
  subtitleStore.loadFromSession()

  // Get the file from sessionStorage
  const storedFile = sessionStorage.getItem('uploadedFile')
  if (storedFile) {
    try {
      const fileData = JSON.parse(storedFile)
      if (fileData.url && fileData.url.startsWith('data:')) {
        videoUrl.value = fileData.url
      } else if (fileData.url) {
        videoUrl.value = fileData.url
      }
    } catch (error) {
      console.error('Error parsing stored file data:', error)
      router.visit('/')
    }
  } else {
    router.visit('/')
  }

  // Initialize editing text
  updateEditingText()

  // Add keyboard event listener for delete key
  const handleKeyDown = (event: KeyboardEvent) => {
    if (event.key === 'Delete' && activeChunk.value && subtitleStore.subtitleCount > 1) {
      event.preventDefault()
      deleteChunk(activeChunk.value.id, currentChunkIndex.value)
    }
  }

  document.addEventListener('keydown', handleKeyDown)

  // Cleanup on unmount
  onUnmounted(() => {
    document.removeEventListener('keydown', handleKeyDown)
  })
})

// Watch for chunk changes to update video position and text
watch(() => subtitleStore.activeChunkIndex, (newIndex) => {
  if (activeChunk.value && videoPlayer.value) {
    videoPlayer.value.currentTime = activeChunk.value.startTime
  }
  // Update editing text when chunk changes
  updateEditingText()
})

// Watch for active chunk changes to update editing text
watch(() => activeChunk.value, () => {
  updateEditingText()
})


// Text editing functions
const updateEditingText = () => {
  if (activeChunk.value) {
    editingText.value = activeChunk.value.text
    originalText.value = activeChunk.value.text
    hasTextChanges.value = false
    // Update global state
    subtitleStore.setHasTextChanges(false)

    // Load styling from current subtitle entry
    textStyling.size = activeChunk.value.styling.size
    textStyling.color = activeChunk.value.styling.color
    textStyling.position = activeChunk.value.styling.position
  } else {
    editingText.value = ''
    originalText.value = ''
    hasTextChanges.value = false
    // Update global state
    subtitleStore.setHasTextChanges(false)
  }
}

const onTextChange = () => {
  hasTextChanges.value = editingText.value !== originalText.value
  // Update global state
  subtitleStore.setHasTextChanges(hasTextChanges.value)
}

const saveTextChanges = () => {
  if (!activeChunk.value || !hasTextChanges.value) return

  const updatedEntry: SubtitleEntry = {
    ...activeChunk.value,
    text: editingText.value.trim(),
    styling: {
      size: textStyling.size,
      color: textStyling.color,
      position: textStyling.position
    }
  }

  subtitleStore.updateEntry(updatedEntry)
  subtitleStore.saveToSession()

  // Update original text to reflect saved changes
  originalText.value = editingText.value
  hasTextChanges.value = false
  // Update global state
  subtitleStore.setHasTextChanges(false)
}

const resetTextChanges = () => {
  editingText.value = originalText.value
  hasTextChanges.value = false
  // Update global state
  subtitleStore.setHasTextChanges(false)

  // Reset styling to original values
  if (activeChunk.value) {
    textStyling.size = activeChunk.value.styling.size
    textStyling.color = activeChunk.value.styling.color
    textStyling.position = activeChunk.value.styling.position
  }
}

const onStylingChange = () => {
  // Mark that there are changes when styling is modified
  hasTextChanges.value = true
  // Update global state
  subtitleStore.setHasTextChanges(true)
}

const selectChunk = (index: number) => {
  subtitleStore.setActiveChunkIndex(index)
  if (videoPlayer.value && activeChunk.value) {
    videoPlayer.value.currentTime = activeChunk.value.startTime
  }
}

const deleteChunk = (entryId: string, index: number) => {
  // Prevent deleting the last chunk
  if (subtitleStore.subtitleCount <= 1) {
    alert('Cannot delete the last chunk. You need at least one subtitle chunk.')
    return
  }

  // Show confirmation dialog
  if (confirm(`Are you sure you want to delete chunk ${index + 1}?\n\n"${subtitleStore.subtitleData?.entries[index]?.text.substring(0, 50)}${subtitleStore.subtitleData?.entries[index]?.text.length > 50 ? '...' : ''}"\n\nThis action cannot be undone.`)) {
    // Remove the entry from the store
    subtitleStore.removeEntry(entryId)

    // Save changes to session
    subtitleStore.saveToSession()

    // Adjust active chunk index if needed
    if (currentChunkIndex.value >= index && currentChunkIndex.value > 0) {
      subtitleStore.setActiveChunkIndex(currentChunkIndex.value - 1)
    } else if (currentChunkIndex.value >= subtitleStore.subtitleCount) {
      subtitleStore.setActiveChunkIndex(Math.max(0, subtitleStore.subtitleCount - 1))
    }

    // Update editing text if we're editing the deleted chunk
    updateEditingText()
  }
}

const playChunk = () => {
  if (activeChunk.value && videoPlayer.value) {
    videoPlayer.value.currentTime = activeChunk.value.startTime
    videoPlayer.value.play()
  }
}

const playPause = () => {
  if (videoPlayer.value) {
    if (videoPlayer.value.paused) {
      videoPlayer.value.play()
    } else {
      videoPlayer.value.pause()
    }
  }
}

const onVideoLoaded = () => {
  if (videoPlayer.value) {
    subtitleStore.setVideoDuration(videoPlayer.value.duration)
  }
}

const onTimeUpdate = () => {
  if (videoPlayer.value) {
    subtitleStore.setCurrentTime(videoPlayer.value.currentTime)

    // Auto-advance to next chunk if current time exceeds current chunk
    if (currentSubtitle.value && videoPlayer.value.currentTime > currentSubtitle.value.endTime) {
      if (currentChunkIndex.value < (subtitleStore.subtitleData?.entries.length || 0) - 1) {
        currentChunkIndex.value++
      }
    }
  }
}

const onPlay = () => {
  subtitleStore.setPlaying(true)
}

const onPause = () => {
  subtitleStore.setPlaying(false)
}

const goBack = () => {
  subtitleStore.stopEditing()
  router.visit('/subtitle')
}

const handleExportClick = (event: Event) => {
  // Get the button element to check its disabled state
  const button = event.target as HTMLButtonElement

  // If the button is disabled, don't execute the export
  if (button.disabled) {
    event.preventDefault()
    event.stopPropagation()
    return
  }

  // Double-check the store state as well
  if (!subtitleStore.canExport || subtitleStore.hasValidationIssues) {
    event.preventDefault()
    event.stopPropagation()
    return
  }

  exportSubtitles()
}

const exportSubtitles = async () => {
  // Prevent export if there are validation issues - double check
  if (!subtitleStore.canExport || subtitleStore.hasValidationIssues) {
    alert('Cannot export: Please fix validation issues first (timeline exceeding chunks or unsaved text changes)')
    return
  }

  try {
    // Set exporting state
    subtitleStore.setExporting(true)

    const storedFile = sessionStorage.getItem('uploadedFile')
    let fileData = null

    if (storedFile) {
      try {
        fileData = JSON.parse(storedFile)
      } catch (error) {
        console.error('Error parsing stored file data:', error)
      }
    }

    if (!fileData) {
      alert('No video file found. Please upload a video first.')
      return
    }

    // Show loading state
    const exportButton = document.querySelector('.export-button')
    if (exportButton) {
      exportButton.textContent = '⏳ Processing...'
    }

    const exportData = {
      file: fileData,
      subtitles: subtitleStore.subtitleData
    }

    const response = await fetch('/api/save', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
      },
      body: JSON.stringify(exportData)
    })

    if (response.ok) {
      // Handle file download
      const blob = await response.blob()
      const url = window.URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `subtitled_video_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.mp4`
      document.body.appendChild(a)
      a.click()
      document.body.removeChild(a)
      window.URL.revokeObjectURL(url)
    } else {
      const errorData = await response.json()
      console.error('Export failed:', errorData)
      alert(`Export failed: ${errorData.error || response.statusText}`)
    }
  } catch (error) {
    console.error('Export error:', error)
    alert('Export failed. Please try again.')
  } finally {
    // Reset exporting state
    subtitleStore.setExporting(false)

    // Reset button state
    const exportButton = document.querySelector('.export-button')
    if (exportButton) {
      exportButton.textContent = '📤 Export'
    }
  }
}

const formatTime = (seconds: number): string => {
  return SubtitleService.secondsToTimeString(seconds)
}

const formatDuration = (seconds: number): string => {
  return SubtitleService.secondsToTimeString(seconds)
}

</script>

<style scoped>
.chunked-video-editor {
  min-height: 100vh;
  background-color: black;
  color: white;
  padding: 10px;
  overflow: hidden;
}

.editor-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 10px;
  padding-bottom: 8px;
  border-bottom: 2px solid #333;
}

.header-left {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.editor-header h2 {
  font-size: 18px;
  margin: 0;
}

.validation-warning {
  font-size: 12px;
  color: #ffc107;
  background-color: rgba(255, 193, 7, 0.1);
  border: 1px solid #ffc107;
  border-radius: 4px;
  padding: 4px 8px;
  font-weight: 500;
  min-height: 20px;
  transition: opacity 0.2s ease;
}

.validation-warning.hidden {
  opacity: 0;
  visibility: hidden;
}

.header-actions {
  display: flex;
  gap: 15px;
}

.add-button, .export-button, .back-button {
  padding: 5px 10px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-weight: 600;
  font-size: 11px;
  transition: all 0.3s;
}

.add-button {
  background-color: #28a745;
  color: white;
}

.add-button:hover {
  background-color: #218838;
}

.export-button {
  background-color: #007bff;
  color: white;
}

.export-button:hover:not(:disabled) {
  background-color: #0056b3;
}

.export-button:disabled {
  background-color: #555;
  cursor: not-allowed;
  opacity: 0.6;
}

.back-button {
  background-color: #333;
  color: white;
  border: 2px solid #555;
}

.back-button:hover {
  background-color: #444;
  border-color: #777;
}

.editor-content {
  display: grid;
  grid-template-columns: 200px 1fr;
  gap: 10px;
  margin-bottom: 2px;
}

.chunk-navigation {
  background-color: #222;
  border-radius: 6px;
  padding: 8px;
  overflow-y: auto;
}

.chunk-list {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.chunk-nav-item {
  padding: 6px;
  background-color: #333;
  border: 1px solid #444;
  border-radius: 4px;
  transition: all 0.3s;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.chunk-content {
  flex: 1;
  cursor: pointer;
  min-width: 0; /* Allow content to shrink */
}

.chunk-nav-item:hover {
  border-color: #666;
  background-color: #3a3a3a;
}

.chunk-nav-item.active {
  border-color: #007bff;
  background-color: #1a1a2e;
}

.chunk-nav-item.editing {
  border-color: #ffc107;
  background-color: #2d1b00;
}

.chunk-nav-item.invalid {
  border-color: #dc3545 !important;
  background-color: rgba(220, 53, 69, 0.2) !important;
}

.chunk-nav-item.invalid:hover {
  background-color: rgba(220, 53, 69, 0.3) !important;
}

.chunk-nav-item.invalid.active {
  border-color: #ffc107 !important;
  background-color: rgba(220, 53, 69, 0.3) !important;
}

.chunk-number {
  font-size: 11px;
  color: #007bff;
  font-weight: 600;
  margin-bottom: 2px;
}

.chunk-timing {
  font-size: 9px;
  color: #888;
  margin-bottom: 2px;
}

.chunk-text-preview {
  font-size: 10px;
  line-height: 1.1;
  color: #ccc;
}

.delete-chunk-btn {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 14px;
  padding: 4px;
  border-radius: 3px;
  transition: all 0.3s;
  opacity: 0.6;
  flex-shrink: 0;
}

.delete-chunk-btn:hover {
  opacity: 1;
  background-color: rgba(220, 53, 69, 0.2);
  transform: scale(1.1);
}

.delete-chunk-btn:active {
  transform: scale(0.95);
}

.delete-chunk-btn.disabled {
  opacity: 0.3;
  cursor: not-allowed;
}

.delete-chunk-btn.disabled:hover {
  opacity: 0.3;
  background-color: transparent;
  transform: none;
}

.video-player-area {
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex: 1;
}

.video-container {
  position: relative;
  background-color: #000;
  border-radius: 8px;
  overflow: hidden;
  height: auto;
  min-height: 200px;
  max-height: 450px;
}

.video-player {
  width: 100%;
  height: auto;
  max-height: 400px;
  object-fit: contain;
}

.subtitle-display {
  position: absolute;
  bottom: 20px;
  left: 50%;
  transform: translateX(-50%);
  background-color: rgba(0, 0, 0, 0.8);
  padding: 15px 25px;
  border-radius: 8px;
  border: 2px solid #007bff;
  max-width: 90%;
  text-align: center;
  backdrop-filter: blur(10px);
}

.subtitle-text {
  color: white;
  font-size: 18px;
  font-weight: 600;
  margin: 0 0 8px 0;
  line-height: 1.4;
  text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.8);
}

.subtitle-timing {
  color: #007bff;
  font-size: 14px;
  font-weight: 500;
  opacity: 0.9;
}

.video-controls {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px;
  background-color: #222;
  border-radius: 6px;
}

.play-chunk-btn, .play-pause-btn {
  padding: 6px 10px;
  background-color: #007bff;
  color: white;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-weight: 600;
  font-size: 12px;
  transition: background-color 0.3s;
}

.play-chunk-btn:hover:not(:disabled), .play-pause-btn:hover {
  background-color: #0056b3;
}

.play-chunk-btn:disabled {
  background-color: #555;
  cursor: not-allowed;
}

.time-display {
  margin-left: auto;
  font-family: monospace;
  color: #888;
}

.chunk-info-editing {
  display: flex;
  gap: 10px;
  height: auto;
  max-height: 100px;
}

.chunk-info {
  background-color: #222;
  padding: 6px;
  border-radius: 6px;
  flex: 0 0 25%; /* Reduced from 30% to 25% */
  min-width: 0;
  max-height: 100px; /* Reduced height */
}

.chunk-info h3 {
  margin: 0 0 4px 0;
  color: #007bff;
  font-size: 12px;
}

.chunk-details {
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.detail-item {
  display: flex;
  justify-content: space-between;
  font-size: 11px;
}

.detail-item label {
  color: #888;
}

.detail-item span {
  color: white;
  font-weight: 600;
}

.chunk-text-editing {
  background-color: #222;
  padding: 6px;
  border-radius: 6px;
  flex: 1; /* Takes remaining space */
  display: flex;
  flex-direction: column;
  max-height: 100px; /* Reduced height */
}

.text-editing-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 4px;
}

.text-editing-header h4 {
  margin: 0;
  color: #007bff;
  font-size: 12px;
}

.text-editor-container {
  display: flex;
  flex-direction: column;
  flex: 1;
}

.text-editor-wrapper {
  position: relative;
  flex: 1;
}

.text-editor {
  width: 100%;
  height: auto;
  background-color: #333;
  color: white;
  border: 2px solid #555;
  border-radius: 6px;
  padding: 6px 40px 6px 6px; /* Right padding for buttons */
  font-size: 12px;
  font-family: inherit;
  resize: none;
  min-height: 30px;
  outline: none;
  transition: border-color 0.3s;
  box-sizing: border-box;
}

.text-editor:focus {
  border-color: #007bff;
}

.text-editor-overlay {
  position: absolute;
  top: 6px;
  right: 6px;
  display: flex;
  align-items: center;
  gap: 4px;
  pointer-events: none;
}

.text-editor-overlay button {
  pointer-events: auto;
}

.save-text-btn, .reset-text-btn {
  width: 20px;
  height: 20px;
  border: none;
  border-radius: 3px;
  cursor: pointer;
  font-weight: 600;
  font-size: 12px;
  transition: all 0.3s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.save-text-btn {
  background-color: #28a745;
  color: white;
}

.save-text-btn:hover:not(:disabled) {
  background-color: #218838;
}

.save-text-btn:disabled {
  background-color: #555;
  cursor: not-allowed;
  opacity: 0.5;
}

.reset-text-btn {
  background-color: #6c757d;
  color: white;
}

.reset-text-btn:hover:not(:disabled) {
  background-color: #5a6268;
}

.reset-text-btn:disabled {
  background-color: #555;
  cursor: not-allowed;
  opacity: 0.5;
}

.unsaved-indicator {
  color: #ffc107;
  font-size: 12px;
  font-weight: 600;
}

.styling-controls {
  display: flex;
  gap: 8px;
  align-items: center;
}

.styling-group {
  display: flex;
  flex-direction: column;
  gap: 2px;
  align-items: center;
}

.styling-group label {
  color: #888;
  font-size: 9px;
  font-weight: 600;
  white-space: nowrap;
}

.styling-select {
  background-color: #333;
  color: white;
  border: 1px solid #555;
  border-radius: 4px;
  padding: 2px 4px;
  font-size: 10px;
  outline: none;
  transition: border-color 0.3s;
  min-width: 60px;
}

.styling-select:focus {
  border-color: #007bff;
}

.styling-select:hover {
  border-color: #666;
}

.timeline-editor-container {
  background-color: #222;
  border-top: 2px solid #333;
  margin-top: 0px;
}

.editor-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 20px;
  border-top: 2px solid #333;
}

.subtitle-stats {
  display: flex;
  gap: 20px;
  color: #888;
  font-size: 14px;
}

@media (max-width: 1200px) {
  .editor-content {
    grid-template-columns: 250px 1fr;
  }
}

@media (max-width: 768px) {
  .editor-content {
    grid-template-columns: 1fr;
    gap: 15px;
  }

  .header-actions {
    flex-direction: column;
    gap: 10px;
  }

  .video-controls {
    flex-wrap: wrap;
  }

  .chunk-info-editing {
    flex-direction: column;
    gap: 8px;
  }

  .chunk-info {
    flex: none;
    height: auto;
  }

  .chunk-text-editing {
    flex: none;
    height: 200px;
  }

  .chunk-nav-item {
    padding: 8px;
    gap: 6px;
  }

  .delete-chunk-btn {
    font-size: 16px;
    padding: 6px;
  }

  .text-editing-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }

  .styling-controls {
    flex-direction: row;
    gap: 12px;
    flex-wrap: wrap;
  }

  .styling-group {
    flex-direction: row;
    align-items: center;
    gap: 4px;
  }

  .styling-group label {
    font-size: 10px;
  }

  .text-editor {
    min-height: 100px;
  }
}
</style>
