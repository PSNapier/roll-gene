<?php

use App\Services\GeneticsService;

beforeEach(function () {
    $this->service = new GeneticsService;
});

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

    $result = $this->service->getBreedingOutcomes($sire, $dam);

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
    $sire = ['Ee', 'Aa'];
    $dam = ['ee', 'aa'];

    $result = $this->service->getBreedingOutcomes($sire, $dam);

    expect($result)->not->toBeEmpty();
    $first = $result[0];
    expect(array_keys($first['genotype']))->toBe([0, 1]);
    expect($first['genotype'])->toHaveCount(2);
});

test('getBreedingOutcomes skips percentage-type genes', function () {
    $sire = ['Ee', 'Aa', 'nZ'];
    $dam = ['Ee', 'Aa', 'nZ'];

    $result = $this->service->getBreedingOutcomes($sire, $dam);

    foreach ($result as $row) {
        expect($row['genotype'])->toHaveCount(2);
    }
});
