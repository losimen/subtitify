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
      <div class="timeline-track" ref="timelineTrack">
        <!-- Time markers -->
        <div class="time-markers">
          <div
            v-for="marker in timeMarkers"
            :key="marker.time"
            class="time-marker"
            :style="{ left: `${(marker.time / totalDuration) * 100}%` }"
          >
            <div class="marker-line"></div>
            <div class="marker-label">{{ formatDuration(marker.time) }}</div>
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
            'invalid': !getChunkValidation(segment.id).isValid
          }"
          :style="getSegmentStyle(segment)"
          @mouseenter="hoveredSegmentId = segment.id"
          @mouseleave="hoveredSegmentId = null"
          @click="selectSegment(segment)"
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
const isResizing = ref(false)
const isPlayheadDragging = ref(false)
const dragStartX = ref(0)
const dragStartTime = ref(0)
const resizeHandle = ref<'left' | 'right' | null>(null)

// Computed
const segments = computed(() => subtitleStore.subtitleData?.entries || [])
const totalDuration = computed(() => subtitleStore.videoDuration || subtitleStore.totalDuration)
const currentTime = computed(() => subtitleStore.currentTime)

const selectedSegment = computed(() => subtitleStore.activeChunk)
const selectedSegmentId = computed(() => subtitleStore.activeChunk?.id || null)

const timeMarkers = computed(() => {
  const markers = []
  const interval = Math.max(1, Math.floor(totalDuration.value / 10)) // Show up to 10 markers

  for (let i = 0; i <= totalDuration.value; i += interval) {
    markers.push({ time: i })
  }

  // Always show the end marker
  if (markers[markers.length - 1]?.time !== totalDuration.value) {
    markers.push({ time: totalDuration.value })
  }

  return markers
})

// Methods
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
  subtitleStore.setActiveChunkById(segment.id)
  editingSegmentId.value = null
}


const startResize = (event: MouseEvent, segment: SubtitleEntry, handle: 'left' | 'right') => {
  isResizing.value = true
  resizeHandle.value = handle
  dragStartX.value = event.clientX
  dragStartTime.value = handle === 'left' ? segment.startTime : segment.endTime

  document.addEventListener('mousemove', handleResize)
  document.addEventListener('mouseup', stopResize)

  event.preventDefault()
}

const handleResize = (event: MouseEvent) => {
  if (!isResizing.value || !timelineTrack.value || !selectedSegment.value) return

  const rect = timelineTrack.value.getBoundingClientRect()
  const deltaX = event.clientX - dragStartX.value
  const deltaTime = (deltaX / rect.width) * totalDuration.value

  const newTime = Math.max(0, Math.min(totalDuration.value, dragStartTime.value + deltaTime))

  if (resizeHandle.value === 'left') {
    const newStartTime = Math.min(newTime, selectedSegment.value.endTime - 0.5) // Minimum 0.5s duration
    updateSegmentTime(selectedSegment.value, newStartTime, selectedSegment.value.endTime)
  } else {
    const newEndTime = Math.max(newTime, selectedSegment.value.startTime + 0.5) // Minimum 0.5s duration
    updateSegmentTime(selectedSegment.value, selectedSegment.value.startTime, newEndTime)
  }
}

const stopResize = () => {
  isResizing.value = false
  resizeHandle.value = null
  document.removeEventListener('mousemove', handleResize)
  document.removeEventListener('mouseup', stopResize)
}

const startPlayheadDrag = (event: MouseEvent) => {
  isPlayheadDragging.value = true
  document.addEventListener('mousemove', handlePlayheadDrag)
  document.addEventListener('mouseup', stopPlayheadDrag)
  event.preventDefault()
}

const handlePlayheadDrag = (event: MouseEvent) => {
  if (!isPlayheadDragging.value || !timelineTrack.value || !props.videoPlayer) return

  const rect = timelineTrack.value.getBoundingClientRect()
  const percent = Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width))
  const newTime = percent * totalDuration.value

  props.videoPlayer.currentTime = newTime
}

const stopPlayheadDrag = () => {
  isPlayheadDragging.value = false
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
}

const updateSegmentFromInput = (segment: SubtitleEntry) => {
  if (!isValidTimeFormat(segment.startTimeFormatted) || !isValidTimeFormat(segment.endTimeFormatted)) {
    alert('Please enter time in MM:SS format (e.g., 00:30)')
    return
  }

  const startTime = SubtitleService.timeStringToSeconds(segment.startTimeFormatted)
  const endTime = SubtitleService.timeStringToSeconds(segment.endTimeFormatted)

  if (startTime >= endTime) {
    alert('End time must be after start time')
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
    endTimeFormatted: SubtitleService.secondsToTimeString(totalDuration.value * 0.2)
  }

  subtitleStore.addEntry(newEntry)
  subtitleStore.saveToSession()
  subtitleStore.setActiveChunkById(newEntry.id)
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

// Watch for current time changes to update playhead
watch(currentTime, (newTime) => {
  // Auto-select segment if playhead is over it
  const currentSegment = segments.value.find(segment =>
    newTime >= segment.startTime && newTime <= segment.endTime
  )

  if (currentSegment && subtitleStore.activeChunk?.id !== currentSegment.id) {
    subtitleStore.setActiveChunkById(currentSegment.id)
  }
})

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
  padding: 20px;
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
  width: 2px;
  height: 20px;
  background-color: #666;
  margin: 0 auto;
}

.marker-label {
  font-size: 12px;
  color: #888;
  text-align: center;
  margin-top: 4px;
  font-weight: 500;
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
  z-index: 10;
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

@media (max-width: 768px) {
  .detail-grid {
    grid-template-columns: 1fr;
  }

  .segment-actions {
    flex-direction: column;
  }
}
</style>
