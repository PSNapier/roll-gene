<?php

namespace App\Http\Controllers;

use App\Services\GeneticsService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private GeneticsService $geneticsService
    ) {}

    public function index(): Response
    {
        return Inertia::render('Dashboard', [
            'genetics' => $this->geneticsService->getBaseDictionary(),
        ]);
    }

    public function roll(Request $request): Response
    {
        $validated = $request->validate([
            'sire_genes' => ['required', 'string'],
            'dam_genes' => ['required', 'string'],
        ]);

        $sire = $this->parseGeneString($validated['sire_genes']);
        $dam = $this->parseGeneString($validated['dam_genes']);

        try {
            $outcomes = $this->geneticsService->getBreedingOutcomes($sire, $dam);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'sire_genes' => [$e->getMessage()],
            ]);
        }

        return Inertia::render('Dashboard', [
            'genetics' => $this->geneticsService->getBaseDictionary(),
            'outcomes' => $outcomes,
        ]);
    }

    private function parseGeneString(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }
        $tokens = preg_split('/[\/,\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_map('trim', $tokens));
    }
}
