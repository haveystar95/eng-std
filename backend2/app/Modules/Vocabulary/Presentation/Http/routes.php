<?php

declare(strict_types=1);

// Vocabulary routes (api/v1). Controllers translate input and dispatch Commands/Queries.

use App\Modules\Vocabulary\Presentation\Http\Controller\TermAudioController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:240,1', 'auth:sanctum'])->group(function (): void {
    // ОЗВУЧКА РЕПЛИКИ (наряд TTS-1). Лимит выше обычного: вход в день качает все реплики посадки
    // подряд, и это один короткий залп, а не поток — 120/мин упирались бы в него на длинной сцене.
    Route::get('/audio/lines/{audioId}', [TermAudioController::class, 'show'])
        ->where('audioId', '[0-9A-HJKMNP-TV-Z]{26}');
});
