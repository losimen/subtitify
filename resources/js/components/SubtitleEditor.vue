<template>
  <div class="subtitle-editor">
    <div class="editor-header">
      <h2>Subtitle Editor</h2>
      <div class="header-actions">
        <button @click="addNewEntry" class="add-button">
          + Add New Subtitle
        </button>
        <button @click="goBack" class="back-button">
          ← Back to Video
        </button>
      </div>
    </div>

    <div class="editor-content">
      <div class="subtitle-list">
        <div 
          v-for="(entry, index) in subtitleStore.subtitleData?.entries || []" 
          :key="entry.id"
          class="subtitle-item"
          :class="{ 'editing': subtitleStore.isEditing && subtitleStore.editingEntry?.id === entry.id }"
        >
          <div class="subtitle-info">
            <div class="subtitle-number">{{ index + 1 }}</div>
            <div class="subtitle-timing">
              {{ entry.startTimeFormatted }} - {{ entry.endTimeFormatted }}
            </div>
            <div class="subtitle-text">{{ entry.text }}</div>
          </div>
          <div class="subtitle-actions">
            <button @click="editEntry(entry)" class="edit-btn">Edit</button>
            <button @click="removeEntry(entry.id)" class="delete-btn">Delete</button>
          </div>
        </div>
      </div>

      <!-- Edit Form -->
      <div v-if="subtitleStore.isEditing && subtitleStore.editingEntry" class="edit-form">
        <h3>Edit Subtitle</h3>
        <form @submit.prevent="saveChanges">
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
          </div>
        </form>
      </div>

      <!-- Add New Form -->
      <div v-if="showAddForm" class="edit-form">
        <h3>Add New Subtitle</h3>
        <form @submit.prevent="addEntry">
          <div class="form-group">
            <label>Start Time (MM:SS)</label>
            <input 
              v-model="newEntryForm.startTimeFormatted" 
              type="text" 
              pattern="[0-9]{2}:[0-9]{2}"
              placeholder="00:00"
              required
            />
          </div>
          
          <div class="form-group">
            <label>End Time (MM:SS)</label>
            <input 
              v-model="newEntryForm.endTimeFormatted" 
              type="text" 
              pattern="[0-9]{2}:[0-9]{2}"
              placeholder="00:04"
              required
            />
          </div>
          
          <div class="form-group">
            <label>Subtitle Text</label>
            <textarea 
              v-model="newEntryForm.text" 
              placeholder="Enter subtitle text..."
              required
            ></textarea>
          </div>
          
          <div class="form-actions">
            <button type="submit" class="save-btn">Add Subtitle</button>
            <button type="button" @click="cancelAdd" class="cancel-btn">Cancel</button>
          </div>
        </form>
      </div>
    </div>

    <div class="editor-footer">
      <div class="subtitle-stats">
        <span>{{ subtitleStore.subtitleCount }} subtitles</span>
        <span>Duration: {{ formatDuration(subtitleStore.totalDuration) }}</span>
      </div>
      <button @click="exportSubtitles" class="export-btn">Export Subtitles</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { useSubtitleStore } from '../stores/subtitle'
import { SubtitleService } from '../services/subtitle.service'
import type { SubtitleEntry } from '../types/subtitle'

const subtitleStore = useSubtitleStore()
const showAddForm = ref(false)

// Edit form data
const editForm = reactive({
  startTimeFormatted: '',
  endTimeFormatted: '',
  text: ''
})

// New entry form data
const newEntryForm = reactive({
  startTimeFormatted: '',
  endTimeFormatted: '',
  text: ''
})

onMounted(() => {
  // Load subtitles from session storage
  subtitleStore.loadFromSession()
  
  // Check if we have subtitles, if not redirect to home
  if (!subtitleStore.hasSubtitles) {
    router.visit('/')
  }
})

const editEntry = (entry: SubtitleEntry) => {
  subtitleStore.startEditing(entry)
  editForm.startTimeFormatted = entry.startTimeFormatted
  editForm.endTimeFormatted = entry.endTimeFormatted
  editForm.text = entry.text
}

const saveChanges = () => {
  if (!subtitleStore.editingEntry) return

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
  if (hasOverlappingSubtitles(startTime, endTime, subtitleStore.editingEntry.id)) {
    alert('This time range overlaps with another subtitle. Please adjust the timing.')
    return
  }

  const updatedEntry: SubtitleEntry = {
    ...subtitleStore.editingEntry,
    startTime,
    endTime,
    text: editForm.text.trim(),
    startTimeFormatted: editForm.startTimeFormatted,
    endTimeFormatted: editForm.endTimeFormatted
  }

  subtitleStore.updateEntry(updatedEntry)
  subtitleStore.stopEditing()
  subtitleStore.saveToSession()
}

const cancelEdit = () => {
  subtitleStore.stopEditing()
}

const addNewEntry = () => {
  showAddForm.value = true
  newEntryForm.startTimeFormatted = ''
  newEntryForm.endTimeFormatted = ''
  newEntryForm.text = ''
}

const addEntry = () => {
  // Validate time format
  if (!isValidTimeFormat(newEntryForm.startTimeFormatted) || !isValidTimeFormat(newEntryForm.endTimeFormatted)) {
    alert('Please enter time in MM:SS format (e.g., 00:30)')
    return
  }

  const startTime = SubtitleService.timeStringToSeconds(newEntryForm.startTimeFormatted)
  const endTime = SubtitleService.timeStringToSeconds(newEntryForm.endTimeFormatted)

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
    text: newEntryForm.text.trim(),
    startTimeFormatted: newEntryForm.startTimeFormatted,
    endTimeFormatted: newEntryForm.endTimeFormatted,
    styling: {
      size: 'medium',
      color: 'white',
      position: 'bottom'
    }
  }

  subtitleStore.addEntry(newEntry)
  subtitleStore.saveToSession()
  cancelAdd()
}

const cancelAdd = () => {
  showAddForm.value = false
  newEntryForm.startTimeFormatted = ''
  newEntryForm.endTimeFormatted = ''
  newEntryForm.text = ''
}

const removeEntry = (entryId: string) => {
  if (confirm('Are you sure you want to delete this subtitle?')) {
    subtitleStore.removeEntry(entryId)
    subtitleStore.saveToSession()
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
.subtitle-editor {
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

.add-button {
  padding: 10px 20px;
  background-color: #28a745;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  transition: background-color 0.3s;
}

.add-button:hover {
  background-color: #218838;
}

.back-button {
  padding: 10px 20px;
  background-color: #333;
  color: white;
  border: 2px solid #555;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s;
}

.back-button:hover {
  background-color: #444;
  border-color: #777;
}

.editor-content {
  display: flex;
  gap: 30px;
  margin-bottom: 30px;
}

.subtitle-list {
  flex: 1;
  max-height: 600px;
  overflow-y: auto;
}

.subtitle-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 15px;
  margin-bottom: 10px;
  background-color: #222;
  border: 2px solid #333;
  border-radius: 8px;
  transition: all 0.3s;
}

.subtitle-item:hover {
  border-color: #555;
}

.subtitle-item.editing {
  border-color: #007bff;
  background-color: #1a1a2e;
}

.subtitle-info {
  flex: 1;
}

.subtitle-number {
  font-size: 14px;
  color: #007bff;
  font-weight: 600;
  margin-bottom: 5px;
}

.subtitle-timing {
  font-size: 14px;
  color: #888;
  margin-bottom: 8px;
}

.subtitle-text {
  font-size: 16px;
  line-height: 1.4;
}

.subtitle-actions {
  display: flex;
  gap: 10px;
}

.edit-btn, .delete-btn {
  padding: 6px 12px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
  font-weight: 600;
  transition: all 0.3s;
}

.edit-btn {
  background-color: #007bff;
  color: white;
}

.edit-btn:hover {
  background-color: #0056b3;
}

.delete-btn {
  background-color: #dc3545;
  color: white;
}

.delete-btn:hover {
  background-color: #c82333;
}

.edit-form {
  width: 400px;
  background-color: #222;
  padding: 20px;
  border-radius: 8px;
  border: 2px solid #333;
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
  gap: 10px;
  justify-content: flex-end;
}

.save-btn {
  padding: 10px 20px;
  background-color: #28a745;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  transition: background-color 0.3s;
}

.save-btn:hover {
  background-color: #218838;
}

.cancel-btn {
  padding: 10px 20px;
  background-color: #6c757d;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  transition: background-color 0.3s;
}

.cancel-btn:hover {
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

.export-btn {
  padding: 10px 20px;
  background-color: #007bff;
  color: white;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
  transition: background-color 0.3s;
}

.export-btn:hover {
  background-color: #0056b3;
}

@media (max-width: 768px) {
  .editor-content {
    flex-direction: column;
  }
  
  .edit-form {
    width: 100%;
  }
  
  .header-actions {
    flex-direction: column;
    gap: 10px;
  }
}
</style>