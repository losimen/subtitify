<template>
  <div class="chunked-video-editor">
    <div class="editor-header">
      <h2>Chunked Video Editor</h2>
      <div class="header-actions">
        <button @click="addNewChunk" class="add-button">
          + Add New Chunk
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
            @click="selectChunk(index)"
          >
            <div class="chunk-number">{{ index + 1 }}</div>
            <div class="chunk-timing">{{ entry.startTimeFormatted }} - {{ entry.endTimeFormatted }}</div>
            <div class="chunk-text-preview">{{ entry.text.substring(0, 30) }}{{ entry.text.length > 30 ? '...' : '' }}</div>
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
          <button @click="playChunk" class="play-chunk-btn" :disabled="!currentSubtitle">
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
                <span>{{ currentSubtitle?.startTimeFormatted || '00:00' }}</span>
              </div>
              <div class="detail-item">
                <label>End Time:</label>
                <span>{{ currentSubtitle?.endTimeFormatted || '00:00' }}</span>
              </div>
              <div class="detail-item">
                <label>Duration:</label>
                <span>{{ currentSubtitle ? formatDuration(currentSubtitle.endTime - currentSubtitle.startTime) : '00:00' }}</span>
              </div>
            </div>
          </div>

          <!-- Text Editing Section -->
          <div class="chunk-text-editing">
            <div class="text-editing-header">
              <h4>Edit Chunk Text</h4>
              <div class="styling-controls">
                <div class="styling-group">
                  <select v-model="textStyling.size" @change="onStylingChange" class="styling-select">
                    <option value="small">Small</option>
                    <option value="medium">Medium</option>
                    <option value="large">Large</option>
                  </select>
                </div>
                <div class="styling-group">
                  <select v-model="textStyling.color" @change="onStylingChange" class="styling-select">
                    <option value="white">White</option>
                    <option value="black">Black</option>
                    <option value="red">Red</option>
                  </select>
                </div>
                <div class="styling-group">
                  <select v-model="textStyling.position" @change="onStylingChange" class="styling-select">
                    <option value="top">Top</option>
                    <option value="center">Center</option>
                    <option value="bottom">Bottom</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="text-editor-container">
              <div class="text-editor-wrapper">
                <textarea
                  v-model="editingText"
                  @input="onTextChange"
                  @blur="saveTextChanges"
                  class="text-editor"
                  placeholder="Enter chunk text..."
                  rows="3"
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
import { ref, reactive, onMounted, computed, watch } from 'vue'
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
const currentSubtitle = computed(() => subtitleStore.activeChunk)
const currentChunkIndex = computed(() => subtitleStore.activeChunkIndex)

const currentTime = computed(() => subtitleStore.currentTime)
const isPlaying = computed(() => subtitleStore.isPlaying)

onMounted(() => {
  // Load subtitles from session storage
  subtitleStore.loadFromSession()

  // Check if we have subtitles, if not redirect to home
  if (!subtitleStore.hasSubtitles) {
    router.visit('/')
    return
  }

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
})

// Watch for chunk changes to update video position and text
watch(() => subtitleStore.activeChunkIndex, (newIndex) => {
  if (currentSubtitle.value && videoPlayer.value) {
    videoPlayer.value.currentTime = currentSubtitle.value.startTime
  }
  // Update editing text when chunk changes
  updateEditingText()
})

// Watch for current subtitle changes to update editing text
watch(() => currentSubtitle.value, () => {
  updateEditingText()
})

// Text editing functions
const updateEditingText = () => {
  if (currentSubtitle.value) {
    editingText.value = currentSubtitle.value.text
    originalText.value = currentSubtitle.value.text
    hasTextChanges.value = false
    
    // Load styling from current subtitle entry
    textStyling.size = currentSubtitle.value.styling.size
    textStyling.color = currentSubtitle.value.styling.color
    textStyling.position = currentSubtitle.value.styling.position
  } else {
    editingText.value = ''
    originalText.value = ''
    hasTextChanges.value = false
  }
}

const onTextChange = () => {
  hasTextChanges.value = editingText.value !== originalText.value
}

const saveTextChanges = () => {
  if (!currentSubtitle.value || !hasTextChanges.value) return

  const updatedEntry: SubtitleEntry = {
    ...currentSubtitle.value,
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
}

const resetTextChanges = () => {
  editingText.value = originalText.value
  hasTextChanges.value = false
  
  // Reset styling to original values
  if (currentSubtitle.value) {
    textStyling.size = currentSubtitle.value.styling.size
    textStyling.color = currentSubtitle.value.styling.color
    textStyling.position = currentSubtitle.value.styling.position
  }
}

const onStylingChange = () => {
  // Mark that there are changes when styling is modified
  hasTextChanges.value = true
  console.log('Text styling changed:', textStyling)
}

const selectChunk = (index: number) => {
  subtitleStore.setActiveChunkIndex(index)
  if (videoPlayer.value && currentSubtitle.value) {
    videoPlayer.value.currentTime = currentSubtitle.value.startTime
  }
}

const playChunk = () => {
  if (currentSubtitle.value && videoPlayer.value) {
    videoPlayer.value.currentTime = currentSubtitle.value.startTime
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
  console.log('Video loaded successfully')
  if (videoPlayer.value) {
    subtitleStore.setVideoDuration(videoPlayer.value.duration)
    console.log('Video duration set to:', videoPlayer.value.duration, 'seconds')
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


const exportSubtitles = () => {
  const validation = subtitleStore.validateAllChunks()

  if (!validation.isValid) {
    const errorMessage = `Cannot export subtitles. Please fix the following issues:\n\n${validation.invalidChunks.map(chunkId => {
      const chunkIndex = subtitleStore.getChunkIndexById(chunkId)
      const errors = validation.errors[chunkId] || []
      return `Chunk ${chunkIndex + 1}: ${errors.join(', ')}`
    }).join('\n')}`

    alert(errorMessage)
    return
  }

  const exportedText = subtitleStore.exportSubtitles()
  const blob = new Blob([exportedText], { type: 'text/plain' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = 'subtitles.txt'
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  URL.revokeObjectURL(url)
}

const goBack = () => {
  subtitleStore.stopEditing()
  router.visit('/subtitle')
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
  align-items: center;
  margin-bottom: 10px;
  padding-bottom: 8px;
  border-bottom: 2px solid #333;
}

.editor-header h2 {
  font-size: 18px;
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 15px;
}

.add-button, .back-button {
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
  cursor: pointer;
  transition: all 0.3s;
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
