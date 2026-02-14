<?php

use App\Http\Controllers\Api\AiSearchController;

Route::get('/ai/search', [AiSearchController::class, 'index']);
