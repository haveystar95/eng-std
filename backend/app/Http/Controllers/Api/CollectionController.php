<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateCollectionRequest;
use App\Http\Requests\StoreCollectionRequest;
use App\Http\Requests\UpdateCollectionRequest;
use App\Http\Resources\CollectionResource;
use App\Jobs\GenerateCollectionJob;
use App\Models\AiJob;
use App\Models\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CollectionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $collections = $request->user()->collections()->withCount('words')->latest()->get();

        return CollectionResource::collection($collections);
    }

    public function store(StoreCollectionRequest $request): JsonResponse
    {
        $collection = $request->user()->collections()->create(
            $request->validated() + ['source' => 'manual'],
        );

        return (new CollectionResource($collection->loadCount('words')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Collection $collection): CollectionResource
    {
        $this->authorize('view', $collection);

        return new CollectionResource($collection->loadCount('words')->load('words'));
    }

    public function update(UpdateCollectionRequest $request, Collection $collection): CollectionResource
    {
        $this->authorize('update', $collection);
        $collection->fill($request->validated())->save();

        return new CollectionResource($collection->loadCount('words'));
    }

    public function destroy(Collection $collection): JsonResponse
    {
        $this->authorize('delete', $collection);
        $collection->delete();

        return response()->json(['ok' => true]);
    }

    public function generate(GenerateCollectionRequest $request): JsonResponse
    {
        $job = AiJob::create([
            'user_id' => $request->user()->id,
            'type' => 'generate_collection',
            'status' => 'queued',
            'payload' => $request->validated(),
        ]);

        GenerateCollectionJob::dispatch($job->id);

        return response()->json(['job_id' => $job->id, 'status' => 'queued'], 202);
    }
}
