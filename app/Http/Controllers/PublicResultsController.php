<?php

namespace App\Http\Controllers;

use App\Models\Election;
use App\Services\ResultService;
use Illuminate\View\View;

class PublicResultsController extends Controller
{
    public function __invoke(ResultService $results): View
    {
        $election = Election::current();

        return view('results.official', [
            'election' => $election,
            'results' => $election && $election->isPublished()
                ? $results->live($election)
                : null,
        ]);
    }
}
