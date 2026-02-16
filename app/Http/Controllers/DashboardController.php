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

        $sireTokens = $this->parseGeneString($validated['sire_genes']);
        $damTokens = $this->parseGeneString($validated['dam_genes']);

        try {
            $sire = $this->geneticsService->tokensToOrderedGenes($sireTokens);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'sire_genes' => [$e->getMessage()],
            ]);
        }

        try {
            $dam = $this->geneticsService->tokensToOrderedGenes($damTokens);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'dam_genes' => [$e->getMessage()],
            ]);
        }

        $outcomes = $this->geneticsService->getBreedingOutcomes($sire, $dam);

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
