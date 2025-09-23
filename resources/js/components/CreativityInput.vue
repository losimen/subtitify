<template>
  <div class="creativity-input">
    <div class="input-container">
      <div class="file-section">
        <input type="file" ref="fileInput" @change="handleFileChange" accept=".mp4,video/mp4" class="file-input" />
        <button @click="handleStart" class="start-button" :disabled="!selectedFile">
          Start
        </button>
      </div>

    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const fileInput = ref<HTMLInputElement | null>(null)
const selectedFile = ref<File | null>(null)

const handleFileChange = (event: Event) => {
  const target = event.target as HTMLInputElement
  if (target.files && target.files[0]) {
    selectedFile.value = target.files[0]
  }
}


const handleStart = async () => {
  if (selectedFile.value) {
    try {
      // Convert file to base64 for persistent storage
      const base64Data = await fileToBase64(selectedFile.value)

      // Store file data in sessionStorage with base64 data
      const fileData = {
        url: base64Data,
        name: selectedFile.value.name,
        size: selectedFile.value.size,
        type: selectedFile.value.type
      }
      sessionStorage.setItem('uploadedFile', JSON.stringify(fileData))

      // Navigate to the subtitle component
      router.visit('/subtitle')
    } catch (error) {
      console.error('Error processing file:', error)
      alert('Error processing video file. Please try again.')
    }
  }
}

// Helper function to convert file to base64
const fileToBase64 = (file: File): Promise<string> => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => {
      if (typeof reader.result === 'string') {
        resolve(reader.result)
      } else {
        reject(new Error('Failed to convert file to base64'))
      }
    }
    reader.onerror = () => reject(reader.error)
    reader.readAsDataURL(file)
  })
}
</script>

<style scoped>
.creativity-input {
  min-height: 100vh;
  background-color: black;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0;
  padding: 0;
}

.input-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 30px;
  max-width: 800px;
  width: 100%;
  padding: 20px;
}

.file-section {
  display: flex;
  align-items: center;
  gap: 20px;
}


.file-input {
  padding: 12px 20px;
  background-color: #333;
  color: white;
  border: 2px solid #555;
  border-radius: 8px;
  cursor: pointer;
  font-size: 16px;
  transition: all 0.3s ease;
}

.file-input:hover {
  background-color: #444;
  border-color: #777;
}

.file-input::-webkit-file-upload-button {
  background-color: #555;
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 4px;
  cursor: pointer;
  margin-right: 10px;
}

.file-input::-webkit-file-upload-button:hover {
  background-color: #666;
}

.start-button {
  padding: 12px 30px;
  background-color: #007bff;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 16px;
  font-weight: 600;
  transition: all 0.3s ease;
}

.start-button:hover:not(:disabled) {
  background-color: #0056b3;
  transform: translateY(-2px);
}

.start-button:disabled {
  background-color: #555;
  cursor: not-allowed;
  opacity: 0.6;
}

.start-button:active:not(:disabled) {
  transform: translateY(0);
}
</style>