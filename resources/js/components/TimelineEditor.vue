<template>
  <div class="timeline-editor">
    <div class="timeline-header">
      <h3>Timeline Editor</h3>
      <div class="timeline-info">
        <span>Total Duration: {{ formatDuration(totalDuration) }}</span>
        <span>{{ segments.length }} segments</span>
        <div class="validation-status" :class="{ 'has-errors': !getAllValidationStatus().isValid }">
          <span v-if="getAllValidationStatus().isValid" class="valid-status">✅ All chunks valid</span>
          <span v-else class="invalid-status">
            ❌ {{ getAllValidationStatus().invalidChunks.length }} invalid chunks
            <span v-if="hasTimelineExceedingChunks()" class="timeline-warning">⚠️ Some exceed video duration</span>
          </span>
        </div>
      </div>
    </div>

    <div class="timeline-container" ref="timelineContainer">
      <div 
        class="timeline-track" 
        :class="{ 
          'snapping': isSnapping,
          'dragging': isDragging
        }"
        :style="{ '--total-duration': totalDuration }"
        ref="timelineTrack" 
        @dblclick="handleTimelineDoubleClick"
      >
        <!-- Time markers -->
        <div class="time-markers">
          <div
            v-for="marker in timeMarkers"
            :key="marker.time"
            class="time-marker"
            :class="{ 'major-marker': marker.isMajor }"
            :style="{ left: `${(marker.time / totalDuration) * 100}%` }"
          >
            <div class="marker-line"></div>
            <div v-if="marker.isMajor" class="marker-label">{{ formatDuration(marker.time) }}</div>
          </div>
        </div>

        <!-- Subtitle segments -->
        <div
          v-for="(segment, index) in segments"
          :key="segment.id"
          class="timeline-segment"
          :class="{
            'selected': selectedSegmentId === segment.id,
            'editing': editingSegmentId === segment.id,
            'hover': hoveredSegmentId === segment.id,
            'invalid': !getChunkValidation(segment.id).isValid,
            'last-edited': lastEditedChunkId === segment.id
          }"
          :style="getSegmentStyle(segment)"
          @mouseenter="hoveredSegmentId = segment.id"
          @mouseleave="hoveredSegmentId = null"
          @click.stop="selectSegment(segment)"
        >
          <!-- Resize handles -->
          <div
            class="resize-handle resize-handle-left"
            @mousedown.stop="startResize($event, segment, 'left')"
          ></div>
          <div
            class="resize-handle resize-handle-right"
            @mousedown.stop="startResize($event, segment, 'right')"
          ></div>

          <!-- Segment content -->
          <div class="segment-content">
            <div class="segment-number">{{ index + 1 }}</div>
            <div class="segment-text">{{ segment.text.substring(0, 20) }}{{ segment.text.length > 20 ? '...' : '' }}</div>
            <div class="segment-timing">{{ segment.startTimeFormatted }} - {{ segment.endTimeFormatted }}</div>
          </div>
        </div>

        <!-- Video Timeline Boundary -->
        <div
          class="timeline-boundary"
          :style="{ left: '100%' }"
        ></div>

        <!-- Playhead -->
        <div
          class="playhead"
          :style="{ left: `${(currentTime / totalDuration) * 100}%` }"
          @mousedown="startPlayheadDrag($event)"
        ></div>
      </div>
    </div>

    <!-- Extension Blocked Notification -->
    <div
      v-if="isExtensionBlocked"
      class="extension-blocked-notification"
      :class="{
        'blocked-intersection': blockedExtensionReason === 'intersection',
        'blocked-boundary': blockedExtensionReason === 'boundary'
      }"
    >
      <div class="notification-content">
        <div class="notification-icon">
          <span v-if="blockedExtensionReason === 'intersection'">⚠️</span>
          <span v-else-if="blockedExtensionReason === 'boundary'">🚫</span>
        </div>
        <div class="notification-text">
          <div class="notification-title">
            {{ blockedExtensionReason === 'intersection' ? 'Segment Overlap' : 'Boundary Reached' }}
          </div>
          <div class="notification-message">
            {{ blockedExtensionReason === 'intersection'
              ? 'Cannot extend: would overlap with another segment'
              : 'Cannot extend: reached video boundary' }}
          </div>
        </div>
      </div>
    </div>

    <!-- Add Chunk Modal -->
    <div v-if="showAddChunkModal" class="modal-overlay" @click="closeAddChunkModal">
      <div class="add-chunk-modal" @click.stop>
        <div class="modal-header">
          <h3>Add New Subtitle Chunk</h3>
          <button class="close-btn" @click="closeAddChunkModal">✕</button>
        </div>

        <div class="modal-content">
          <div class="form-group">
            <label for="chunk-text">Subtitle Text</label>
            <textarea
              id="chunk-text"
              v-model="newChunkData.text"
              placeholder="Enter subtitle text..."
              rows="3"
            ></textarea>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label for="chunk-start">Start Time</label>
              <input
                id="chunk-start"
                v-model="newChunkData.startTimeFormatted"
                type="text"
                placeholder="MM:SS"
                @blur="validateTimeFormat('start')"
              />
              <div class="time-input-hint">Format: MM:SS (e.g., 01:30)</div>
            </div>

            <div class="form-group">
              <label for="chunk-end">End Time</label>
              <input
                id="chunk-end"
                v-model="newChunkData.endTimeFormatted"
                type="text"
                placeholder="MM:SS"
                @blur="validateTimeFormat('end')"
              />
              <div class="time-input-hint">Format: MM:SS (e.g., 02:00)</div>
            </div>
          </div>

          <div class="form-group">
            <label>Quick Position</label>
            <div class="quick-position-buttons">
              <button
                class="quick-btn"
                @click="setQuickPosition('beginning')"
              >
                At Beginning
              </button>
              <button
                class="quick-btn"
                @click="setQuickPosition('middle')"
              >
                At Middle
              </button>
              <button
                class="quick-btn"
                @click="setQuickPosition('end')"
              >
                At End
              </button>
              <button
                class="quick-btn"
                @click="setQuickPosition('current')"
              >
                At Playhead
              </button>
            </div>
          </div>

          <div v-if="newChunkError" class="error-message">
            {{ newChunkError }}
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn-secondary" @click="closeAddChunkModal">Cancel</button>
          <button class="btn-primary" @click="createChunkFromModal">Create Chunk</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, onUnmounted, watch } from 'vue'
import { useSubtitleStore } from '../stores/subtitle'
import { SubtitleService } from '../services/subtitle.service'
import type { SubtitleEntry } from '../types/subtitle'

interface Props {
  videoPlayer?: HTMLVideoElement | null
}

const props = defineProps<Props>()
const subtitleStore = useSubtitleStore()

// Refs
const timelineContainer = ref<HTMLDivElement | null>(null)
const timelineTrack = ref<HTMLDivElement | null>(null)

// State
const editingSegmentId = ref<string | null>(null)
const hoveredSegmentId = ref<string | null>(null)
const lastEditedChunkId = ref<string | null>(null)
const isResizing = ref(false)
const isPlayheadDragging = ref(false)
const dragStartX = ref(0)
const dragStartTime = ref(0)
const resizeHandle = ref<'left' | 'right' | null>(null)
const resizingSegment = ref<SubtitleEntry | null>(null)
const currentDragTime = ref(0) // Track the current dragged position
const isExtensionBlocked = ref(false)
const blockedExtensionReason = ref<'intersection' | 'boundary' | null>(null)

// Chunk creation state
const showAddChunkModal = ref(false)
const newChunkData = reactive({
  text: '',
  startTimeFormatted: '',
  endTimeFormatted: ''
})
const newChunkError = ref('')

// Snapping visual feedback
const isSnapping = ref(false)
const isDragging = ref(false)

// Computed
const segments = computed(() => subtitleStore.subtitleData?.entries || [])
const totalDuration = computed(() => subtitleStore.videoDuration || subtitleStore.totalDuration)
const currentTime = computed(() => subtitleStore.currentTime)

const selectedSegment = computed(() => subtitleStore.activeChunk)
const selectedSegmentId = computed(() => subtitleStore.activeChunk?.id || null)

const timeMarkers = computed(() => {
  const markers = []

  // Show markers every 1 second
  for (let i = 0; i <= totalDuration.value; i += 1) {
    markers.push({
      time: i,
      isMajor: i % 5 === 0 || i === 0 || i === totalDuration.value // Major markers every 5 seconds
    })
  }

  return markers
})

// Methods
const snapToSecond = (time: number): number => {
  const snapped = Math.round(time)

  // Show brief visual feedback when snapping occurs
  if (Math.abs(time - snapped) > 0.1) {
    isSnapping.value = true
    setTimeout(() => {
      isSnapping.value = false
    }, 200)
  }

  return snapped
}

const getSegmentStyle = (segment: SubtitleEntry) => {
  const startPercent = (segment.startTime / totalDuration.value) * 100
  const endPercent = (segment.endTime / totalDuration.value) * 100
  const widthPercent = endPercent - startPercent

  return {
    left: `${startPercent}%`,
    width: `${widthPercent}%`
  }
}

const selectSegment = (segment: SubtitleEntry) => {
  // Select segment by ID - no time-based selection
  subtitleStore.setActiveChunkById(segment.id)
  editingSegmentId.value = null
}

const handleTimelineDoubleClick = (event: MouseEvent) => {
  // Only handle double clicks on empty space (not on segments or resize handles)
  const target = event.target as HTMLElement

  // Check if double click was on a segment or resize handle
  if (target.closest('.timeline-segment') || target.closest('.resize-handle')) {
    return // Let the segment handle the double click
  }

  // Double click was on empty space - create new chunk at click position
  if (timelineTrack.value) {
    const rect = timelineTrack.value.getBoundingClientRect()
    const clickX = event.clientX - rect.left
    const clickPercent = clickX / rect.width
    const clickTime = clickPercent * totalDuration.value
    const snappedClickTime = snapToSecond(clickTime)
    
    // Create new chunk at double click position (snap immediately since no dragging)
    createChunkAtTime(snappedClickTime)
  }
}


const startResize = (event: MouseEvent, segment: SubtitleEntry, handle: 'left' | 'right') => {
  isResizing.value = true
  isDragging.value = true
  resizeHandle.value = handle
  resizingSegment.value = segment
  dragStartX.value = event.clientX
  dragStartTime.value = handle === 'left' ? segment.startTime : segment.endTime

  // Select the segment being resized
  subtitleStore.setActiveChunkById(segment.id)

  document.addEventListener('mousemove', handleResize)
  document.addEventListener('mouseup', stopResize)

  event.preventDefault()
}

const handleResize = (event: MouseEvent) => {
  if (!isResizing.value || !timelineTrack.value || !resizingSegment.value) return

  const rect = timelineTrack.value.getBoundingClientRect()
  const deltaX = event.clientX - dragStartX.value
  const deltaTime = (deltaX / rect.width) * totalDuration.value

  const newTime = Math.max(0, Math.min(totalDuration.value, dragStartTime.value + deltaTime))
  // Track the current drag position
  currentDragTime.value = newTime

  if (resizeHandle.value === 'left') {
    const newStartTime = Math.min(newTime, resizingSegment.value.endTime - 1) // Minimum 1s duration
    const validation = validateResizeExtension(resizingSegment.value, newStartTime, resizingSegment.value.endTime, 'left')
    
    if (validation.isValid) {
      updateSegmentTime(resizingSegment.value, newStartTime, resizingSegment.value.endTime)
    } else {
      // Show blocked extension animation
      showBlockedExtensionAnimation(validation.reason!)
    }
  } else {
    const newEndTime = Math.max(newTime, resizingSegment.value.startTime + 1) // Minimum 1s duration
    const validation = validateResizeExtension(resizingSegment.value, resizingSegment.value.startTime, newEndTime, 'right')
    
    if (validation.isValid) {
      updateSegmentTime(resizingSegment.value, resizingSegment.value.startTime, newEndTime)
    } else {
      // Show blocked extension animation
      showBlockedExtensionAnimation(validation.reason!)
    }
  }
}

const stopResize = () => {
  if (resizingSegment.value && resizeHandle.value) {
    // Snap only the handle that was being dragged to whole seconds on mouseup
    const currentSegment = resizingSegment.value
    const draggedTime = currentDragTime.value
    
    if (resizeHandle.value === 'left') {
      // Only snap the start time (left handle), keep end time as is
      const snappedStartTime = snapToSecond(draggedTime)
      
      // Ensure minimum 1 second duration after snapping
      const finalStartTime = Math.min(snappedStartTime, currentSegment.endTime - 1)
      
      updateSegmentTime(currentSegment, finalStartTime, currentSegment.endTime)
    } else {
      // Only snap the end time (right handle), keep start time as is
      const snappedEndTime = snapToSecond(draggedTime)
      
      // Ensure minimum 1 second duration after snapping
      const finalEndTime = Math.max(snappedEndTime, currentSegment.startTime + 1)
      
      updateSegmentTime(currentSegment, currentSegment.startTime, finalEndTime)
    }
  }
  
  isResizing.value = false
  isDragging.value = false
  resizeHandle.value = null
  resizingSegment.value = null
  currentDragTime.value = 0
  document.removeEventListener('mousemove', handleResize)
  document.removeEventListener('mouseup', stopResize)
}

const startPlayheadDrag = (event: MouseEvent) => {
  isPlayheadDragging.value = true
  isDragging.value = true
  document.addEventListener('mousemove', handlePlayheadDrag)
  document.addEventListener('mouseup', stopPlayheadDrag)
  event.preventDefault()
}

const handlePlayheadDrag = (event: MouseEvent) => {
  if (!isPlayheadDragging.value || !timelineTrack.value || !props.videoPlayer) return

  const rect = timelineTrack.value.getBoundingClientRect()
  const percent = Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width))
  const newTime = percent * totalDuration.value
  // Don't snap during drag - allow smooth movement

  props.videoPlayer.currentTime = newTime
}

const stopPlayheadDrag = () => {
  if (props.videoPlayer) {
    // Snap the final playhead position to whole seconds on mouseup
    const snappedTime = snapToSecond(props.videoPlayer.currentTime)
    props.videoPlayer.currentTime = snappedTime
  }
  
  isPlayheadDragging.value = false
  isDragging.value = false
  document.removeEventListener('mousemove', handlePlayheadDrag)
  document.removeEventListener('mouseup', stopPlayheadDrag)
}

const updateSegmentTime = (segment: SubtitleEntry, startTime: number, endTime: number) => {
  const updatedSegment: SubtitleEntry = {
    ...segment,
    startTime,
    endTime,
    startTimeFormatted: SubtitleService.secondsToTimeString(startTime),
    endTimeFormatted: SubtitleService.secondsToTimeString(endTime)
  }

  subtitleStore.updateEntry(updatedSegment)
  subtitleStore.saveToSession()
  // Mark this chunk as the last edited
  lastEditedChunkId.value = segment.id
}

const updateSegmentFromInput = (segment: SubtitleEntry) => {
  if (!isValidTimeFormat(segment.startTimeFormatted) || !isValidTimeFormat(segment.endTimeFormatted)) {
    alert('Please enter time in MM:SS format (e.g., 00:30)')
    return
  }

  const startTime = snapToSecond(SubtitleService.timeStringToSeconds(segment.startTimeFormatted))
  const endTime = snapToSecond(SubtitleService.timeStringToSeconds(segment.endTimeFormatted))

  if (startTime >= endTime) {
    alert('End time must be after start time')
    return
  }

  if (endTime - startTime < 1) {
    alert('Subtitle duration must be at least 1 second')
    return
  }

  updateSegmentTime(segment, startTime, endTime)
}

const updateSegmentText = (segment: SubtitleEntry) => {
  const updatedSegment: SubtitleEntry = {
    ...segment,
    text: segment.text.trim()
  }

  subtitleStore.updateEntry(updatedSegment)
  subtitleStore.saveToSession()
  // Mark this chunk as the last edited
  lastEditedChunkId.value = segment.id
}

const playSegment = (segment: SubtitleEntry) => {
  if (props.videoPlayer) {
    props.videoPlayer.currentTime = segment.startTime
    props.videoPlayer.play()
  }
}

const deleteSegment = (segment: SubtitleEntry) => {
  if (confirm('Are you sure you want to delete this segment?')) {
    subtitleStore.removeEntry(segment.id)
    subtitleStore.saveToSession()

    // Adjust active chunk index if needed
    if (subtitleStore.activeChunkIndex >= subtitleStore.subtitleCount) {
      subtitleStore.setActiveChunkIndex(Math.max(0, subtitleStore.subtitleCount - 1))
    }
  }
}

const addNewSegment = () => {
  const newEntry: SubtitleEntry = {
    id: subtitleStore.generateNewId(),
    startTime: totalDuration.value * 0.1, // Start at 10% of video
    endTime: totalDuration.value * 0.2,   // End at 20% of video
    text: 'New subtitle text',
    startTimeFormatted: SubtitleService.secondsToTimeString(totalDuration.value * 0.1),
    endTimeFormatted: SubtitleService.secondsToTimeString(totalDuration.value * 0.2),
    styling: {
      size: 'medium',
      color: 'white',
      position: 'bottom'
    }
  }

  subtitleStore.addEntry(newEntry)
  subtitleStore.saveToSession()
  // Select the newly created segment by ID
  subtitleStore.setActiveChunkById(newEntry.id)
  // Mark this chunk as the last edited
  lastEditedChunkId.value = newEntry.id
}

const getSegmentIndex = (segmentId: string) => {
  return segments.value.findIndex(s => s.id === segmentId)
}

const formatDuration = (seconds: number): string => {
  return SubtitleService.secondsToTimeString(seconds)
}

const isValidTimeFormat = (timeStr: string): boolean => {
  return /^\d{2}:\d{2}$/.test(timeStr)
}

const getChunkValidation = (chunkId: string) => {
  return subtitleStore.getChunkValidationStatus(chunkId)
}

const getAllValidationStatus = () => {
  return subtitleStore.validateAllChunks()
}

const hasTimelineExceedingChunks = () => {
  const validation = getAllValidationStatus()
  return validation.invalidChunks.some(chunkId => {
    const errors = validation.errors[chunkId] || []
    return errors.some(error => error.includes('video is only') || error.includes('exceeds video'))
  })
}

// Watch for changes in timeline exceeding chunks and update global state
watch(() => hasTimelineExceedingChunks(), (hasExceeding) => {
  subtitleStore.setHasTimelineExceedingChunks(hasExceeding)
}, { immediate: true })

// Intersection detection methods
const checkSegmentIntersection = (segment: SubtitleEntry, newStartTime: number, newEndTime: number) => {
  return segments.value.some(otherSegment => {
    if (otherSegment.id === segment.id) return false

    // Check if the new time range overlaps with any other segment
    return (newStartTime < otherSegment.endTime && newEndTime > otherSegment.startTime)
  })
}

const checkBoundaryViolation = (newStartTime: number, newEndTime: number) => {
  return newStartTime < 0 || newEndTime > totalDuration.value
}

const validateResizeExtension = (segment: SubtitleEntry, newStartTime: number, newEndTime: number, handle: 'left' | 'right') => {
  // Check boundary violations first
  if (checkBoundaryViolation(newStartTime, newEndTime)) {
    return { isValid: false, reason: 'boundary' }
  }

  // Check for intersections with other segments
  if (checkSegmentIntersection(segment, newStartTime, newEndTime)) {
    return { isValid: false, reason: 'intersection' }
  }

  return { isValid: true, reason: null }
}

const showBlockedExtensionAnimation = (reason: 'intersection' | 'boundary') => {
  isExtensionBlocked.value = true
  blockedExtensionReason.value = reason

  // Hide animation after 1.5 seconds
  setTimeout(() => {
    isExtensionBlocked.value = false
    blockedExtensionReason.value = null
  }, 1500)
}

// Chunk creation methods
const createChunkAtTime = (time: number) => {
  const duration = 1 // Default 1 seconds duration
  const startTime = Math.max(0, time - duration / 2)
  const endTime = Math.min(totalDuration.value, startTime + duration)

  // Adjust if we hit boundaries, ensuring minimum 1 second duration and whole seconds
  const adjustedStartTime = snapToSecond(endTime - duration >= 0 ? endTime - duration : 0)
  const adjustedEndTime = snapToSecond(Math.max(adjustedStartTime + 1, adjustedStartTime + duration)) // Ensure minimum 1s

  const newEntry: SubtitleEntry = {
    id: subtitleStore.generateNewId(),
    startTime: adjustedStartTime,
    endTime: adjustedEndTime,
    text: 'New subtitle text',
    startTimeFormatted: SubtitleService.secondsToTimeString(adjustedStartTime),
    endTimeFormatted: SubtitleService.secondsToTimeString(adjustedEndTime),
    styling: {
      size: 'medium',
      color: 'white',
      position: 'bottom'
    }
  }

  // Check for intersections before adding
  if (checkSegmentIntersection(newEntry, newEntry.startTime, newEntry.endTime)) {
    showBlockedExtensionAnimation('intersection')
    return
  }

  subtitleStore.addEntry(newEntry)
  subtitleStore.saveToSession()
  subtitleStore.setActiveChunkById(newEntry.id)
  lastEditedChunkId.value = newEntry.id
}

const closeAddChunkModal = () => {
  showAddChunkModal.value = false
  newChunkError.value = ''
  // Reset form data
  newChunkData.text = ''
  newChunkData.startTimeFormatted = ''
  newChunkData.endTimeFormatted = ''
}

const validateTimeFormat = (field: 'start' | 'end') => {
  const timeStr = field === 'start' ? newChunkData.startTimeFormatted : newChunkData.endTimeFormatted

  if (timeStr && !isValidTimeFormat(timeStr)) {
    newChunkError.value = `Invalid time format for ${field} time. Use MM:SS format (e.g., 01:30)`
    return false
  }

  newChunkError.value = ''
  return true
}

const setQuickPosition = (position: 'beginning' | 'middle' | 'end' | 'current') => {
  const duration = 2 // Default 2 seconds duration

  switch (position) {
    case 'beginning':
      newChunkData.startTimeFormatted = SubtitleService.secondsToTimeString(snapToSecond(0))
      newChunkData.endTimeFormatted = SubtitleService.secondsToTimeString(snapToSecond(Math.max(1, duration))) // Minimum 1s
      break
    case 'middle':
      const middleTime = snapToSecond(totalDuration.value / 2)
      newChunkData.startTimeFormatted = SubtitleService.secondsToTimeString(snapToSecond(middleTime - duration / 2))
      newChunkData.endTimeFormatted = SubtitleService.secondsToTimeString(snapToSecond(middleTime + duration / 2))
      break
    case 'end':
      newChunkData.endTimeFormatted = SubtitleService.secondsToTimeString(snapToSecond(totalDuration.value))
      newChunkData.startTimeFormatted = SubtitleService.secondsToTimeString(snapToSecond(Math.max(0, totalDuration.value - Math.max(1, duration))))
      break
    case 'current':
      const currentTimeValue = snapToSecond(currentTime.value)
      newChunkData.startTimeFormatted = SubtitleService.secondsToTimeString(currentTimeValue)
      newChunkData.endTimeFormatted = SubtitleService.secondsToTimeString(snapToSecond(Math.min(totalDuration.value, currentTimeValue + Math.max(1, duration))))
      break
  }

  newChunkError.value = ''
}

const createChunkFromModal = () => {
  // Validate inputs
  if (!newChunkData.text.trim()) {
    newChunkError.value = 'Please enter subtitle text'
    return
  }

  if (!newChunkData.startTimeFormatted || !newChunkData.endTimeFormatted) {
    newChunkError.value = 'Please enter both start and end times'
    return
  }

  if (!validateTimeFormat('start') || !validateTimeFormat('end')) {
    return
  }

  const startTime = snapToSecond(SubtitleService.timeStringToSeconds(newChunkData.startTimeFormatted))
  const endTime = snapToSecond(SubtitleService.timeStringToSeconds(newChunkData.endTimeFormatted))

  if (startTime >= endTime) {
    newChunkError.value = 'End time must be after start time'
    return
  }

  if (endTime - startTime < 1) {
    newChunkError.value = 'Subtitle duration must be at least 1 second'
    return
  }

  if (startTime < 0 || endTime > totalDuration.value) {
    newChunkError.value = 'Times must be within video duration (0 to ' + formatDuration(totalDuration.value) + ')'
    return
  }

  // Check for intersections
  if (checkSegmentIntersection({ id: 'temp' } as SubtitleEntry, startTime, endTime)) {
    newChunkError.value = 'This time range overlaps with an existing segment'
    return
  }

  // Create the chunk
  const newEntry: SubtitleEntry = {
    id: subtitleStore.generateNewId(),
    startTime,
    endTime,
    text: newChunkData.text.trim(),
    startTimeFormatted: newChunkData.startTimeFormatted,
    endTimeFormatted: newChunkData.endTimeFormatted,
    styling: {
      size: 'medium',
      color: 'white',
      position: 'bottom'
    }
  }

  subtitleStore.addEntry(newEntry)
  subtitleStore.saveToSession()
  subtitleStore.setActiveChunkById(newEntry.id)
  lastEditedChunkId.value = newEntry.id

  // Close modal and reset form
  closeAddChunkModal()
}

// Note: Removed automatic time-based selection
// Selection now only happens through explicit user interaction (clicking on segments)

onUnmounted(() => {
  // Clean up event listeners
  document.removeEventListener('mousemove', handleResize)
  document.removeEventListener('mouseup', stopResize)
  document.removeEventListener('mousemove', handlePlayheadDrag)
  document.removeEventListener('mouseup', stopPlayheadDrag)
})
</script>

<style scoped>
.timeline-editor {
  padding: 10px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
  max-height: calc(100vh - 150px);
}

.timeline-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;
}

.timeline-header h3 {
  margin: 0;
  color: #007bff;
  font-size: 18px;
  font-weight: 600;
}

.timeline-info {
  display: flex;
  gap: 12px;
  color: #888;
  font-size: 12px;
  align-items: center;
}

.validation-status {
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 600;
}

.validation-status.has-errors {
  background-color: rgba(220, 53, 69, 0.2);
  border: 1px solid #dc3545;
}

.valid-status {
  color: #28a745;
}

.invalid-status {
  color: #dc3545;
}

.timeline-warning {
  display: block;
  margin-top: 4px;
  font-size: 11px;
  color: #ff6b6b;
  font-weight: 500;
}

.timeline-container {
  position: relative;
  background-color: #333;
  border-radius: 6px;
  margin-bottom: 10px;
  flex: 1;
  min-height: 0;
}

.timeline-track {
  position: relative;
  height: 60px;
  background-color: #444;
  border-radius: 8px;
  cursor: crosshair;
  background-image:
    linear-gradient(to right, rgba(255, 255, 255, 0.05) 1px, transparent 1px);
  background-size: calc(100% / var(--total-duration, 1)) 100%;
}

.time-markers {
  position: absolute;
  top: -25px;
  left: 0;
  right: 0;
  height: 20px;
}

.time-marker {
  position: absolute;
  top: 0;
  transform: translateX(-50%);
}

.marker-line {
  width: 1px;
  height: 12px;
  background-color: #555;
  margin: 0 auto;
  transition: all 0.2s ease;
}

.time-marker.major-marker .marker-line {
  width: 2px;
  height: 20px;
  background-color: #666;
}

.marker-label {
  font-size: 11px;
  color: #888;
  text-align: center;
  margin-top: 4px;
  font-weight: 500;
  white-space: nowrap;
}

.timeline-segment {
  position: absolute;
  top: 4px;
  height: 52px;
  background-color: #007bff;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s ease;
  border: 2px solid transparent;
  overflow: hidden;
}

.timeline-segment:hover {
  background-color: #0056b3;
  transform: translateY(-2px);
}

.timeline-segment.selected {
  border-color: #ffc107;
  background-color: #0056b3;
  box-shadow: 0 0 10px rgba(255, 193, 7, 0.5);
}

.timeline-segment.editing {
  border-color: #28a745;
  background-color: #0056b3;
  box-shadow: 0 0 10px rgba(40, 167, 69, 0.5);
}

.timeline-segment.hover {
  background-color: #0056b3;
}

.timeline-segment.invalid {
  background-color: #dc3545 !important;
  border-color: #c82333 !important;
}

.timeline-segment.invalid:hover {
  background-color: #c82333 !important;
}

.timeline-segment.invalid.selected {
  border-color: #ffc107 !important;
  box-shadow: 0 0 10px rgba(255, 193, 7, 0.5) !important;
}

.timeline-segment.last-edited {
  z-index: 15;
  transform: translateY(-3px);
  box-shadow: 0 4px 15px rgba(0, 123, 255, 0.4);
}

.timeline-segment.last-edited.selected {
  z-index: 20;
  transform: translateY(-3px);
  box-shadow: 0 4px 15px rgba(255, 193, 7, 0.6);
}

.resize-handle {
  position: absolute;
  top: 0;
  width: 8px;
  height: 100%;
  background-color: rgba(255, 255, 255, 0.3);
  cursor: ew-resize;
  opacity: 0;
  transition: opacity 0.2s;
}

.timeline-segment:hover .resize-handle {
  opacity: 1;
}

.resize-handle-left {
  left: 0;
  border-radius: 4px 0 0 4px;
}

.resize-handle-right {
  right: 0;
  border-radius: 0 4px 4px 0;
}

.segment-content {
  padding: 4px 8px;
  height: 100%;
  display: flex;
  flex-direction: column;
  justify-content: center;
  pointer-events: none;
}

.segment-number {
  font-size: 9px;
  color: rgba(255, 255, 255, 0.8);
  font-weight: 600;
  margin-bottom: 1px;
}

.segment-text {
  font-size: 11px;
  color: white;
  font-weight: 500;
  line-height: 1.1;
  margin-bottom: 1px;
}

.segment-timing {
  font-size: 8px;
  color: rgba(255, 255, 255, 0.7);
}

.playhead {
  position: absolute;
  top: 0;
  width: 2px;
  height: 100%;
  background-color: #ff4444;
  cursor: ew-resize;
  z-index: 9999;
}

.playhead::before {
  content: '';
  position: absolute;
  top: -5px;
  left: -4px;
  width: 10px;
  height: 10px;
  background-color: #ff4444;
  border-radius: 50%;
}

.timeline-boundary {
  position: absolute;
  top: 0;
  width: 3px;
  height: 100%;
  background-color: #ff6b6b;
  z-index: 5;
  border-radius: 2px;
}

.timeline-boundary::before {
  content: 'END';
  position: absolute;
  top: -20px;
  left: -15px;
  font-size: 10px;
  color: #ff6b6b;
  font-weight: 600;
  background-color: rgba(0, 0, 0, 0.8);
  padding: 2px 4px;
  border-radius: 3px;
}

.segment-details {
  background-color: #333;
  border-radius: 6px;
  padding: 15px;
  margin-bottom: 15px;
}

.segment-details h4 {
  margin: 0 0 15px 0;
  color: #007bff;
}

.detail-grid {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 15px;
  margin-bottom: 15px;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.detail-item label {
  font-size: 12px;
  color: #888;
  font-weight: 600;
}

.detail-item input {
  padding: 8px;
  background-color: #444;
  color: white;
  border: 1px solid #555;
  border-radius: 4px;
  font-size: 14px;
}

.detail-item input:focus {
  outline: none;
  border-color: #007bff;
}

.detail-item span {
  color: white;
  font-weight: 600;
}

.segment-text-edit {
  margin-bottom: 15px;
}

.segment-text-edit label {
  display: block;
  font-size: 12px;
  color: #888;
  font-weight: 600;
  margin-bottom: 5px;
}

.segment-text-edit textarea {
  width: 100%;
  padding: 8px;
  background-color: #444;
  color: white;
  border: 1px solid #555;
  border-radius: 4px;
  font-size: 14px;
  min-height: 60px;
  resize: vertical;
}

.segment-text-edit textarea:focus {
  outline: none;
  border-color: #007bff;
}

.validation-errors {
  background-color: rgba(220, 53, 69, 0.1);
  border: 1px solid #dc3545;
  border-radius: 6px;
  padding: 15px;
  margin-bottom: 15px;
}

.validation-errors h5 {
  margin: 0 0 10px 0;
  color: #dc3545;
  font-size: 14px;
}

.validation-errors ul {
  margin: 0;
  padding-left: 20px;
}

.error-item {
  color: #dc3545;
  font-size: 13px;
  margin-bottom: 5px;
}

.segment-actions {
  display: flex;
  gap: 10px;
}

.play-btn, .delete-btn {
  padding: 8px 15px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s;
}

.play-btn {
  background-color: #28a745;
  color: white;
}

.play-btn:hover {
  background-color: #218838;
}

.delete-btn {
  background-color: #dc3545;
  color: white;
}

.delete-btn:hover {
  background-color: #c82333;
}

.add-segment-area {
  text-align: center;
}

.add-segment-btn {
  padding: 10px 20px;
  background-color: #28a745;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  transition: background-color 0.3s;
}

.add-segment-btn:hover {
  background-color: #218838;
}

/* Extension Blocked Notification Styles */
.extension-blocked-notification {
  position: fixed;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 1000;
  pointer-events: none;
  animation: blockedExtensionSlideIn 0.3s ease-out;
}

.notification-content {
  display: flex;
  align-items: center;
  gap: 12px;
  background: rgba(0, 0, 0, 0.9);
  border-radius: 12px;
  padding: 16px 20px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
  backdrop-filter: blur(10px);
  border: 2px solid;
  min-width: 280px;
}

.blocked-intersection .notification-content {
  border-color: #ff6b6b;
  background: rgba(255, 107, 107, 0.1);
}

.blocked-boundary .notification-content {
  border-color: #ffa726;
  background: rgba(255, 167, 38, 0.1);
}

.notification-icon {
  font-size: 24px;
  flex-shrink: 0;
}

.notification-text {
  flex: 1;
}

.notification-title {
  font-size: 16px;
  font-weight: 700;
  margin-bottom: 4px;
  color: white;
}

.blocked-intersection .notification-title {
  color: #ff6b6b;
}

.blocked-boundary .notification-title {
  color: #ffa726;
}

.notification-message {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.8);
  line-height: 1.4;
}

/* Animation for blocked extension */
@keyframes blockedExtensionSlideIn {
  0% {
    opacity: 0;
    transform: translate(-50%, -50%) scale(0.8) translateY(-20px);
  }
  50% {
    opacity: 1;
    transform: translate(-50%, -50%) scale(1.05) translateY(0);
  }
  100% {
    opacity: 1;
    transform: translate(-50%, -50%) scale(1) translateY(0);
  }
}

@keyframes blockedExtensionSlideOut {
  0% {
    opacity: 1;
    transform: translate(-50%, -50%) scale(1) translateY(0);
  }
  100% {
    opacity: 0;
    transform: translate(-50%, -50%) scale(0.8) translateY(-20px);
  }
}


/* Snapping visual feedback */
.timeline-track.snapping {
  animation: snapPulse 0.2s ease-out;
}

/* Dragging visual feedback */
.timeline-track.dragging {
  background-color: #4a4a4a;
  box-shadow: inset 0 0 0 2px rgba(0, 123, 255, 0.3);
}


@keyframes snapPulse {
  0% {
    box-shadow: 0 0 0 0 rgba(0, 123, 255, 0.4);
    transform: scale(1);
  }
  50% {
    box-shadow: 0 0 0 8px rgba(0, 123, 255, 0.1);
    transform: scale(1.01);
  }
  100% {
    box-shadow: 0 0 0 0 rgba(0, 123, 255, 0);
    transform: scale(1);
  }
}

/* Enhanced resize handle feedback */
.resize-handle.blocked {
  background-color: rgba(255, 107, 107, 0.6) !important;
  animation: resizeHandlePulse 0.3s ease-in-out;
}

@keyframes resizeHandlePulse {
  0% { transform: scale(1); }
  50% { transform: scale(1.2); }
  100% { transform: scale(1); }
}

/* Modal Styles */
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(0, 0, 0, 0.7);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2000;
  backdrop-filter: blur(4px);
}

.add-chunk-modal {
  background-color: #333;
  border-radius: 12px;
  width: 90%;
  max-width: 500px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
  animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
  0% {
    opacity: 0;
    transform: scale(0.9) translateY(-20px);
  }
  100% {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px 24px;
  border-bottom: 1px solid #444;
}

.modal-header h3 {
  margin: 0;
  color: #007bff;
  font-size: 18px;
  font-weight: 600;
}

.close-btn {
  background: none;
  border: none;
  color: #888;
  font-size: 20px;
  cursor: pointer;
  padding: 4px;
  border-radius: 4px;
  transition: all 0.2s;
}

.close-btn:hover {
  color: white;
  background-color: #444;
}

.modal-content {
  padding: 24px;
}

.form-group {
  margin-bottom: 20px;
}

.form-group label {
  display: block;
  margin-bottom: 8px;
  color: #ccc;
  font-weight: 600;
  font-size: 14px;
}

.form-group input,
.form-group textarea {
  width: 100%;
  padding: 12px;
  background-color: #444;
  color: white;
  border: 1px solid #555;
  border-radius: 6px;
  font-size: 14px;
  transition: border-color 0.2s;
}

.form-group input:focus,
.form-group textarea:focus {
  outline: none;
  border-color: #007bff;
}

.form-group textarea {
  resize: vertical;
  min-height: 80px;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.time-input-hint {
  font-size: 12px;
  color: #888;
  margin-top: 4px;
}

.quick-position-buttons {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
}

.quick-btn {
  padding: 8px 12px;
  background-color: #444;
  color: white;
  border: 1px solid #555;
  border-radius: 6px;
  cursor: pointer;
  font-size: 13px;
  transition: all 0.2s;
}

.quick-btn:hover {
  background-color: #555;
  border-color: #007bff;
}

.error-message {
  background-color: rgba(220, 53, 69, 0.1);
  border: 1px solid #dc3545;
  border-radius: 6px;
  padding: 12px;
  color: #dc3545;
  font-size: 14px;
  margin-top: 16px;
}

.modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 20px 24px;
  border-top: 1px solid #444;
}

.btn-primary,
.btn-secondary {
  padding: 10px 20px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  font-size: 14px;
  transition: all 0.2s;
}

.btn-primary {
  background-color: #007bff;
  color: white;
}

.btn-primary:hover {
  background-color: #0056b3;
}

.btn-secondary {
  background-color: #6c757d;
  color: white;
}

.btn-secondary:hover {
  background-color: #545b62;
}

@media (max-width: 768px) {
  .detail-grid {
    grid-template-columns: 1fr;
  }

  .segment-actions {
    flex-direction: column;
  }

  .notification-content {
    min-width: 240px;
    padding: 12px 16px;
  }

  .notification-title {
    font-size: 14px;
  }

  .notification-message {
    font-size: 12px;
  }

  .add-chunk-modal {
    width: 95%;
    margin: 20px;
  }

  .form-row {
    grid-template-columns: 1fr;
  }

  .quick-position-buttons {
    grid-template-columns: 1fr;
  }

  .modal-footer {
    flex-direction: column;
  }

  .btn-primary,
  .btn-secondary {
    width: 100%;
  }
}
</style>
