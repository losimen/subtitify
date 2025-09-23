<template>
  <div class="creativity-subtitle">
    <div class="video-container">
      <video ref="videoPlayer" :src="videoUrl" controls class="video-player" @loadedmetadata="onVideoLoaded"
        @timeupdate="onTimeUpdate" @play="onPlay" @pause="onPause">
        Your browser does not support the video tag.
      </video>

      <!-- Subtitle Display -->
      <div v-if="subtitleStore.currentSubtitle" class="subtitle-display">
        <p class="subtitle-text">{{ subtitleStore.currentSubtitle.text }}</p>
        <div class="subtitle-timing">
          {{ subtitleStore.currentSubtitle.startTimeFormatted }} - {{ subtitleStore.currentSubtitle.endTimeFormatted }}
        </div>
      </div>
    </div>

    <div class="controls">
      <div class="control-buttons">
        <button @click="goBack" class="back-button">
          ← Back to Upload
        </button>
        <button @click="editSubtitles" class="edit-button" v-if="subtitleStore.hasSubtitles">
          ✏️ Edit Subtitles
        </button>
      </div>
      <div class="file-info">
        <span class="file-name">{{ fileName }}</span>
        <span class="file-size">{{ fileSize }}</span>
        <div v-if="subtitleStore.hasSubtitles" class="subtitle-info">
          <span>{{ subtitleStore.subtitleCount }} subtitles loaded</span>
          <span>Duration: {{ formatDuration(subtitleStore.totalDuration) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { useSubtitleStore } from '../stores/subtitle'
import { SubtitleService } from '../services/subtitle.service'

const videoPlayer = ref<HTMLVideoElement | null>(null)
const videoUrl = ref<string>('')
const fileName = ref<string>('')
const fileSize = ref<string>('')

// Use the subtitle store
const subtitleStore = useSubtitleStore()

// Get the uploaded file from the previous route
onMounted(() => {
  // Load subtitles from session storage
  subtitleStore.loadFromSession()

  // Get the file from sessionStorage or route state
  const storedFile = sessionStorage.getItem('uploadedFile')
  if (storedFile) {
    try {
      const fileData = JSON.parse(storedFile)

      // Check if it's base64 data (new format) or blob URL (old format)
      if (fileData.url && fileData.url.startsWith('data:')) {
        // It's base64 data, use it directly
        videoUrl.value = fileData.url
      } else if (fileData.url) {
        // It's a blob URL, use it (for backward compatibility)
        videoUrl.value = fileData.url
      } else {
        throw new Error('Invalid file data format')
      }

      fileName.value = fileData.name
      fileSize.value = formatFileSize(fileData.size)
    } catch (error) {
      console.error('Error parsing stored file data:', error)
      // If there's an error parsing, redirect to home
      router.visit('/')
    }
  } else {
    // If no file is found, redirect to home (fallback)
    console.warn('No video file found in session storage, redirecting to home')
    router.visit('/')
  }
})

const onVideoLoaded = () => {
  console.log('Video loaded successfully')
  // You can add additional logic here when video is ready
}

const onTimeUpdate = () => {
  if (videoPlayer.value) {
    subtitleStore.setCurrentTime(videoPlayer.value.currentTime)
  }
}

const onPlay = () => {
  subtitleStore.setPlaying(true)
}

const onPause = () => {
  subtitleStore.setPlaying(false)
}

const goBack = () => {
  // Clear the stored file and subtitles
  sessionStorage.removeItem('uploadedFile')
  sessionStorage.removeItem('subtitleData')
  subtitleStore.clearSubtitles()
  router.visit('/')
}

const editSubtitles = () => {
  router.visit('/subtitle/edit')
}

// Format file size for display
const formatFileSize = (bytes: number): string => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

// Format duration for display
const formatDuration = (seconds: number): string => {
  return SubtitleService.secondsToTimeString(seconds)
}
</script>

<style scoped>
.creativity-subtitle {
  min-height: 100vh;
  background-color: black;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.video-container {
  position: relative;
  max-width: 800px;
  width: 100%;
  margin-bottom: 30px;
}

.video-player {
  width: 100%;
  height: auto;
  border-radius: 12px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
  background-color: #000;
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

.controls {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 20px;
}

.control-buttons {
  display: flex;
  gap: 15px;
  align-items: center;
}

.back-button {
  padding: 12px 24px;
  background-color: #333;
  color: white;
  border: 2px solid #555;
  border-radius: 8px;
  cursor: pointer;
  font-size: 16px;
  font-weight: 600;
  transition: all 0.3s ease;
}

.back-button:hover {
  background-color: #444;
  border-color: #777;
  transform: translateY(-2px);
}

.back-button:active {
  transform: translateY(0);
}

.edit-button {
  padding: 12px 24px;
  background-color: #007bff;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 16px;
  font-weight: 600;
  transition: all 0.3s ease;
}

.edit-button:hover {
  background-color: #0056b3;
  transform: translateY(-2px);
}

.edit-button:active {
  transform: translateY(0);
}

.file-info {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  color: #ccc;
}

.subtitle-info {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  margin-top: 10px;
  padding: 10px;
  background-color: rgba(0, 123, 255, 0.1);
  border-radius: 6px;
  border: 1px solid rgba(0, 123, 255, 0.3);
}

.subtitle-info span {
  font-size: 12px;
  color: #007bff;
}

.file-name {
  font-size: 18px;
  font-weight: 600;
  color: white;
}

.file-size {
  font-size: 14px;
  color: #888;
}

@media (max-width: 768px) {
  .video-container {
    max-width: 100%;
  }

  .controls {
    width: 100%;
    max-width: 400px;
  }
}
</style>