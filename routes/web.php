<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JsonBeautifierController;

Route::get('/json-beautifier', [JsonBeautifierController::class, 'show'])->name('json.beautifier.show');
Route::post('/json-beautifier', [JsonBeautifierController::class, 'beautify'])->name('json.beautifier.beautify');
