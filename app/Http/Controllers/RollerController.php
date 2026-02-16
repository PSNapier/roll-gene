<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRollerRequest;
use App\Models\Roller;
use App\Services\GeneticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RollerController extends Controller
{
    public function __construct(
        private GeneticsService $geneticsService
    ) {}

    public function index(Request $request): Response
    {
        $rollers = Roller::query()
            ->visibleTo($request->user())
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'is_core', 'visibility']);

        return Inertia::render('Rollers/Index', [
            'rollers' => $rollers,
        ]);
    }

    public function show(Request $request, Roller $roller): Response|RedirectResponse
    {
        $this->authorize('view', $roller);

        $genetics = $this->geneticsService->getDictionaryForRoller($roller);
        $canEdit = $request->user() && Gate::allows('update', $roller);

        return Inertia::render('RollerShow', [
            'roller' => [
                'id' => $roller->id,
                'name' => $roller->name,
                'slug' => $roller->slug,
                'is_core' => $roller->is_core,
            ],
            'genetics' => $genetics,
            'phenos' => $roller->phenos ?? [],
            'canEdit' => $canEdit,
            'outcomes' => $request->session()->get('roller_outcomes'),
        ]);
    }

    public function update(UpdateRollerRequest $request, Roller $roller): RedirectResponse
    {
        $this->authorize('update', $roller);

        $payload = [];
        if ($request->has('dictionary')) {
            $payload['dictionary'] = $request->validated('dictionary');
        }
        if ($request->has('phenos')) {
            $payload['phenos'] = $request->validated('phenos');
        }
        $roller->update($payload);

        $message = match (true) {
            isset($payload['dictionary'], $payload['phenos']) => 'Genes and phenos updated.',
            isset($payload['phenos']) => 'Phenos updated.',
            default => 'Genes updated.',
        };

        return redirect()->route('rollers.show', $roller)
            ->with('status', $message);
    }

    public function roll(Request $request, Roller $roller): RedirectResponse
    {
        $this->authorize('view', $roller);

        $validated = $request->validate([
            'sire_genes' => ['required', 'string'],
            'dam_genes' => ['required', 'string'],
        ]);

        $sireTokens = $this->parseGeneString($validated['sire_genes']);
        $damTokens = $this->parseGeneString($validated['dam_genes']);
        $dict = $roller->dictionary ?? [];

        try {
            $sire = $this->geneticsService->tokensToOrderedGenes($sireTokens, $dict);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'sire_genes' => [$e->getMessage()],
            ]);
        }

        try {
            $dam = $this->geneticsService->tokensToOrderedGenes($damTokens, $dict);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'dam_genes' => [$e->getMessage()],
            ]);
        }

        $outcomes = $this->geneticsService->getBreedingOutcomes($sire, $dam, $roller->toGeneticsArray());

        return redirect()->route('rollers.show', $roller)->with('roller_outcomes', $outcomes);
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
