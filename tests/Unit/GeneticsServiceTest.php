<?php

use App\Services\GeneticsService;

beforeEach(function () {
    $this->service = new GeneticsService;
});

function baseEquineGenetics(): array
{
    return [
        'dict' => [
            'black' => ['oddsType' => 'punnett', 'alleles' => ['E', 'e']],
            'agouti' => ['oddsType' => 'punnett', 'alleles' => ['At', 'A', 'a']],
            'silver' => ['oddsType' => 'percentage', 'alleles' => ['Z']],
        ],
        'odds' => [
            'punnett' => ['roll1' => 25, 'roll2' => 25, 'roll3' => 25, 'roll4' => 25],
            'percentage' => [
                'domXdom' => ['dom' => 100],
                'domXrec' => ['dom' => 100],
                'domXnone' => ['rec' => 50],
                'recXrec' => ['dom' => 50, 'rec' => 50],
                'recXnone' => ['rec' => 50],
                'noneXnone' => ['none' => 100],
            ],
        ],
    ];
}

test('parseParentGenotype splits Ee into E and e', function () {
    $alleles = ['E', 'e'];
    expect($this->service->parseParentGenotype('Ee', $alleles))->toBe(['E', 'e']);
});

test('parseParentGenotype splits homozygous recessive into two alleles', function () {
    $alleles = ['E', 'e'];
    expect($this->service->parseParentGenotype('ee', $alleles))->toBe(['e', 'e']);
});

test('parseParentGenotype splits homozygous dominant into two alleles', function () {
    $alleles = ['E', 'e'];
    expect($this->service->parseParentGenotype('EE', $alleles))->toBe(['E', 'E']);
});

test('parseParentGenotype uses longest allele match first for agouti', function () {
    $alleles = ['At', 'A', 'a'];
    expect($this->service->parseParentGenotype('AtA', $alleles))->toBe(['At', 'A']);
});

test('parseParentGenotype throws for empty genotype', function () {
    $this->service->parseParentGenotype('', ['E', 'e']);
})->throws(InvalidArgumentException::class);

test('parseParentGenotype throws when genotype does not match two alleles', function () {
    $this->service->parseParentGenotype('Xy', ['E', 'e']);
})->throws(InvalidArgumentException::class);

test('formatGenotype returns alleles in dominance order', function () {
    $allelesOrder = ['E', 'e'];
    expect($this->service->formatGenotype(['e', 'E'], $allelesOrder))->toBe('Ee');
});

test('getPunnettOutcomesForGene returns four outcomes with equal probability by default', function () {
    $sire = ['E', 'e'];
    $dam = ['E', 'e'];
    $alleles = ['E', 'e'];
    $odds = ['roll1' => 25, 'roll2' => 25, 'roll3' => 25, 'roll4' => 25];

    $outcomes = $this->service->getPunnettOutcomesForGene($sire, $dam, $alleles, $odds);

    expect($outcomes)->toHaveCount(4);
    $genotypes = array_column($outcomes, 'genotype');
    expect($genotypes)->toContain('EE', 'Ee', 'ee');
    expect($genotypes)->toHaveCount(4);
    $probs = array_column($outcomes, 'probability');
    expect(array_sum($probs))->toBe(1.0);
});

test('classifyPercentageParent returns none for empty, dom for ZZ, rec for nZ', function () {
    $alleles = ['Z'];
    expect($this->service->classifyPercentageParent('', $alleles))->toBe('none');
    expect($this->service->classifyPercentageParent('ZZ', $alleles))->toBe('dom');
    expect($this->service->classifyPercentageParent('nZ', $alleles))->toBe('rec');
});

test('classifyPercentageParent throws for invalid genotype', function () {
    $this->service->classifyPercentageParent('Zz', ['Z']);
})->throws(InvalidArgumentException::class);

test('percentageOutcomeToGenotype maps dom to ZZ, rec to nZ, none to empty', function () {
    $alleles = ['Z'];
    expect($this->service->percentageOutcomeToGenotype('dom', $alleles))->toBe('ZZ');
    expect($this->service->percentageOutcomeToGenotype('rec', $alleles))->toBe('nZ');
    expect($this->service->percentageOutcomeToGenotype('none', $alleles))->toBe('');
});

test('getPercentageOutcomesForGene returns outcomes from odds table', function () {
    $odds = ['recXrec' => ['dom' => 50, 'rec' => 50]];
    $outcomes = $this->service->getPercentageOutcomesForGene('rec', 'rec', ['Z'], $odds);

    expect($outcomes)->toHaveCount(2);
    $byGenotype = [];
    foreach ($outcomes as $o) {
        $byGenotype[$o['genotype']] = $o['probability'];
    }
    expect($byGenotype['ZZ'])->toBe(0.5);
    expect($byGenotype['nZ'])->toBe(0.5);
});

test('getPercentageOutcomesForGene defaults to 100% none when key missing', function () {
    $outcomes = $this->service->getPercentageOutcomesForGene('none', 'none', ['Z'], []);

    expect($outcomes)->toHaveCount(1);
    expect($outcomes[0]['genotype'])->toBe('');
    expect($outcomes[0]['probability'])->toBe(1.0);
});

test('getPunnettOutcomesForGene respects custom odds', function () {
    $sire = ['E', 'e'];
    $dam = ['e', 'e'];
    $alleles = ['E', 'e'];
    $odds = ['roll1' => 50, 'roll2' => 0, 'roll3' => 0, 'roll4' => 50];

    $outcomes = $this->service->getPunnettOutcomesForGene($sire, $dam, $alleles, $odds);

    $byGenotype = [];
    foreach ($outcomes as $o) {
        $byGenotype[$o['genotype']] = ($byGenotype[$o['genotype']] ?? 0) + $o['probability'];
    }
    expect($byGenotype['Ee'] ?? 0)->toBe(0.5);
    expect($byGenotype['ee'] ?? 0)->toBe(0.5);
});

test('getBreedingOutcomes returns all combinations with percentages summing to 100', function () {
    $sire = ['Ee', 'Aa'];
    $dam = ['Ee', 'Aa'];
    $genetics = baseEquineGenetics();

    $result = $this->service->getBreedingOutcomes($sire, $dam, $genetics);

    expect($result)->not->toBeEmpty();
    $totalPct = 0.0;
    foreach ($result as $row) {
        expect($row)->toHaveKeys(['genotype', 'probability', 'percentage']);
        expect($row['genotype'])->toBeArray();
        $totalPct += $row['probability'];
    }
    expect(round($totalPct, 10))->toBe(1.0);
});

test('getBreedingOutcomes genotype entries are in dictionary order', function () {
    $sire = ['Ee', 'Aa', 'nZ'];
    $dam = ['ee', 'aa', ''];
    $genetics = baseEquineGenetics();

    $result = $this->service->getBreedingOutcomes($sire, $dam, $genetics);

    expect($result)->not->toBeEmpty();
    $first = $result[0];
    $dict = $genetics['dict'];
    expect($first['genotype'])->toHaveCount(count($dict));
});

test('getBreedingOutcomes includes percentage-type genes and respects percentage odds', function () {
    $sire = ['Ee', 'Aa', 'nZ'];
    $dam = ['Ee', 'Aa', 'nZ'];
    $genetics = baseEquineGenetics();

    $result = $this->service->getBreedingOutcomes($sire, $dam, $genetics);

    $totalPct = 0.0;
    $silverOutcomes = [];
    foreach ($result as $row) {
        $totalPct += $row['probability'];
        $silver = $row['genotype'][2] ?? null;
        $silverOutcomes[$silver] = ($silverOutcomes[$silver] ?? 0) + $row['probability'];
    }
    expect(round($totalPct, 10))->toBe(1.0);
    // recXrec => dom 50%, rec 50% in default odds
    expect($silverOutcomes)->toHaveKeys(['ZZ', 'nZ']);
    expect(round($silverOutcomes['ZZ'], 10))->toBe(0.5);
    expect(round($silverOutcomes['nZ'], 10))->toBe(0.5);
});

test('tokensToOrderedGenes assigns by validity so input order does not matter', function () {
    $tokens = ['aa', 'ee'];
    $genetics = baseEquineGenetics();
    $dict = $genetics['dict'];

    $ordered = $this->service->tokensToOrderedGenes($tokens, $dict);

    $geneNames = array_keys($dict);
    expect($ordered)->toHaveCount(count($geneNames));
    expect($ordered[0])->toBe('ee');
    expect($ordered[1])->toBe('aa');
});

test('tokensToOrderedGenes assigns percentage gene token to correct slot', function () {
    $tokens = ['aa', 'ee', 'nZ'];
    $genetics = baseEquineGenetics();

    $ordered = $this->service->tokensToOrderedGenes($tokens, $genetics['dict']);

    expect($ordered[2])->toBe('nZ');
});

test('tokensToOrderedGenes throws when not enough tokens', function () {
    $genetics = baseEquineGenetics();
    $this->service->tokensToOrderedGenes(['ee'], $genetics['dict']);
})->throws(InvalidArgumentException::class);
