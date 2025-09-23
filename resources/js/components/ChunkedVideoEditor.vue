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
            :class="{ 'active': currentChunkIndex === index, 'editing': editingChunkIndex === index }"
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

      <!-- Editing Panel -->
      <div class="editing-panel">
        <div v-if="editingChunkIndex !== null && currentSubtitle" class="edit-form">
          <h3>Edit Chunk {{ editingChunkIndex + 1 }}</h3>
          <form @submit.prevent="saveChunkChanges">
            <div class="form-group">
              <label>Start Time (MM:SS)</label>
              <input 
                v-model="editForm.startTimeFormatted" 
                type="text" 
                pattern="[0-9]{2}:[0-9]{2}"
                placeholder="00:00"
                required
              />
            </div>
            
            <div class="form-group">
              <label>End Time (MM:SS)</label>
              <input 
                v-model="editForm.endTimeFormatted" 
                type="text" 
                pattern="[0-9]{2}:[0-9]{2}"
                placeholder="00:04"
                required
              />
            </div>
            
            <div class="form-group">
              <label>Subtitle Text</label>
              <textarea 
                v-model="editForm.text" 
                placeholder="Enter subtitle text..."
                required
              ></textarea>
            </div>
            
            <div class="form-actions">
              <button type="submit" class="save-btn">Save Changes</button>
              <button type="button" @click="cancelEdit" class="cancel-btn">Cancel</button>
              <button type="button" @click="deleteChunk" class="delete-btn">Delete Chunk</button>
            </div>
          </form>
        </div>

        <!-- Add New Chunk Form -->
        <div v-if="showAddForm" class="edit-form">
          <h3>Add New Chunk</h3>
          <form @submit.prevent="addChunk">
            <div class="form-group">
              <label>Start Time (MM:SS)</label>
              <input 
                v-model="newChunkForm.startTimeFormatted" 
                type="text" 
                pattern="[0-9]{2}:[0-9]{2}"
                placeholder="00:00"
                required
              />
            </div>
            
            <div class="form-group">
              <label>End Time (MM:SS)</label>
              <input 
                v-model="newChunkForm.endTimeFormatted" 
                type="text" 
                pattern="[0-9]{2}:[0-9]{2}"
                placeholder="00:04"
                required
              />
            </div>
            
            <div class="form-group">
              <label>Subtitle Text</label>
              <textarea 
                v-model="newChunkForm.text" 
                placeholder="Enter subtitle text..."
                required
              ></textarea>
            </div>
            
            <div class="form-actions">
              <button type="submit" class="save-btn">Add Chunk</button>
              <button type="button" @click="cancelAdd" class="cancel-btn">Cancel</button>
            </div>
          </form>
        </div>

        <!-- Chunk Actions -->
        <div v-if="!showAddForm && editingChunkIndex === null" class="chunk-actions">
          <button @click="startEditChunk" class="edit-chunk-btn" :disabled="!currentSubtitle">
            ✏️ Edit This Chunk
          </button>
          <button @click="addNewChunk" class="add-chunk-btn">
            + Add New Chunk
          </button>
          <button @click="exportSubtitles" class="export-btn">
            📁 Export Subtitles
          </button>
        </div>
      </div>
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
import type { SubtitleEntry } from '../types/subtitle'

const subtitleStore = useSubtitleStore()
const videoPlayer = ref<HTMLVideoElement | null>(null)
const videoUrl = ref<string>('')
const currentChunkIndex = ref(0)
const editingChunkIndex = ref<number | null>(null)
const showAddForm = ref(false)

// Edit form data
const editForm = reactive({
  startTimeFormatted: '',
  endTimeFormatted: '',
  text: ''
})

// New chunk form data
const newChunkForm = reactive({
  startTimeFormatted: '',
  endTimeFormatted: '',
  text: ''
})

// Computed properties
const currentSubtitle = computed(() => {
  if (!subtitleStore.subtitleData || currentChunkIndex.value >= subtitleStore.subtitleData.entries.length) {
    return null
  }
  return subtitleStore.subtitleData.entries[currentChunkIndex.value]
})

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
watch(currentChunkIndex, (newIndex) => {
  if (currentSubtitle.value && videoPlayer.value) {
    videoPlayer.value.currentTime = currentSubtitle.value.startTime
  }
})

const selectChunk = (index: number) => {
  currentChunkIndex.value = index
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

const startEditChunk = () => {
  if (currentSubtitle.value) {
    editingChunkIndex.value = currentChunkIndex.value
    editForm.startTimeFormatted = currentSubtitle.value.startTimeFormatted
    editForm.endTimeFormatted = currentSubtitle.value.endTimeFormatted
    editForm.text = currentSubtitle.value.text
  }
}

const saveChunkChanges = () => {
  if (!currentSubtitle.value || editingChunkIndex.value === null) return

  // Validate time format
  if (!isValidTimeFormat(editForm.startTimeFormatted) || !isValidTimeFormat(editForm.endTimeFormatted)) {
    alert('Please enter time in MM:SS format (e.g., 00:30)')
    return
  }

  const startTime = SubtitleService.timeStringToSeconds(editForm.startTimeFormatted)
  const endTime = SubtitleService.timeStringToSeconds(editForm.endTimeFormatted)

  if (startTime >= endTime) {
    alert('End time must be after start time')
    return
  }

  // Check for overlapping subtitles
  if (hasOverlappingSubtitles(startTime, endTime, currentSubtitle.value.id)) {
    alert('This time range overlaps with another subtitle. Please adjust the timing.')
    return
  }

  const updatedEntry: SubtitleEntry = {
    ...currentSubtitle.value,
    startTime,
    endTime,
    text: editForm.text.trim(),
    startTimeFormatted: editForm.startTimeFormatted,
    endTimeFormatted: editForm.endTimeFormatted
  }

  subtitleStore.updateEntry(updatedEntry)
  subtitleStore.saveToSession()
  cancelEdit()
}

const cancelEdit = () => {
  editingChunkIndex.value = null
  editForm.startTimeFormatted = ''
  editForm.endTimeFormatted = ''
  editForm.text = ''
}

const addNewChunk = () => {
  showAddForm.value = true
  newChunkForm.startTimeFormatted = ''
  newChunkForm.endTimeFormatted = ''
  newChunkForm.text = ''
}

const addChunk = () => {
  // Validate time format
  if (!isValidTimeFormat(newChunkForm.startTimeFormatted) || !isValidTimeFormat(newChunkForm.endTimeFormatted)) {
    alert('Please enter time in MM:SS format (e.g., 00:30)')
    return
  }

  const startTime = SubtitleService.timeStringToSeconds(newChunkForm.startTimeFormatted)
  const endTime = SubtitleService.timeStringToSeconds(newChunkForm.endTimeFormatted)

  if (startTime >= endTime) {
    alert('End time must be after start time')
    return
  }

  // Check for overlapping subtitles
  if (hasOverlappingSubtitles(startTime, endTime)) {
    alert('This time range overlaps with another subtitle. Please adjust the timing.')
    return
  }

  const newEntry: SubtitleEntry = {
    id: subtitleStore.generateNewId(),
    startTime,
    endTime,
    text: newChunkForm.text.trim(),
    startTimeFormatted: newChunkForm.startTimeFormatted,
    endTimeFormatted: newChunkForm.endTimeFormatted
  }

  subtitleStore.addEntry(newEntry)
  subtitleStore.saveToSession()
  cancelAdd()
  
  // Navigate to the new chunk
  const newIndex = subtitleStore.subtitleData?.entries.findIndex(e => e.id === newEntry.id) || 0
  currentChunkIndex.value = newIndex
}

const cancelAdd = () => {
  showAddForm.value = false
  newChunkForm.startTimeFormatted = ''
  newChunkForm.endTimeFormatted = ''
  newChunkForm.text = ''
}

const deleteChunk = () => {
  if (currentSubtitle.value && confirm('Are you sure you want to delete this chunk?')) {
    subtitleStore.removeEntry(currentSubtitle.value.id)
    subtitleStore.saveToSession()
    
    // Adjust current chunk index
    if (currentChunkIndex.value >= (subtitleStore.subtitleData?.entries.length || 0)) {
      currentChunkIndex.value = Math.max(0, (subtitleStore.subtitleData?.entries.length || 0) - 1)
    }
    
    cancelEdit()
  }
}

const exportSubtitles = () => {
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

const isValidTimeFormat = (timeStr: string): boolean => {
  return /^\d{2}:\d{2}$/.test(timeStr)
}

const hasOverlappingSubtitles = (startTime: number, endTime: number, excludeId?: string): boolean => {
  if (!subtitleStore.subtitleData) return false
  
  return subtitleStore.subtitleData.entries.some(entry => {
    if (excludeId && entry.id === excludeId) return false
    
    return (
      (startTime >= entry.startTime && startTime < entry.endTime) ||
      (endTime > entry.startTime && endTime <= entry.endTime) ||
      (startTime <= entry.startTime && endTime >= entry.endTime)
    )
  })
}
</script>

<style scoped>
.chunked-video-editor {
  min-height: 100vh;
  background-color: black;
  color: white;
  padding: 20px;
}

.editor-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
  padding-bottom: 20px;
  border-bottom: 2px solid #333;
}

.editor-header h2 {
  font-size: 28px;
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 15px;
}

.add-button, .back-button {
  padding: 10px 20px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
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
  grid-template-columns: 300px 1fr 350px;
  gap: 20px;
  margin-bottom: 30px;
}

.chunk-navigation {
  background-color: #222;
  border-radius: 8px;
  padding: 15px;
  max-height: 600px;
  overflow-y: auto;
}

.chunk-list {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.chunk-nav-item {
  padding: 12px;
  background-color: #333;
  border: 2px solid #444;
  border-radius: 6px;
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

.chunk-number {
  font-size: 14px;
  color: #007bff;
  font-weight: 600;
  margin-bottom: 5px;
}

.chunk-timing {
  font-size: 12px;
  color: #888;
  margin-bottom: 5px;
}

.chunk-text-preview {
  font-size: 13px;
  line-height: 1.3;
  color: #ccc;
}

.video-player-area {
  display: flex;
  flex-direction: column;
  gap: 20px;
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
  gap: 15px;
  padding: 15px;
  background-color: #222;
  border-radius: 8px;
}

.play-chunk-btn, .play-pause-btn {
  padding: 10px 15px;
  background-color: #007bff;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
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
  padding: 15px;
  border-radius: 8px;
}

.chunk-info h3 {
  margin: 0 0 15px 0;
  color: #007bff;
}

.chunk-details {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.detail-item {
  display: flex;
  justify-content: space-between;
  font-size: 14px;
}

.detail-item label {
  color: #888;
}

.detail-item span {
  color: white;
  font-weight: 600;
}

.editing-panel {
  background-color: #222;
  border-radius: 8px;
  padding: 20px;
  max-height: 600px;
  overflow-y: auto;
}

.edit-form h3 {
  margin: 0 0 20px 0;
  color: #007bff;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: #ccc;
}

.form-group input,
.form-group textarea {
  width: 100%;
  padding: 10px;
  background-color: #333;
  color: white;
  border: 2px solid #555;
  border-radius: 6px;
  font-size: 14px;
  transition: all 0.3s;
}

.form-group input:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #007bff;
  background-color: #444;
}

.form-group textarea {
  min-height: 80px;
  resize: vertical;
}

.form-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.save-btn, .cancel-btn, .delete-btn {
  padding: 10px 15px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s;
}

.save-btn {
  background-color: #28a745;
  color: white;
}

.save-btn:hover {
  background-color: #218838;
}

.cancel-btn {
  background-color: #6c757d;
  color: white;
}

.cancel-btn:hover {
  background-color: #5a6268;
}

.delete-btn {
  background-color: #dc3545;
  color: white;
}

.delete-btn:hover {
  background-color: #c82333;
}

.chunk-actions {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.edit-chunk-btn, .add-chunk-btn, .export-btn {
  padding: 12px 20px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s;
}

.edit-chunk-btn {
  background-color: #007bff;
  color: white;
}

.edit-chunk-btn:hover:not(:disabled) {
  background-color: #0056b3;
}

.edit-chunk-btn:disabled {
  background-color: #555;
  cursor: not-allowed;
}

.add-chunk-btn {
  background-color: #28a745;
  color: white;
}

.add-chunk-btn:hover {
  background-color: #218838;
}

.export-btn {
  background-color: #6c757d;
  color: white;
}

.export-btn:hover {
  background-color: #5a6268;
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
    grid-template-columns: 250px 1fr 300px;
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
}
</style>