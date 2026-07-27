<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StatsBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function index(Request $request, StatsBuilder $builder): JsonResponse
    {
        return response()->json($builder->forUser($request->user()));
    }
}
