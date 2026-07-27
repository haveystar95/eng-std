<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWordRequest;
use App\Http\Requests\UpdateWordRequest;
use App\Http\Resources\WordResource;
use App\Models\Collection;
use App\Models\Word;
use App\Services\Vocabulary;
use Illuminate\Http\JsonResponse;

class WordController extends Controller
{
    public function store(StoreWordRequest $request, Collection $collection, Vocabulary $vocab): JsonResponse
    {
        $this->authorize('update', $collection);

        $word = $vocab->addToCollection($request->user(), $collection, $request->validated());

        return (new WordResource($word))->response()->setStatusCode(201);
    }

    public function update(UpdateWordRequest $request, Collection $collection, Word $word): WordResource
    {
        $this->authorize('update', $collection);
        $this->authorize('update', $word);

        $data = $request->validated();
        if (isset($data['term'])) {
            $data['term'] = trim($data['term']);
            $data['term_key'] = Word::keyFor($data['term']);
        }
        $word->fill($data)->save();

        return new WordResource($word);
    }

    public function destroy(Collection $collection, Word $word): JsonResponse
    {
        $this->authorize('update', $collection);
        $this->authorize('delete', $word);

        $collection->words()->detach($word->id);

        if ($word->collections()->count() === 0) {
            $word->delete();
        }

        return response()->json(['ok' => true]);
    }
}
