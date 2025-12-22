<?php

use App\Http\Controllers\TvController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TvController::class, 'index']);
Route::get('/tv/data', [TvController::class, 'data']);
