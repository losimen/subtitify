<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CreativityController extends Controller
{
    /**
     * Generate contextual text or CTA based on video content and theme
     */
    public function generateCreativePhrase(Request $request): JsonResponse
    {
        try {
            // Log incoming request data for debugging
            Log::info('Creativity API Request', [
                'has_video' => $request->hasFile('video'),
                'startTime' => $request->input('startTime'),
                'endTime' => $request->input('endTime'),
                'textTheme' => $request->input('textTheme'),
                'style' => $request->input('style'),
                'context' => $request->input('context')
            ]);

            // Validate request (similar to VideoController)
            $request->validate([
                'file' => 'required|array',
                'startTime' => 'required|numeric|min:0',
                'endTime' => 'required|numeric|min:0',
                'textTheme' => 'required|string|in:contextual,cta',
                'context' => 'nullable|string|max:500',
                'style' => 'nullable|string|in:professional,casual,funny,inspirational,technical'
            ]);

            $fileData = $request->input('file');
            $startTime = $request->input('startTime');
            $endTime = $request->input('endTime');
            $textTheme = $request->input('textTheme');
            $context = $request->input('context', '') ?? '';
            $style = $request->input('style', 'professional');

            // Additional validation: endTime must be greater than startTime
            if ($endTime <= $startTime) {
                return response()->json([
                    'success' => false,
                    'error' => 'End time must be greater than start time'
                ], 422);
            }

            // Analyze video content at the specified time range
            $videoAnalysis = $this->analyzeVideoContent($fileData, $startTime, $endTime);

            // Generate contextual text or CTA based on theme
            $generatedText = $this->generateContextualText($videoAnalysis, $textTheme, $context, $style);

            return response()->json([
                'success' => true,
                'text' => $generatedText,
                'metadata' => [
                    'textTheme' => $textTheme,
                    'style' => $style,
                    'startTime' => $startTime,
                    'endTime' => $endTime,
                    'duration' => $endTime - $startTime,
                    'generated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error in creative text generation', ['errors' => $e->errors()]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Creative text generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to generate creative text: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Analyze video content at specified time range
     */
    private function analyzeVideoContent($fileData, float $startTime, float $endTime): array
    {
        try {
            // Handle file data similar to VideoController
            $videoPath = $this->handleFileUpload($fileData);

            // Extract frame at middle of time range for analysis
            $middleTime = ($startTime + $endTime) / 2;
            $framePath = $this->extractFrameAtTime($videoPath, $middleTime);

            // Analyze the frame (placeholder for now - could integrate with image analysis APIs)
            $analysis = $this->analyzeFrame($framePath);

            // Clean up temporary files
            if (file_exists($videoPath)) {
                unlink($videoPath);
            }
            if (file_exists($framePath)) {
                unlink($framePath);
            }

            return $analysis;

        } catch (\Exception $e) {
            Log::error('Video analysis failed', ['error' => $e->getMessage()]);
            // Return default analysis if video processing fails
            return [
                'scene_type' => 'general',
                'mood' => 'neutral',
                'objects' => [],
                'colors' => ['neutral'],
                'activity_level' => 'medium'
            ];
        }
    }

    /**
     * Handle file upload similar to VideoController
     */
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

    /**
     * Get file extension from MIME type
     */
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

    /**
     * Extract frame at specific time from video
     */
    private function extractFrameAtTime(string $videoPath, float $time): string
    {
        $outputPath = storage_path('app/temp/frame_' . uniqid() . '.jpg');
        
        // Ensure temp directory exists
        $tempDir = dirname($outputPath);
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // Use FFmpeg to extract frame
        $command = sprintf(
            'ffmpeg -i %s -ss %s -vframes 1 -q:v 2 %s 2>/dev/null',
            escapeshellarg($videoPath),
            $time,
            escapeshellarg($outputPath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0 || !file_exists($outputPath)) {
            throw new \Exception('Failed to extract frame from video');
        }

        return $outputPath;
    }

    /**
     * Analyze extracted frame (placeholder implementation)
     */
    private function analyzeFrame(string $framePath): array
    {
        // This is a placeholder implementation
        // In a real scenario, you would integrate with image analysis APIs like:
        // - Google Vision API
        // - AWS Rekognition
        // - Azure Computer Vision
        // - Custom ML models

        // For now, return a mock analysis based on common video scenarios
        $scenarios = [
            [
                'scene_type' => 'product_demo',
                'mood' => 'professional',
                'objects' => ['product', 'hands', 'background'],
                'colors' => ['blue', 'white', 'gray'],
                'activity_level' => 'low'
            ],
            [
                'scene_type' => 'lifestyle',
                'mood' => 'energetic',
                'objects' => ['people', 'outdoor', 'movement'],
                'colors' => ['bright', 'vibrant', 'natural'],
                'activity_level' => 'high'
            ],
            [
                'scene_type' => 'tutorial',
                'mood' => 'educational',
                'objects' => ['screen', 'text', 'interface'],
                'colors' => ['neutral', 'contrast'],
                'activity_level' => 'medium'
            ],
            [
                'scene_type' => 'testimonial',
                'mood' => 'trustworthy',
                'objects' => ['person', 'face', 'background'],
                'colors' => ['warm', 'professional'],
                'activity_level' => 'low'
            ]
        ];

        return $scenarios[array_rand($scenarios)];
    }

    /**
     * Generate contextual text based on video analysis and theme
     */
    private function generateContextualText(array $videoAnalysis, string $textTheme, string $context, string $style): string
    {
        if ($textTheme === 'cta') {
            return $this->generateCTA($videoAnalysis, $context, $style);
        } else {
            return $this->generateContextualLine($videoAnalysis, $context, $style);
        }
    }

    /**
     * Generate CTA (Call-to-Action) text based on video analysis
     */
    private function generateCTA(array $videoAnalysis, string $context, string $style): string
    {
        $sceneType = $videoAnalysis['scene_type'];
        $mood = $videoAnalysis['mood'];
        $activityLevel = $videoAnalysis['activity_level'];

        // CTA templates based on scene type and mood
        $ctaTemplates = [
            'product_demo' => [
                'professional' => [
                    'Get Started Today',
                    'Try It Free',
                    'Download Now',
                    'Learn More',
                    'Get Your Copy',
                    'Start Free Trial',
                    'Buy Now',
                    'Order Today'
                ],
                'casual' => [
                    'Give It a Try',
                    'Check It Out',
                    'Get Started',
                    'Join Us',
                    'Try It Out',
                    'See How',
                    'Take a Look',
                    'Get Going'
                ],
                'funny' => [
                    'Don\'t Wait',
                    'Jump In',
                    'Go For It',
                    'Why Not?',
                    'Let\'s Do This',
                    'Ready? Go!',
                    'Make It Happen',
                    'Just Do It'
                ]
            ],
            'lifestyle' => [
                'professional' => [
                    'Join Our Community',
                    'Start Your Journey',
                    'Transform Today',
                    'Begin Now',
                    'Take Action',
                    'Get Started',
                    'Change Your Life',
                    'Make It Happen'
                ],
                'casual' => [
                    'Come Along',
                    'Join the Fun',
                    'Be Part of It',
                    'Get Involved',
                    'Come On In',
                    'Jump On Board',
                    'Be There',
                    'Don\'t Miss Out'
                ],
                'funny' => [
                    'Don\'t Be Left Out',
                    'Join the Party',
                    'Come Play',
                    'Get In On This',
                    'Be Cool Like Us',
                    'Don\'t Be Square',
                    'Come On Over',
                    'Be Awesome'
                ]
            ],
            'tutorial' => [
                'professional' => [
                    'Learn More',
                    'Master This',
                    'Get Skilled',
                    'Become Expert',
                    'Study Now',
                    'Improve Skills',
                    'Level Up',
                    'Advance Today'
                ],
                'casual' => [
                    'Learn This',
                    'Try It Yourself',
                    'Give It a Go',
                    'Practice Now',
                    'Have a Go',
                    'Test It Out',
                    'See If You Can',
                    'Challenge Yourself'
                ],
                'funny' => [
                    'Be a Pro',
                    'Show Off',
                    'Impress Friends',
                    'Be Amazing',
                    'Look Smart',
                    'Be Cool',
                    'Stand Out',
                    'Be Awesome'
                ]
            ],
            'testimonial' => [
                'professional' => [
                    'Join Success Stories',
                    'Be Like Them',
                    'Start Your Success',
                    'Achieve Results',
                    'Get Results',
                    'See Success',
                    'Win Like Them',
                    'Succeed Today'
                ],
                'casual' => [
                    'Be Like Them',
                    'Join Winners',
                    'Get Results Too',
                    'Be Successful',
                    'Win Like This',
                    'Join Success',
                    'Be a Winner',
                    'Get There'
                ],
                'funny' => [
                    'Be a Winner',
                    'Join the Winners',
                    'Be Like Them',
                    'Win Too',
                    'Be Successful',
                    'Join Success',
                    'Be Awesome',
                    'Win Big'
                ]
            ]
        ];

        // Get appropriate templates based on scene type
        $sceneTemplates = $ctaTemplates[$sceneType] ?? $ctaTemplates['product_demo'];
        $styleTemplates = $sceneTemplates[$style] ?? $sceneTemplates['professional'];

        // Select random CTA from appropriate templates
        $selectedCTA = $styleTemplates[array_rand($styleTemplates)];

        // Add context if provided
        if (!empty($context)) {
            $selectedCTA = $this->enhanceCTAWithContext($selectedCTA, $context);
        }

        return $selectedCTA;
    }

    /**
     * Generate contextual line that enhances the scene
     */
    private function generateContextualLine(array $videoAnalysis, string $context, string $style): string
    {
        $sceneType = $videoAnalysis['scene_type'];
        $mood = $videoAnalysis['mood'];
        $activityLevel = $videoAnalysis['activity_level'];

        // Contextual line templates based on scene analysis
        $contextualTemplates = [
            'product_demo' => [
                'professional' => [
                    'See how it works in real-time',
                    'Experience the difference',
                    'Watch the magic happen',
                    'See the results instantly',
                    'Experience seamless performance',
                    'Watch innovation in action',
                    'See quality in motion',
                    'Experience excellence'
                ],
                'casual' => [
                    'Pretty cool, right?',
                    'See how easy that was?',
                    'That\'s how it\'s done',
                    'Pretty neat, huh?',
                    'See what I mean?',
                    'That\'s the magic',
                    'Pretty awesome stuff',
                    'See how smooth?'
                ],
                'funny' => [
                    'Boom! Just like that',
                    'Easy peasy, lemon squeezy',
                    'That\'s how we roll',
                    'Pretty slick, right?',
                    'That\'s what I\'m talking about',
                    'Boom! Problem solved',
                    'That\'s how you do it',
                    'Pretty sweet, huh?'
                ]
            ],
            'lifestyle' => [
                'professional' => [
                    'This is what success looks like',
                    'See the transformation',
                    'Experience the change',
                    'This is your future',
                    'See what\'s possible',
                    'This is the lifestyle',
                    'Experience the difference',
                    'See the results'
                ],
                'casual' => [
                    'This is the life',
                    'Pretty amazing, right?',
                    'This is living',
                    'See what I mean?',
                    'This is awesome',
                    'Pretty cool lifestyle',
                    'This is it',
                    'See the difference?'
                ],
                'funny' => [
                    'Living the dream',
                    'This is how it\'s done',
                    'Pretty sweet life',
                    'This is living',
                    'Living large',
                    'This is awesome',
                    'Pretty cool, right?',
                    'This is the way'
                ]
            ],
            'tutorial' => [
                'professional' => [
                    'Follow these simple steps',
                    'Watch and learn',
                    'Master this technique',
                    'Learn the process',
                    'See the method',
                    'Follow the steps',
                    'Learn the skill',
                    'Master the art'
                ],
                'casual' => [
                    'Here\'s how you do it',
                    'Watch this closely',
                    'Pretty simple, right?',
                    'See how easy?',
                    'That\'s how it works',
                    'Pretty straightforward',
                    'See the technique?',
                    'That\'s the trick'
                ],
                'funny' => [
                    'Easy as pie',
                    'Piece of cake',
                    'Nothing to it',
                    'Child\'s play',
                    'No big deal',
                    'Super simple',
                    'Easy peasy',
                    'No sweat'
                ]
            ],
            'testimonial' => [
                'professional' => [
                    'Real results from real people',
                    'See what customers say',
                    'Hear their success story',
                    'This is their experience',
                    'See the transformation',
                    'Real customer feedback',
                    'Hear their journey',
                    'See their results'
                ],
                'casual' => [
                    'Pretty amazing story',
                    'See what they say',
                    'Pretty cool results',
                    'That\'s their experience',
                    'Pretty awesome feedback',
                    'See their story',
                    'Pretty great results',
                    'That\'s what they say'
                ],
                'funny' => [
                    'Pretty awesome, right?',
                    'That\'s what they say',
                    'Pretty cool story',
                    'That\'s their experience',
                    'Pretty amazing results',
                    'That\'s the truth',
                    'Pretty sweet feedback',
                    'That\'s real talk'
                ]
            ]
        ];

        // Get appropriate templates based on scene type
        $sceneTemplates = $contextualTemplates[$sceneType] ?? $contextualTemplates['product_demo'];
        $styleTemplates = $sceneTemplates[$style] ?? $sceneTemplates['professional'];

        // Select random contextual line from appropriate templates
        $selectedLine = $styleTemplates[array_rand($styleTemplates)];

        // Add context if provided
        if (!empty($context)) {
            $selectedLine = $this->enhanceContextualLineWithContext($selectedLine, $context);
        }

        return $selectedLine;
    }

    /**
     * Enhance CTA with context
     */
    private function enhanceCTAWithContext(string $cta, string $context): string
    {
        // Simple context enhancement - could be made more sophisticated
        $contextWords = explode(' ', strtolower($context));
        
        // Look for key words in context to enhance CTA
        if (in_array('free', $contextWords)) {
            return str_replace(['Get', 'Try', 'Start'], ['Get Free', 'Try Free', 'Start Free'], $cta);
        }
        
        if (in_array('now', $contextWords)) {
            return $cta . ' Now';
        }
        
        return $cta;
    }

    /**
     * Enhance contextual line with context
     */
    private function enhanceContextualLineWithContext(string $line, string $context): string
    {
        // Simple context enhancement
        $contextWords = explode(' ', strtolower($context));
        
        // Add context-specific enhancements
        if (in_array('amazing', $contextWords)) {
            return str_replace(['pretty', 'see'], ['amazing', 'see'], $line);
        }
        
        return $line;
    }

    /**
     * Get available styles and text themes for creative text generation
     */
    public function getStyles(): JsonResponse
    {
        return response()->json([
            'textThemes' => [
                'contextual' => 'Contextual text that enhances the scene and feels part of the story',
                'cta' => 'Call-to-action (≤ 5 words) that motivates action'
            ],
            'styles' => [
                'professional' => 'Professional and business-focused',
                'casual' => 'Relaxed and conversational',
                'funny' => 'Humorous and light-hearted',
                'inspirational' => 'Motivational and uplifting',
                'technical' => 'Technical and precise'
            ],
            'sceneTypes' => [
                'product_demo' => 'Product demonstration or showcase',
                'lifestyle' => 'Lifestyle or aspirational content',
                'tutorial' => 'Educational or how-to content',
                'testimonial' => 'Customer testimonial or review'
            ]
        ]);
    }
}