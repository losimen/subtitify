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

        <!-- Chunk Info -->
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
})

// Watch for chunk changes to update video position
watch(() => subtitleStore.activeChunkIndex, (newIndex) => {
  if (currentSubtitle.value && videoPlayer.value) {
    videoPlayer.value.currentTime = currentSubtitle.value.startTime
  }
})

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
  height: 100vh;
  background-color: black;
  color: white;
  padding: 10px;
  padding-bottom: 200px; /* Reduced space for fixed timeline editor */
  overflow: hidden;
}

.editor-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
  padding-bottom: 10px;
  border-bottom: 2px solid #333;
}

.editor-header h2 {
  font-size: 20px;
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 15px;
}

.add-button, .back-button {
  padding: 6px 12px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-weight: 600;
  font-size: 12px;
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
  grid-template-columns: 250px 1fr;
  gap: 15px;
  margin-bottom: 10px;
  height: calc(100vh - 200px);
}

.chunk-navigation {
  background-color: #222;
  border-radius: 6px;
  padding: 10px;
  overflow-y: auto;
}

.chunk-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.chunk-nav-item {
  padding: 8px;
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
  font-size: 12px;
  color: #007bff;
  font-weight: 600;
  margin-bottom: 3px;
}

.chunk-timing {
  font-size: 10px;
  color: #888;
  margin-bottom: 3px;
}

.chunk-text-preview {
  font-size: 11px;
  line-height: 1.2;
  color: #ccc;
}

.video-player-area {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.video-container {
  position: relative;
  background-color: #000;
  border-radius: 8px;
  overflow: hidden;
}

.video-player {
  width: 100%;
  height: auto;
  max-height: 400px;
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

.chunk-info {
  background-color: #222;
  padding: 8px;
  border-radius: 6px;
}

.chunk-info h3 {
  margin: 0 0 8px 0;
  color: #007bff;
  font-size: 14px;
}

.chunk-details {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.detail-item {
  display: flex;
  justify-content: space-between;
  font-size: 12px;
}

.detail-item label {
  color: #888;
}

.detail-item span {
  color: white;
  font-weight: 600;
}

.timeline-editor-container {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background-color: #222;
  border-top: 2px solid #333;
  z-index: 1000;
  height: 180px;
  overflow-y: auto;
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

  .chunked-video-editor {
    padding-bottom: 250px; /* Reduced space for mobile timeline */
  }
  
  .timeline-editor-container {
    height: 200px; /* Fixed height on mobile */
  }
}
</style>
