<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\CreativityController;

Route::post('/save', [VideoController::class, 'processVideoWithSubtitles']);

// Test route to verify API is working
Route::get('/test', function () {
    return response()->json(['message' => 'API is working']);
});

// Creativity routes
Route::post('/creativity/generate', [CreativityController::class, 'generateCreativePhrase']);
Route::get('/creativity/styles', [CreativityController::class, 'getStyles']);
