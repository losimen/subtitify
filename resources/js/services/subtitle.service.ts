import type { SubtitleEntry, SubtitleData } from '../types/subtitle'

export class SubtitleService {
  /**
   * Parses subtitle text in the format:
   * [00:00-00:04]
   * Text content here
   *
   * [00:05-00:08]
   * More text content
   */
  static parseSubtitleText(text: string, source: string = 'text'): SubtitleData {
    const lines = text.trim().split('\n')
    const entries: SubtitleEntry[] = []
    let totalDuration = 0

    for (let i = 0; i < lines.length; i++) {
      const line = lines[i].trim()

      // Skip empty lines
      if (!line) continue

      // Check if this line contains a time range like [00:00-00:04]
      const timeMatch = line.match(/\[(\d{2}:\d{2})-(\d{2}:\d{2})\]/)
      if (!timeMatch) continue

      const [, startTimeStr, endTimeStr] = timeMatch
      const startTime = this.timeStringToSeconds(startTimeStr)
      const endTime = this.timeStringToSeconds(endTimeStr)

      // Look for the text content in the next non-empty line
      let textContent = ''
      for (let j = i + 1; j < lines.length; j++) {
        const nextLine = lines[j].trim()
        if (nextLine) {
          textContent = nextLine
          break
        }
      }

      const entry: SubtitleEntry = {
        id: `subtitle-${entries.length + 1}`,
        startTime,
        endTime,
        text: textContent,
        startTimeFormatted: startTimeStr,
        endTimeFormatted: endTimeStr,
        styling: {
          size: 'medium',
          color: 'white',
          position: 'bottom'
        }
      }

      entries.push(entry)
      totalDuration = Math.max(totalDuration, endTime)
    }

    return {
      entries,
      totalDuration,
      source,
    }
  }

  /**
   * Converts time string (MM:SS) to seconds
   */
  static timeStringToSeconds(timeStr: string): number {
    const [minutes, seconds] = timeStr.split(':').map(Number)
    return minutes * 60 + seconds
  }

  /**
   * Converts seconds to time string (MM:SS)
   */
  static secondsToTimeString(seconds: number): string {
    const mins = Math.floor(seconds / 60)
    const secs = Math.floor(seconds % 60)
    return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
  }

  /**
   * Gets subtitle entry at a specific time
   */
  static getSubtitleAtTime(subtitles: SubtitleData, timeInSeconds: number): SubtitleEntry | null {
    return (
      subtitles.entries.find(
        (entry) => timeInSeconds >= entry.startTime && timeInSeconds <= entry.endTime,
      ) || null
    )
  }

  /**
   * Gets all subtitle entries within a time range
   */
  static getSubtitlesInRange(
    subtitles: SubtitleData,
    startTime: number,
    endTime: number,
  ): SubtitleEntry[] {
    return subtitles.entries.filter(
      (entry) =>
        (entry.startTime >= startTime && entry.startTime <= endTime) ||
        (entry.endTime >= startTime && entry.endTime <= endTime) ||
        (entry.startTime <= startTime && entry.endTime >= endTime),
    )
  }

  /**
   * Exports subtitle data back to text format
   */
  static exportToText(subtitles: SubtitleData): string {
    return subtitles.entries
      .map((entry) => `[${entry.startTimeFormatted}-${entry.endTimeFormatted}]\n${entry.text}`)
      .join('\n\n')
  }
}