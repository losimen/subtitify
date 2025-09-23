<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Format\Video\X264;

class VideoController extends Controller
{
    public function processVideoWithSubtitles(Request $request)
    {
        $videoPath = null;
        $outputPath = null;

        try {
            // Validate request
            $request->validate([
                'file' => 'required|array',
                'subtitles' => 'required|array',
                'subtitles.entries' => 'required|array'
            ]);

            $fileData = $request->input('file');
            $subtitles = $request->input('subtitles.entries', []);

            // Log the request for debugging
            \Log::info('Processing video with subtitles', [
                'file_data_keys' => array_keys($fileData),
                'subtitle_count' => count($subtitles)
            ]);

            // Check FFmpeg installation
            $this->checkFFmpegInstallation();

            // Handle file upload
            $videoPath = $this->handleFileUpload($fileData);
            \Log::info('Video file uploaded successfully', ['path' => $videoPath]);

            // Create subtitle overlay
            $outputPath = $this->createSubtitleOverlay($videoPath, $subtitles);
            \Log::info('Subtitle overlay created successfully', ['output_path' => $outputPath]);

            // Return download response
            return $this->downloadVideo($outputPath);

        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Validation error in video processing', ['errors' => $e->errors()]);
            // Let Laravel handle validation errors in the standard format
            throw $e;
        } catch (\Exception $e) {
            \Log::error('Video processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Clean up files if they exist
            if ($videoPath && file_exists($videoPath)) {
                unlink($videoPath);
            }
            if ($outputPath && file_exists($outputPath)) {
                unlink($outputPath);
            }

            return response()->json([
                'error' => 'Video processing failed: ' . $e->getMessage()
            ], 500);
        }
    }

    private function handleFileUpload($fileData)
    {
        // Create temporary directory
        $tempDir = storage_path('app/temp');
        if (!file_exists($tempDir)) {
            if (!mkdir($tempDir, 0755, true)) {
                throw new \Exception('Failed to create temporary directory');
            }
        }

        // Handle different file data formats
        if (isset($fileData['url']) && str_starts_with($fileData['url'], 'data:')) {
            // Handle base64 data URL
            $data = $fileData['url'];
            $parts = explode(',', $data, 2);
            
            if (count($parts) !== 2) {
                throw new \Exception('Invalid data URL format');
            }

            $videoData = base64_decode($parts[1]);
            if ($videoData === false) {
                throw new \Exception('Failed to decode base64 video data');
            }

            // Determine file extension from MIME type
            $mimeType = explode(';', explode(':', $parts[0])[1])[0];
            $extension = $this->getExtensionFromMimeType($mimeType);

            $filename = 'input_' . uniqid() . '.' . $extension;
            $filePath = $tempDir . '/' . $filename;

            if (file_put_contents($filePath, $videoData) === false) {
                throw new \Exception('Failed to write video file to disk');
            }

        } elseif (isset($fileData['name']) && isset($fileData['content'])) {
            // Handle file content
            $extension = pathinfo($fileData['name'], PATHINFO_EXTENSION);
            if (empty($extension)) {
                $extension = 'mp4'; // Default extension
            }

            $filename = 'input_' . uniqid() . '.' . $extension;
            $filePath = $tempDir . '/' . $filename;

            $videoData = base64_decode($fileData['content']);
            if ($videoData === false) {
                throw new \Exception('Failed to decode base64 video content');
            }

            if (file_put_contents($filePath, $videoData) === false) {
                throw new \Exception('Failed to write video file to disk');
            }

        } else {
            throw new \Exception('Invalid file data format. Expected "url" or "name"/"content" fields.');
        }

        // Verify the file was created and has content
        if (!file_exists($filePath) || filesize($filePath) === 0) {
            throw new \Exception('Video file was not created properly');
        }

        return $filePath;
    }

    private function getExtensionFromMimeType($mimeType)
    {
        $mimeToExt = [
            'video/mp4' => 'mp4',
            'video/avi' => 'avi',
            'video/mov' => 'mov',
            'video/wmv' => 'wmv',
            'video/webm' => 'webm',
            'video/mkv' => 'mkv'
        ];

        return $mimeToExt[$mimeType] ?? 'mp4';
    }

    private function createSubtitleOverlay($videoPath, $subtitles)
    {
        // Initialize FFmpeg
        $ffmpeg = FFMpeg::create([
            'ffmpeg.binaries'  => '/opt/homebrew/bin/ffmpeg',
            'ffprobe.binaries' => '/opt/homebrew/bin/ffprobe',
            'timeout'          => 3600, // 1 hour timeout
            'ffmpeg.threads'   => 12,   // Use multiple threads
        ]);

        $video = $ffmpeg->open($videoPath);

        // Create output filename
        $outputPath = storage_path('app/temp/output_' . uniqid() . '.mp4');

        // Create subtitle filter
        $subtitleFilter = $this->buildSubtitleFilter($subtitles);

        // Apply subtitle overlay using the correct method
        if (!empty($subtitleFilter)) {
            $video->filters()->custom($subtitleFilter);
        }

        // Set output format
        $format = new X264();
        $format->setAudioCodec('aac');
        $format->setVideoCodec('libx264');
        $format->setAdditionalParameters(['-preset', 'fast', '-crf', '23']);

        // Save video with error handling
        try {
            $video->save($format, $outputPath);
        } catch (\Exception $e) {
            // Clean up input file
            if (file_exists($videoPath)) {
                unlink($videoPath);
            }
            throw new \Exception('FFmpeg encoding failed: ' . $e->getMessage());
        }

        // Clean up input file
        if (file_exists($videoPath)) {
            unlink($videoPath);
        }

        return $outputPath;
    }

    private function buildSubtitleFilter($subtitles)
    {
        if (empty($subtitles)) {
            return '';
        }

        $filters = [];

        foreach ($subtitles as $index => $subtitle) {
            // Get styling
            $styling = $subtitle['styling'] ?? [
                'size' => 'medium',
                'color' => 'white',
                'position' => 'bottom'
            ];

            // Build font size
            $fontSize = $this->getFontSize($styling['size']);

            // Build color
            $color = $this->getColor($styling['color']);

            // Build position
            $position = $this->getPosition($styling['position']);

            // Escape text for filter - handle special characters
            $text = $this->escapeTextForFilter($subtitle['text']);

            // Get enhanced styling based on color
            $stylingOptions = $this->getEnhancedStyling($styling['color']);

            // Create drawtext filter for this subtitle with enhanced styling
            $drawText = sprintf(
                "drawtext=text='%s':fontsize=%d:fontcolor=%s:x=%s:y=%s:enable='between(t,%s,%s)%s'",
                $text,
                $fontSize,
                $color,
                $position['x'],
                $position['y'],
                $subtitle['startTime'],
                $subtitle['endTime'],
                $stylingOptions
            );

            $filters[] = $drawText;
        }

        // Join all filters with commas
        return implode(',', $filters);
    }

    private function escapeTextForFilter($text)
    {
        // Escape special characters for FFmpeg filter
        $text = str_replace("'", "\\'", $text);
        $text = str_replace(':', '\\:', $text);
        $text = str_replace('[', '\\[', $text);
        $text = str_replace(']', '\\]', $text);
        $text = str_replace(',', '\\,', $text);
        $text = str_replace(';', '\\;', $text);
        $text = str_replace('\\', '\\\\', $text);
        
        return $text;
    }

    private function secondsToTimeCode($seconds)
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        return sprintf('%02d:%02d:%05.2f', $hours, $minutes, $secs);
    }

    private function getFontSize($size)
    {
        return match($size) {
            'small' => 20,
            'medium' => 28,
            'large' => 40,
            default => 28
        };
    }

    private function getColor($color)
    {
        return match($color) {
            'white' => 'white',
            'black' => 'black',
            'red' => 'red',
            'blue' => 'blue',
            'green' => 'green',
            'yellow' => 'yellow',
            'orange' => 'orange',
            'purple' => 'purple',
            'cyan' => 'cyan',
            'magenta' => 'magenta',
            default => 'white'
        };
    }

    private function getPosition($position)
    {
        return match($position) {
            'top' => ['x' => '(w-text_w)/2', 'y' => 'h*0.1'],
            'center' => ['x' => '(w-text_w)/2', 'y' => '(h-text_h)/2'],
            'bottom' => ['x' => '(w-text_w)/2', 'y' => 'h-text_h-h*0.1'],
            default => ['x' => '(w-text_w)/2', 'y' => 'h-text_h-h*0.1']
        };
    }

    private function getEnhancedStyling($color)
    {
        // Get appropriate font file path
        $fontFile = $this->getFontFile();
        
        // Return appropriate shadow and outline styling based on text color
        // Includes font smoothing and better rendering
        return match($color) {
            'white' => ":shadowcolor=black:shadowx=2:shadowy=2:box=1:boxcolor=black@0.7:boxborderw=8{$fontFile}",
            'black' => ":shadowcolor=white:shadowx=2:shadowy=2:box=1:boxcolor=white@0.7:boxborderw=8{$fontFile}",
            'red' => ":shadowcolor=black:shadowx=2:shadowy=2:box=1:boxcolor=black@0.6:boxborderw=6{$fontFile}",
            'blue' => ":shadowcolor=black:shadowx=2:shadowy=2:box=1:boxcolor=black@0.6:boxborderw=6{$fontFile}",
            'green' => ":shadowcolor=black:shadowx=2:shadowy=2:box=1:boxcolor=black@0.6:boxborderw=6{$fontFile}",
            'yellow' => ":shadowcolor=black:shadowx=2:shadowy=2:box=1:boxcolor=black@0.7:boxborderw=8{$fontFile}",
            'orange' => ":shadowcolor=black:shadowx=2:shadowy=2:box=1:boxcolor=black@0.6:boxborderw=6{$fontFile}",
            'purple' => ":shadowcolor=black:shadowx=2:shadowy=2:box=1:boxcolor=black@0.6:boxborderw=6{$fontFile}",
            'cyan' => ":shadowcolor=black:shadowx=2:shadowy=2:box=1:boxcolor=black@0.6:boxborderw=6{$fontFile}",
            'magenta' => ":shadowcolor=black:shadowx=2:shadowy=2:box=1:boxcolor=black@0.6:boxborderw=6{$fontFile}",
            default => ":shadowcolor=black:shadowx=2:shadowy=2:box=1:boxcolor=black@0.7:boxborderw=8{$fontFile}"
        };
    }

    private function getFontFile()
    {
        // Try to find a suitable font file
        $fontPaths = [
            '/System/Library/Fonts/Arial.ttf',           // macOS
            '/System/Library/Fonts/Helvetica.ttc',      // macOS alternative
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', // Linux
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf', // Linux alternative
            'C:\\Windows\\Fonts\\arial.ttf',            // Windows
            'C:\\Windows\\Fonts\\calibri.ttf',           // Windows alternative
        ];

        foreach ($fontPaths as $path) {
            if (file_exists($path)) {
                return ":fontfile={$path}";
            }
        }

        // If no font file found, return empty string (FFmpeg will use default)
        return '';
    }

    private function downloadVideo($filePath)
    {
        if (!file_exists($filePath)) {
            throw new \Exception('Output file not found');
        }

        $fileSize = filesize($filePath);
        if ($fileSize === 0) {
            throw new \Exception('Output file is empty');
        }

        $filename = 'subtitled_video_' . date('Y-m-d_H-i-s') . '.mp4';

        \Log::info('Preparing video download', [
            'file_path' => $filePath,
            'file_size' => $fileSize,
            'filename' => $filename
        ]);

        return response()->download($filePath, $filename)->deleteFileAfterSend(true);
    }

    private function checkFFmpegInstallation()
    {
        $ffmpegPath = '/opt/homebrew/bin/ffmpeg';
        $ffprobePath = '/opt/homebrew/bin/ffprobe';

        if (!file_exists($ffmpegPath)) {
            throw new \Exception('FFmpeg binary not found at: ' . $ffmpegPath);
        }

        if (!file_exists($ffprobePath)) {
            throw new \Exception('FFprobe binary not found at: ' . $ffprobePath);
        }

        // Test if binaries are executable
        if (!is_executable($ffmpegPath)) {
            throw new \Exception('FFmpeg binary is not executable: ' . $ffmpegPath);
        }

        if (!is_executable($ffprobePath)) {
            throw new \Exception('FFprobe binary is not executable: ' . $ffprobePath);
        }

        return true;
    }
}
