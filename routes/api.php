<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PhraseController;
use App\Http\Controllers\Api\V1\QuizController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', function (Request $request) {
            return $request->user();
        });

        Route::post('/phrases', [PhraseController::class, 'store']);
        Route::get('/phrases/active', [PhraseController::class, 'active']);
        Route::post('/phrases/sync', [PhraseController::class, 'sync']);

        Route::post('/quiz/submit', [QuizController::class, 'submit']);
    });
});
