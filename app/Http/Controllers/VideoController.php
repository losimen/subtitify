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
        try {
            // Validate request
            $request->validate([
                'file' => 'required|array',
                'subtitles' => 'required|array',
                'subtitles.entries' => 'required|array'
            ]);

            $fileData = $request->input('file');
            $subtitles = $request->input('subtitles.entries', []);

            // Handle file upload
            $videoPath = $this->handleFileUpload($fileData);

            // Create subtitle overlay
            $outputPath = $this->createSubtitleOverlay($videoPath, $subtitles);

            // Return download response
            return $this->downloadVideo($outputPath);

        } catch (\Exception $e) {
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
            mkdir($tempDir, 0755, true);
        }

        // Handle different file data formats
        if (isset($fileData['url']) && str_starts_with($fileData['url'], 'data:')) {
            // Handle base64 data URL
            $data = $fileData['url'];
            $data = explode(',', $data);
            $videoData = base64_decode($data[1]);

            // Determine file extension from MIME type
            $mimeType = explode(';', explode(':', $data[0])[1])[0];
            $extension = $this->getExtensionFromMimeType($mimeType);

            $filename = 'input_' . uniqid() . '.' . $extension;
            $filePath = $tempDir . '/' . $filename;

            file_put_contents($filePath, $videoData);

        } elseif (isset($fileData['name']) && isset($fileData['content'])) {
            // Handle file content
            $extension = pathinfo($fileData['name'], PATHINFO_EXTENSION);
            $filename = 'input_' . uniqid() . '.' . $extension;
            $filePath = $tempDir . '/' . $filename;

            file_put_contents($filePath, base64_decode($fileData['content']));

        } else {
            throw new \Exception('Invalid file data format');
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
            'ffmpeg.binaries'  => '/opt/homebrew/bin/ffmpeg', // Adjust path as needed
            'ffprobe.binaries' => '/opt/homebrew/bin/ffprobe', // Adjust path as needed
        ]);

        $video = $ffmpeg->open($videoPath);

        // Create output filename
        $outputPath = storage_path('app/temp/output_' . uniqid() . '.mp4');

        // Create subtitle filter
        $subtitleFilter = $this->buildSubtitleFilter($subtitles);

        // Apply subtitle overlay
        $video->filters()->custom('drawtext', $subtitleFilter);

        // Set output format
        $format = new X264();
        $format->setAudioCodec('aac');

        // Save video
        $video->save($format, $outputPath);

        return $outputPath;
    }

    private function buildSubtitleFilter($subtitles)
    {
        $filter = '';

        foreach ($subtitles as $index => $subtitle) {
            $startTime = $this->secondsToTimeCode($subtitle['startTime']);
            $endTime = $this->secondsToTimeCode($subtitle['endTime']);

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

            // Escape text for filter
            $text = addslashes($subtitle['text']);

            // Create drawtext filter for this subtitle
            $drawText = sprintf(
                "drawtext=text='%s':fontsize=%d:fontcolor=%s:x=%s:y=%s:enable='between(t,%s,%s)'",
                $text,
                $fontSize,
                $color,
                $position['x'],
                $position['y'],
                $subtitle['startTime'],
                $subtitle['endTime']
            );

            if ($index > 0) {
                $filter .= ',';
            }
            $filter .= $drawText;
        }

        return $filter;
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
            'small' => 24,
            'medium' => 32,
            'large' => 48,
            default => 32
        };
    }

    private function getColor($color)
    {
        return match($color) {
            'white' => 'white',
            'black' => 'black',
            'red' => 'red',
            default => 'white'
        };
    }

    private function getPosition($position)
    {
        return match($position) {
            'top' => ['x' => '(w-text_w)/2', 'y' => '50'],
            'center' => ['x' => '(w-text_w)/2', 'y' => '(h-text_h)/2'],
            'bottom' => ['x' => '(w-text_w)/2', 'y' => 'h-text_h-50'],
            default => ['x' => '(w-text_w)/2', 'y' => 'h-text_h-50']
        };
    }

    private function downloadVideo($filePath)
    {
        if (!file_exists($filePath)) {
            throw new \Exception('Output file not found');
        }

        $filename = 'subtitled_video_' . date('Y-m-d_H-i-s') . '.mp4';

        return response()->download($filePath, $filename)->deleteFileAfterSend(true);
    }
}
