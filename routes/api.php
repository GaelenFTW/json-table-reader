<?php
use App\Http\Controllers\JsonBeautifierController;
use Illuminate\Support\Facades\Route;

Route::post('/json-beautify', [JsonBeautifierController::class, 'apiBeautify']);
