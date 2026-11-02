<?php

namespace App\Http\Controllers;

use App\Models\Letter;
use App\Models\LetterBatch;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $year = now()->year;

        return Inertia::render('Dashboard', [
            'stats' => [
                'lettersThisYear' => Letter::query()
                    ->whereHas('batch', fn ($query) => $query->whereYear('letter_date', $year))
                    ->count(),
                'batchesThisYear' => LetterBatch::query()
                    ->whereYear('letter_date', $year)
                    ->count(),
                'lettersToday' => Letter::query()
                    ->whereHas('batch', fn ($query) => $query->whereDate('letter_date', today()))
                    ->count(),
            ],
            'recentBatches' => LetterBatch::query()
                ->withCount('letters')
                ->latest('letter_date')
                ->latest('id')
                ->limit(5)
                ->get(),
        ]);
    }
}
