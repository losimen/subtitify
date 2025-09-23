<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\CreativityController;

Route::post('/save', [VideoController::class, 'processVideoWithSubtitles']);

// Creativity routes
Route::post('/creativity/generate', [CreativityController::class, 'generateCreativePhrase']);
Route::get('/creativity/styles', [CreativityController::class, 'getStyles']);
