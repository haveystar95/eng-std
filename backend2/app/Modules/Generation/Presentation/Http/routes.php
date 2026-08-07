<?php

declare(strict_types=1);

use App\Modules\Generation\Presentation\Http\Controller\GenerationController;
use App\Modules\Generation\Presentation\Http\Controller\PracticeDialogController;
use App\Modules\Generation\Presentation\Http\Controller\RegenerateExampleController;
use Illuminate\Support\Facades\Route;

// Prefixed with /api/v1 by GenerationServiceProvider.
Route::middleware(['throttle:60,1', 'auth:sanctum'])->group(function (): void {
    Route::post('/generations', [GenerationController::class, 'store']);
    Route::get('/generations/{id}', [GenerationController::class, 'show']);
    Route::post('/terms/{id}/regenerate-example', RegenerateExampleController::class);

    // Realtime conversation practice (premium).
    Route::post('/practice/dialogs', [PracticeDialogController::class, 'store']);
    Route::post('/practice/dialogs/{id}/transcripts', [PracticeDialogController::class, 'transcripts']);
    Route::post('/practice/dialogs/{id}/finish', [PracticeDialogController::class, 'finish']);
    Route::get('/practice/collections/{collectionId}/last-dialog', [PracticeDialogController::class, 'lastForCollection']);
});
