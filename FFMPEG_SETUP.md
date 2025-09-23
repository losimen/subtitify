# FFmpeg Setup for Video Processing

This application uses FFmpeg to overlay subtitles on videos. You need to install FFmpeg on your system.

## Installation

### macOS (using Homebrew)
```bash
brew install ffmpeg
```

### Ubuntu/Debian
```bash
sudo apt update
sudo apt install ffmpeg
```

### Windows
1. Download FFmpeg from https://ffmpeg.org/download.html
2. Extract to a folder (e.g., `C:\ffmpeg`)
3. Add `C:\ffmpeg\bin` to your PATH environment variable

### Docker (if using Laravel Sail)
Add to your Dockerfile or docker-compose.yml:
```yaml
services:
  laravel.test:
    # ... other config
    command: |
      bash -c "
        apt-get update &&
        apt-get install -y ffmpeg &&
        /start.sh
      "
```

## Verify Installation

Run this command to verify FFmpeg is installed:
```bash
ffmpeg -version
```

## Update PHP-FFMpeg Configuration

If FFmpeg is installed in a non-standard location, update the paths in `app/Http/Controllers/VideoController.php`:

```php
$ffmpeg = FFMpeg::create([
    'ffmpeg.binaries'  => '/path/to/your/ffmpeg',  // Update this path
    'ffprobe.binaries' => '/path/to/your/ffprobe', // Update this path
]);
```

## Common FFmpeg Paths

- **macOS (Homebrew)**: `/opt/homebrew/bin/ffmpeg` and `/opt/homebrew/bin/ffprobe`
- **Linux**: `/usr/bin/ffmpeg` and `/usr/bin/ffprobe`
- **Windows**: `C:\ffmpeg\bin\ffmpeg.exe` and `C:\ffmpeg\bin\ffprobe.exe`

## Troubleshooting

1. **"FFmpeg not found"**: Make sure FFmpeg is installed and in your PATH
2. **Permission errors**: Ensure the storage/app/temp directory is writable
3. **Video format issues**: FFmpeg supports most formats, but some may need additional codecs

## Testing

After installation, test the video processing by:
1. Uploading a video file
2. Adding some subtitles
3. Clicking the Export button
4. The processed video should download automatically