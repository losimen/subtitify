export interface SubtitleEntry {
  id: string
  startTime: number // in seconds
  endTime: number // in seconds
  text: string
  startTimeFormatted: string // e.g., "00:00"
  endTimeFormatted: string // e.g., "00:04"
  styling: {
    size: 'small' | 'medium' | 'large'
    color: 'white' | 'black' | 'red' | 'blue' | 'green' | 'yellow' | 'orange' | 'purple' | 'cyan' | 'magenta'
    position: 'top' | 'center' | 'bottom'
  }
}

export interface SubtitleData {
  entries: SubtitleEntry[]
  totalDuration: number // in seconds
  source: string // original text or file name
}
