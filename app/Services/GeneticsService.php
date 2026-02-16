<?php

namespace App\Services;

class GeneticsService
{
    /** @var array<string, int> Punnett square cell weights (roll1..roll4). */
    private const DEFAULT_BASE_ODDS_KEYS = ['roll1', 'roll2', 'roll3', 'roll4'];

    /**
     * Default odds for base (realistic/Punnett) gene rolling.
     * Each key (roll1..roll4) is the weight for one cell of the 2x2 Punnett square.
     *
     * @return array<string, int>
     */
    public function getBaseOdds(): array
    {
        return [
            'roll1' => 25,
            'roll2' => 25,
            'roll3' => 25,
            'roll4' => 25,
        ];
    }

    /**
     * Default odds for percentage-based inheritance (dom/rec/none).
     *
     * @return array<string, array<string, int>>
     */
    public function getPercentageOdds(): array
    {
        return [
            'domXdom' => ['dom' => 100],
            'domXrec' => ['dom' => 100],
            'domXnone' => ['rec' => 50],
            'recXrec' => ['dom' => 50, 'rec' => 50],
            'recXnone' => ['rec' => 50],
        ];
    }

    /**
     * Base equine genetics dictionary (public free tier).
     * Each gene has an odds type ('base' or 'percentage') and alleles (dominance order).
     *
     * @return array{odds: array{base: array<string, int>, percentage: array<string, array<string, int>>}, dict: array<string, array{oddsType: string, alleles: array<string>}>}
     */
    public function getBaseDictionary(): array
    {
        $base = $this->getBaseOdds();
        $percentage = $this->getPercentageOdds();

        return [
            'odds' => [
                'base' => $base,
                'percentage' => $percentage,
            ],
            'dict' => [
                'black' => ['oddsType' => 'base', 'alleles' => ['E', 'e']],
                'agouti' => ['oddsType' => 'base', 'alleles' => ['At', 'A', 'a']],
                'silver' => ['oddsType' => 'percentage', 'alleles' => ['Z']],
            ],
        ];
    }

    /**
     * Split a parent genotype string into two alleles for one gene.
     * Alleles are matched longest-first so "At" is used before "A" when both exist.
     *
     * @param  array<string>  $alleles  Legal alleles for this gene (dominance order).
     * @return array{0: string, 1: string} Two alleles.
     *
     * @throws \InvalidArgumentException When genotype cannot be parsed into two alleles.
     */
    public function parseParentGenotype(string $genotype, array $alleles): array
    {
        $genotype = trim($genotype);
        if ($genotype === '') {
            throw new \InvalidArgumentException('Genotype cannot be empty.');
        }

        $allelesByLength = $alleles;
        usort($allelesByLength, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($allelesByLength as $first) {
            if (str_starts_with($genotype, $first)) {
                $remainder = substr($genotype, strlen($first));
                foreach ($allelesByLength as $second) {
                    if ($remainder === $second) {
                        return [$first, $second];
                    }
                }
            }
        }

        throw new \InvalidArgumentException("Genotype \"{$genotype}\" does not match two alleles from [".implode(', ', $alleles).'].');
    }

    /**
     * Format two alleles as a single genotype string in dominance order.
     *
     * @param  array{0: string, 1: string}  $allelePair
     * @param  array<string>  $allelesOrder  Alleles in dominance order.
     */
    public function formatGenotype(array $allelePair, array $allelesOrder): string
    {
        $order = array_flip($allelesOrder);
        usort($allelePair, fn (string $a, string $b): int => ($order[$a] ?? 0) <=> ($order[$b] ?? 0));

        return $allelePair[0].$allelePair[1];
    }

    /**
     * All possible offspring genotypes for one base gene with their relative probabilities.
     * Punnett square: (sire1,dam1)=roll1, (sire1,dam2)=roll2, (sire2,dam1)=roll3, (sire2,dam2)=roll4.
     *
     * @param  array{0: string, 1: string}  $sireAlleles
     * @param  array{0: string, 1: string}  $damAlleles
     * @param  array<string>  $allelesOrder
     * @param  array<string, int>  $baseOdds
     * @return array<int, array{genotype: string, probability: float}>
     */
    public function getPunnettOutcomesForGene(array $sireAlleles, array $damAlleles, array $allelesOrder, array $baseOdds): array
    {
        $pairs = [
            [$sireAlleles[0], $damAlleles[0]],
            [$sireAlleles[0], $damAlleles[1]],
            [$sireAlleles[1], $damAlleles[0]],
            [$sireAlleles[1], $damAlleles[1]],
        ];
        $weights = [
            $baseOdds['roll1'] ?? 25,
            $baseOdds['roll2'] ?? 25,
            $baseOdds['roll3'] ?? 25,
            $baseOdds['roll4'] ?? 25,
        ];
        $total = (float) array_sum($weights);
        if ($total <= 0) {
            $total = 100.0;
            $weights = [25, 25, 25, 25];
        }

        $outcomes = [];
        foreach (self::DEFAULT_BASE_ODDS_KEYS as $i => $key) {
            $genotype = $this->formatGenotype($pairs[$i], $allelesOrder);
            $outcomes[] = [
                'genotype' => $genotype,
                'probability' => $weights[$i] / $total,
            ];
        }

        return $outcomes;
    }

    /**
     * Enumerate all possible offspring combinations from sire and dam gene strings, with probabilities as percentages.
     * Only base (Punnett) genes are considered; percentage-type genes are skipped.
     *
     * @param  array<string>  $sireGenes  One genotype per gene, in same order as dictionary keys (e.g. ['Ee', 'Aa', 'nZ']).
     * @param  array<string>  $damGenes
     * @return array<int, array{genotype: list<string>, probability: float, percentage: string}>
     */
    public function getBreedingOutcomes(array $sireGenes, array $damGenes): array
    {
        $data = $this->getBaseDictionary();
        $dict = $data['dict'];
        $baseOdds = $data['odds']['base'];
        $geneNames = array_keys($dict);

        $perGeneOutcomes = [];
        foreach ($geneNames as $index => $geneName) {
            $entry = $dict[$geneName];
            if (($entry['oddsType'] ?? '') !== 'base') {
                continue;
            }
            $alleles = $entry['alleles'];
            $sireRaw = $sireGenes[$index] ?? '';
            $damRaw = $damGenes[$index] ?? '';
            if ($sireRaw === '' || $damRaw === '') {
                continue;
            }
            $sireAlleles = $this->parseParentGenotype($sireRaw, $alleles);
            $damAlleles = $this->parseParentGenotype($damRaw, $alleles);
            $perGeneOutcomes[$geneName] = $this->getPunnettOutcomesForGene($sireAlleles, $damAlleles, $alleles, $baseOdds);
        }

        if ($perGeneOutcomes === []) {
            return [];
        }

        $combined = $this->cartesianProductWithProbabilities($perGeneOutcomes);
        $aggregated = $this->aggregateProbabilities($combined);
        $this->sortGenotypeByDominance($aggregated, $dict);
        uasort($aggregated, fn (float $a, float $b): int => $b <=> $a);

        $result = [];
        foreach ($aggregated as $genotypeKey => $probability) {
            $tokens = explode("\0", $genotypeKey);
            $result[] = [
                'genotype' => $tokens,
                'probability' => $probability,
                'percentage' => $this->formatPercentage($probability),
            ];
        }

        return $result;
    }

    /**
     * Cartesian product of per-gene outcomes; each row is (genotype key, probability).
     *
     * @param  array<string, array<int, array{genotype: string, probability: float}>>  $perGeneOutcomes
     * @return array<int, array{key: string, probability: float}>
     */
    private function cartesianProductWithProbabilities(array $perGeneOutcomes): array
    {
        $genes = array_keys($perGeneOutcomes);
        $first = array_shift($genes);
        $product = [];
        foreach ($perGeneOutcomes[$first] as $out) {
            $product[] = ['key' => $out['genotype'], 'probability' => $out['probability']];
        }

        foreach ($genes as $geneName) {
            $newProduct = [];
            foreach ($product as $row) {
                foreach ($perGeneOutcomes[$geneName] as $out) {
                    $newProduct[] = [
                        'key' => $row['key']."\0".$out['genotype'],
                        'probability' => $row['probability'] * $out['probability'],
                    ];
                }
            }
            $product = $newProduct;
        }

        return $product;
    }

    /**
     * Aggregate identical genotype keys by summing probabilities.
     *
     * @param  array<int, array{key: string, probability: float}>  $combined
     * @return array<string, float> genotype key => probability
     */
    private function aggregateProbabilities(array $combined): array
    {
        $agg = [];
        foreach ($combined as $row) {
            $k = $row['key'];
            $agg[$k] = ($agg[$k] ?? 0) + $row['probability'];
        }

        return $agg;
    }

    /**
     * Sort aggregated array so genotype keys are ordered by gene dominance (dict order).
     * In place by key; we only need consistent order for output.
     */
    private function sortGenotypeByDominance(array &$aggregated, array $dict): void
    {
        uksort($aggregated, function (string $a, string $b): int {
            $ta = explode("\0", $a);
            $tb = explode("\0", $b);
            for ($i = 0; $i < min(count($ta), count($tb)); $i++) {
                $c = strcmp($ta[$i], $tb[$i]);
                if ($c !== 0) {
                    return $c;
                }
            }

            return count($ta) <=> count($tb);
        });
    }

    private function formatPercentage(float $probability): string
    {
        $pct = round($probability * 100, 2);

        return $pct === (float) (int) $pct ? (string) (int) $pct : (string) $pct;
    }
}
