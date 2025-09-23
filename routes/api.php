<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VideoController;

Route::post('/save', [VideoController::class, 'processVideoWithSubtitles']);
