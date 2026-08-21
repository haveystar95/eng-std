<?php

declare(strict_types=1);

use App\Modules\Generation\Presentation\Http\Controller\GenerationController;
use App\Modules\Generation\Presentation\Http\Controller\PracticeDialogController;
use App\Modules\Generation\Presentation\Http\Controller\RegenerateExampleController;
use App\Modules\Generation\Presentation\Http\Controller\SearchController;
use Illuminate\Support\Facades\Route;

// Prefixed with /api/v1 by GenerationServiceProvider.
Route::middleware(['throttle:60,1', 'auth:sanctum'])->group(function (): void {
    Route::post('/generations', [GenerationController::class, 'store']);
    Route::get('/generations/{id}', [GenerationController::class, 'show']);
    Route::post('/terms/{id}/regenerate-example', RegenerateExampleController::class);

    // Word search. `GET /search` is free and instant, so it gets its own, looser throttle: the
    // screen debounces and still fires it far more often than any other endpoint here. The two
    // POSTs stay on the group's 60/min — they write, and one of them spends money.
    Route::get('/search', [SearchController::class, 'index'])->withoutMiddleware('throttle:60,1')
        ->middleware('throttle:240,1');
    Route::post('/search/lookup', [SearchController::class, 'lookup']);
    Route::post('/search/add', [SearchController::class, 'add']);

    // Realtime conversation practice (premium).
    Route::post('/practice/dialogs', [PracticeDialogController::class, 'store']);
    Route::post('/practice/dialogs/{id}/transcripts', [PracticeDialogController::class, 'transcripts']);
    Route::post('/practice/dialogs/{id}/finish', [PracticeDialogController::class, 'finish']);
    Route::get('/practice/collections/{collectionId}/last-dialog', [PracticeDialogController::class, 'lastForCollection']);
});
