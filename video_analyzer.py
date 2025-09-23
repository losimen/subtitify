#!/usr/bin/env python3
"""
Video Analyzer CLI Tool
Uses Google Gemini API to analyze video content based on a custom prompt.
"""

import argparse
import os
import sys
import tempfile
import shutil
import random
from pathlib import Path
import google.generativeai as genai
from google.generativeai.types import HarmCategory, HarmBlockThreshold
import subprocess


def setup_gemini_api(api_key: str = None):
    """Setup Google Gemini API with the provided API key."""
    if api_key is None:
        api_key = os.getenv('GEMINI_API_KEY')
    
    if not api_key:
        print("Error: Gemini API key not found!")
        print("Please set GEMINI_API_KEY environment variable or provide it via --api-key")
        sys.exit(1)
    
    genai.configure(api_key=api_key)
    return genai.GenerativeModel('gemini-1.5-pro')


def parse_time_to_seconds(time_str: str) -> float:
    """Parse time string (HH:MM:SS or MM:SS or SS) to seconds."""
    try:
        parts = time_str.split(':')
        if len(parts) == 3:  # HH:MM:SS
            hours, minutes, seconds = map(float, parts)
            return hours * 3600 + minutes * 60 + seconds
        elif len(parts) == 2:  # MM:SS
            minutes, seconds = map(float, parts)
            return minutes * 60 + seconds
        else:  # SS
            return float(time_str)
    except ValueError:
        raise ValueError(f"Invalid time format: {time_str}. Use HH:MM:SS, MM:SS, or SS format")


def trim_video(input_path: str, start_time: float, end_time: float, output_path: str, fast_mode: bool = False) -> bool:
    """Trim video using ffmpeg."""
    try:
        duration = end_time - start_time
        
        if fast_mode:
            # Fast mode: stream copy (may have black frames but much faster)
            cmd = [
                'ffmpeg',
                '-ss', str(start_time),
                '-i', input_path,
                '-t', str(duration),
                '-c', 'copy',  # Copy streams without re-encoding
                '-avoid_negative_ts', 'make_zero',
                '-y',
                output_path
            ]
            print(f"Trimming video from {start_time}s to {end_time}s (fast mode)...")
        else:
            # Quality mode: re-encode to avoid black frames
            cmd = [
                'ffmpeg',
                '-ss', str(start_time),  # Seek to start time BEFORE input
                '-i', input_path,
                '-t', str(duration),
                '-c:v', 'libx264',  # Re-encode video to avoid black frames
                '-c:a', 'aac',      # Re-encode audio
                '-preset', 'fast',   # Fast encoding preset
                '-crf', '23',       # Good quality/size balance
                '-avoid_negative_ts', 'make_zero',
                '-y',  # Overwrite output file
                output_path
            ]
            print(f"Trimming video from {start_time}s to {end_time}s...")
            print("Note: Re-encoding video to avoid black frames (this may take longer)...")
        
        result = subprocess.run(cmd, capture_output=True, text=True)
        
        if result.returncode != 0:
            print(f"FFmpeg error: {result.stderr}")
            return False
        
        return True
        
    except FileNotFoundError:
        print("Error: ffmpeg not found. Please install ffmpeg.")
        return False
    except Exception as e:
        print(f"Error trimming video: {str(e)}")
        return False


def validate_video_file(file_path: str) -> bool:
    """Validate that the provided file exists and is a video file."""
    path = Path(file_path)
    
    if not path.exists():
        print(f"Error: File '{file_path}' does not exist.")
        return False
    
    if not path.is_file():
        print(f"Error: '{file_path}' is not a file.")
        return False
    
    # Check for common video file extensions
    video_extensions = {'.mp4', '.avi', '.mov', '.mkv', '.wmv', '.flv', '.webm', '.m4v', '.3gp', '.mpg', '.mpeg'}
    if path.suffix.lower() not in video_extensions:
        print(f"Warning: '{file_path}' may not be a video file. Supported formats: {', '.join(video_extensions)}")
    
    return True


def generate_random_cta(file_path: str, start_time: float = None, end_time: float = None, fast_trim: bool = False) -> str:
    """Generate a random call-to-action phrase locally without uploading to Gemini."""
    temp_file = None
    try:
        # Handle video trimming if start/end times are provided
        if start_time is not None and end_time is not None:
            # Create trimmed file in the same directory as the script
            script_dir = Path(__file__).parent
            original_name = Path(file_path).stem
            original_ext = Path(file_path).suffix
            trimmed_filename = f"{original_name}_trimmed_{int(start_time)}s_to_{int(end_time)}s{original_ext}"
            temp_file = script_dir / trimmed_filename
            
            # Trim the video
            if not trim_video(file_path, start_time, end_time, str(temp_file), fast_trim):
                return None
            
            print(f"Trimmed video saved as: {temp_file}")
        
        # Generate random call-to-action phrase locally
        print("Generating random call-to-action phrase...")
        
        # List of common call-to-action phrases
        cta_phrases = [
            "Download Now",
            "Free Content", 
            "Try Today",
            "Get Started",
            "Learn More",
            "Sign Up",
            "Buy Now",
            "Join Today",
            "Start Free",
            "Watch Now",
            "Click Here",
            "Explore More",
            "Get Access",
            "Subscribe Now",
            "Limited Time",
            "Don't Miss",
            "Act Fast",
            "Save Today",
            "Exclusive Deal",
            "Premium Access",
            "Instant Access",
            "Free Trial",
            "No Risk",
            "Guaranteed Results",
            "Proven Method",
            "Expert Tips",
            "Step By Step",
            "Easy Setup",
            "Quick Start",
            "Begin Today"
        ]
        
        # Select a random phrase
        selected_cta = random.choice(cta_phrases)
        
        return selected_cta
        
    except Exception as e:
        print(f"Error generating call-to-action: {str(e)}")
        return None
    finally:
        # Note: Trimmed files are saved in the script directory and not automatically deleted
        # User can manually delete them if needed
        if temp_file and os.path.exists(temp_file):
            print("Note: Trimmed video file is preserved. Delete manually if not needed.")


def analyze_video(model, file_path: str, prompt: str, start_time: float = None, end_time: float = None, fast_trim: bool = False) -> str:
    """Analyze video content using Gemini API."""
    temp_file = None
    try:
        # Handle video trimming if start/end times are provided
        if start_time is not None and end_time is not None:
            # Create trimmed file in the same directory as the script
            script_dir = Path(__file__).parent
            original_name = Path(file_path).stem
            original_ext = Path(file_path).suffix
            trimmed_filename = f"{original_name}_trimmed_{int(start_time)}s_to_{int(end_time)}s{original_ext}"
            temp_file = script_dir / trimmed_filename
            
            # Trim the video
            if not trim_video(file_path, start_time, end_time, str(temp_file), fast_trim):
                return None
            
            # Use the trimmed video for analysis
            analysis_file = str(temp_file)
            print(f"Using trimmed video: {analysis_file}")
        else:
            analysis_file = file_path
        
        # Upload the video file
        print(f"Uploading video file: {analysis_file}")
        video_file = genai.upload_file(path=analysis_file)
        
        # Wait for the file to be processed
        print("Processing video file...")
        while video_file.state.name == "PROCESSING":
            print(".", end="", flush=True)
            import time
            time.sleep(2)
            # Refresh the file state from the server
            video_file = genai.get_file(video_file.name)
        
        if video_file.state.name == "FAILED":
            print(f"\nError: Video processing failed: {video_file.state.name}")
            return None
        
        print("\nVideo processed successfully!")
        
        # Generate content with the custom prompt
        print("Analyzing video content...")
        response = model.generate_content([
            f"Please analyze this video and {prompt}",
            video_file
        ], safety_settings={
            HarmCategory.HARM_CATEGORY_HATE_SPEECH: HarmBlockThreshold.BLOCK_NONE,
            HarmCategory.HARM_CATEGORY_HARASSMENT: HarmBlockThreshold.BLOCK_NONE,
            HarmCategory.HARM_CATEGORY_SEXUALLY_EXPLICIT: HarmBlockThreshold.BLOCK_NONE,
            HarmCategory.HARM_CATEGORY_DANGEROUS_CONTENT: HarmBlockThreshold.BLOCK_NONE,
        })
        
        # Clean up the uploaded file
        genai.delete_file(video_file.name)
        
        return response.text
        
    except Exception as e:
        print(f"Error analyzing video: {str(e)}")
        return None
    finally:
        # Note: Trimmed files are saved in the script directory and not automatically deleted
        # User can manually delete them if needed
        if temp_file and os.path.exists(temp_file):
            print(f"Trimmed video saved as: {temp_file}")
            print("Note: Trimmed video file is preserved. Delete manually if not needed.")


def main():
    """Main CLI function."""
    parser = argparse.ArgumentParser(
        description="Analyze video content using Google Gemini API",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  # Analysis mode (default)
  python video_analyzer.py video.mp4 "describe what happens in this video"
  python video_analyzer.py --api-key YOUR_KEY video.mp4 "summarize the main events"
  python video_analyzer.py video.mp4 "identify all the people in this video"
  python video_analyzer.py --start-time 00:01:30 --end-time 00:02:45 video.mp4 "analyze this scene"
  python video_analyzer.py --start-time 30 --end-time 90 video.mp4 "describe what happens"
  python video_analyzer.py --start-time 30 --end-time 90 --fast-trim video.mp4 "quick analysis"
  
  # Random call-to-action mode
  python video_analyzer.py --mode random video.mp4
  python video_analyzer.py --mode random --start-time 30 --end-time 90 video.mp4
  python video_analyzer.py --mode random --start-time 00:01:30 --end-time 00:02:45 video.mp4
        """
    )
    
    parser.add_argument(
        'file_path',
        help='Path to the video file to analyze'
    )
    
    parser.add_argument(
        'prompt',
        nargs='?',
        help='Custom prompt for video analysis (required for analysis mode, ignored for random mode)'
    )
    
    parser.add_argument(
        '--mode',
        choices=['analysis', 'random'],
        default='analysis',
        help='Mode: analysis (default) or random call-to-action generation'
    )
    
    parser.add_argument(
        '--start-time',
        help='Start time for video trimming (format: HH:MM:SS, MM:SS, or SS)'
    )
    
    parser.add_argument(
        '--end-time',
        help='End time for video trimming (format: HH:MM:SS, MM:SS, or SS)'
    )
    
    parser.add_argument(
        '--fast-trim',
        action='store_true',
        help='Use fast trimming (stream copy) - may have black frames at start but much faster'
    )
    
    parser.add_argument(
        '--api-key',
        help='Google Gemini API key (can also be set via GEMINI_API_KEY environment variable)'
    )
    
    parser.add_argument(
        '--output',
        '-o',
        help='Output file to save the analysis results (optional)'
    )
    
    args = parser.parse_args()
    
    # Validate mode and prompt requirements
    if args.mode == 'analysis' and not args.prompt:
        print("Error: Prompt is required for analysis mode.")
        print("Usage: python video_analyzer.py video.mp4 \"your prompt here\"")
        print("Or use random mode: python video_analyzer.py --mode random video.mp4")
        sys.exit(1)
    
    # Parse and validate time parameters
    start_time = None
    end_time = None
    
    if args.start_time or args.end_time:
        if not (args.start_time and args.end_time):
            print("Error: Both --start-time and --end-time must be provided together.")
            sys.exit(1)
        
        try:
            start_time = parse_time_to_seconds(args.start_time)
            end_time = parse_time_to_seconds(args.end_time)
            
            if start_time >= end_time:
                print("Error: Start time must be less than end time.")
                sys.exit(1)
            
            print(f"Video will be trimmed from {args.start_time} to {args.end_time}")
            
        except ValueError as e:
            print(f"Error: {str(e)}")
            sys.exit(1)
    
    # Validate input file
    if not validate_video_file(args.file_path):
        sys.exit(1)
    
    # Process video based on mode
    if args.mode == 'analysis':
        # Setup Gemini API for analysis mode
        try:
            model = setup_gemini_api(args.api_key)
        except Exception as e:
            print(f"Error setting up Gemini API: {str(e)}")
            sys.exit(1)
        
        result = analyze_video(model, args.file_path, args.prompt, start_time, end_time, args.fast_trim)
        if result is None:
            print("Failed to analyze video.")
            sys.exit(1)
        
        # Output results
        print("\n" + "="*50)
        print("ANALYSIS RESULT:")
        print("="*50)
        print(result)
        
    elif args.mode == 'random':
        # Random mode doesn't need Gemini API
        result = generate_random_cta(args.file_path, start_time, end_time, args.fast_trim)
        if result is None:
            print("Failed to generate call-to-action.")
            sys.exit(1)
        
        # Output results
        print("\n" + "="*50)
        print("CALL-TO-ACTION:")
        print("="*50)
        print(result)
    
    # Save to file if requested
    if args.output:
        try:
            with open(args.output, 'w', encoding='utf-8') as f:
                if args.mode == 'analysis':
                    f.write(f"Video Analysis Results\n")
                    f.write(f"File: {args.file_path}\n")
                    f.write(f"Prompt: {args.prompt}\n")
                    f.write(f"{'='*50}\n\n")
                    f.write(result)
                else:  # random mode
                    f.write(f"Call-to-Action Generation Results\n")
                    f.write(f"File: {args.file_path}\n")
                    f.write(f"Mode: Random CTA Generation\n")
                    f.write(f"{'='*50}\n\n")
                    f.write(result)
            print(f"\nResults saved to: {args.output}")
        except Exception as e:
            print(f"Error saving results to file: {str(e)}")


if __name__ == "__main__":
    main()

# cursor-agent --resume=e5b36f40-a6f8-4c4d-ba06-99725f24d5d4