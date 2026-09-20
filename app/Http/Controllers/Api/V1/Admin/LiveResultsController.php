<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Election;
use App\Services\ResultService;
use Illuminate\Http\JsonResponse;

class LiveResultsController extends Controller
{
    public function __invoke(ResultService $results): JsonResponse
    {
        $election = Election::current();

        if (! $election) {
            return response()->json(['message' => 'No election is configured.'], 404);
        }

        return response()->json($results->live($election));
    }
}
