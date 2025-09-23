<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\CreativityController;
use Gemini\Laravel\Facades\Gemini;

Route::post('/save', [VideoController::class, 'processVideoWithSubtitles']);

Route::get('/gg', function () {
    $result = Gemini::generativeModel(model: 'gemini-2.0-flash')->generateContent('Hello, are you here');
    return  $result->text();
});

// Test route for upgraded CreativityController
Route::get('/test-creativity', function () {
    return response()->json([
        'message' => 'CreativityController upgraded with Gemini API',
        'features' => [
            'Video segment extraction using FFmpeg',
            'Gemini video analysis with gemini-2.0-flash model',
            'AI-powered creative phrase generation',
            'Fallback to original templates if Gemini fails',
            'Enhanced metadata with analysis results'
        ],
        'endpoint' => '/api/creativity/generate',
        'required_params' => [
            'file' => 'Video file data (base64 or data URL)',
            'startTime' => 'Start time in seconds',
            'endTime' => 'End time in seconds',
            'textTheme' => 'contextual or cta',
            'style' => 'professional, casual, funny, inspirational, or technical',
            'context' => 'Optional context string'
        ]
    ]);
});

// Test route to verify API is working
Route::get('/test', function () {
    return response()->json(['message' => 'API is working']);
});

// Creativity routes
Route::post('/creativity/generate', [CreativityController::class, 'generateCreativePhrase']);
Route::get('/creativity/styles', [CreativityController::class, 'getStyles']);
