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
            'noneXnone' => ['none' => 100],
        ];
    }

    /**
     * Classify a parent's genotype for a percentage gene as dom, rec, or none.
     * Single allele (e.g. 'Z') → dom = ZZ, rec = nZ, none = absent/empty.
     *
     * @param  array<string>  $alleles  One allele for percentage genes (e.g. ['Z']).
     * @return 'dom'|'rec'|'none'
     */
    public function classifyPercentageParent(string $genotype, array $alleles): string
    {
        $genotype = trim($genotype);
        if ($genotype === '') {
            return 'none';
        }
        $a = $alleles[0];
        $dom = $a.$a;
        $rec = 'n'.$a;
        if ($genotype === $dom) {
            return 'dom';
        }
        if ($genotype === $rec) {
            return 'rec';
        }

        throw new \InvalidArgumentException("Genotype \"{$genotype}\" is not valid for percentage gene (expected {$dom}, {$rec}, or empty).");
    }

    /**
     * Map a percentage outcome (dom/rec/none) to genotype string for the given allele.
     *
     * @param  array<string>  $alleles  One allele (e.g. ['Z']).
     */
    public function percentageOutcomeToGenotype(string $outcome, array $alleles): string
    {
        $a = $alleles[0];

        return match ($outcome) {
            'dom' => $a.$a,
            'rec' => 'n'.$a,
            'none' => '',
            default => throw new \InvalidArgumentException("Invalid percentage outcome: {$outcome}."),
        };
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
     * Check if a genotype string is valid for the given alleles (two alleles, longest-first match).
     *
     * @param  array<string>  $alleles
     */
    public function isValidGenotype(string $genotype, array $alleles): bool
    {
        try {
            $this->parseParentGenotype($genotype, $alleles);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Check if a genotype is valid for a percentage gene (dom=AA, rec=nA, none=empty).
     *
     * @param  array<string>  $alleles  One allele (e.g. ['Z']).
     */
    public function isValidPercentageGenotype(string $genotype, array $alleles): bool
    {
        $genotype = trim($genotype);
        if ($alleles === []) {
            return false;
        }
        $a = $alleles[0];

        return $genotype === '' || $genotype === $a.$a || $genotype === 'n'.$a;
    }

    /**
     * Assign tokens to genes by validity; order of input does not matter.
     * Returns an array in dictionary order (one entry per gene).
     *
     * @param  array<string>  $tokens
     * @return array<int, string>
     *
     * @throws \InvalidArgumentException When assignment is impossible.
     */
    public function tokensToOrderedGenes(array $tokens): array
    {
        $data = $this->getBaseDictionary();
        $dict = $data['dict'];
        $geneNames = array_keys($dict);

        $baseGeneNames = [];
        foreach ($geneNames as $name) {
            if (($dict[$name]['oddsType'] ?? '') === 'base') {
                $baseGeneNames[] = $name;
            }
        }

        if (count($tokens) < count($baseGeneNames)) {
            throw new \InvalidArgumentException(
                'Not enough gene values: need '.count($baseGeneNames).' (for '.implode(', ', $baseGeneNames).'), got '.count($tokens).'.'
            );
        }

        $used = array_fill(0, count($tokens), false);
        $assignment = [];

        foreach ($baseGeneNames as $geneName) {
            $alleles = $dict[$geneName]['alleles'];
            $found = null;
            foreach ($tokens as $j => $token) {
                if ($used[$j]) {
                    continue;
                }
                if ($this->isValidGenotype($token, $alleles)) {
                    $found = $j;
                    break;
                }
            }
            if ($found === null) {
                throw new \InvalidArgumentException(
                    'No valid value for gene "'.$geneName.'" (expected two alleles from ['.implode(', ', $alleles).']).'
                );
            }
            $used[$found] = true;
            $assignment[$geneName] = $tokens[$found];
        }

        foreach ($geneNames as $geneName) {
            if (($dict[$geneName]['oddsType'] ?? '') !== 'percentage') {
                continue;
            }
            $alleles = $dict[$geneName]['alleles'];
            foreach ($tokens as $j => $token) {
                if ($used[$j]) {
                    continue;
                }
                if ($this->isValidPercentageGenotype($token, $alleles)) {
                    $used[$j] = true;
                    $assignment[$geneName] = $token;
                    break;
                }
            }
        }

        $result = [];
        foreach ($geneNames as $name) {
            $result[] = $assignment[$name] ?? '';
        }

        return $result;
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
     * All possible offspring outcomes for one percentage gene, with probabilities from the odds table.
     * Sire and dam are classified as dom/rec/none; odds key is sireClass.'X'.damClass.
     *
     * @param  'dom'|'rec'|'none'  $sireClass
     * @param  'dom'|'rec'|'none'  $damClass
     * @param  array<string>  $alleles  One allele (e.g. ['Z']).
     * @param  array<string, array<string, int>>  $percentageOdds
     * @return array<int, array{genotype: string, probability: float}>
     */
    public function getPercentageOutcomesForGene(string $sireClass, string $damClass, array $alleles, array $percentageOdds): array
    {
        $key = $sireClass.'X'.$damClass;
        $bands = $percentageOdds[$key] ?? ['none' => 100];
        $total = (float) array_sum($bands);
        if ($total <= 0) {
            $bands = ['none' => 100];
            $total = 100.0;
        }

        $outcomes = [];
        foreach ($bands as $outcome => $weight) {
            $outcomes[] = [
                'genotype' => $this->percentageOutcomeToGenotype($outcome, $alleles),
                'probability' => $weight / $total,
            ];
        }

        return $outcomes;
    }

    /**
     * Enumerate all possible offspring combinations from sire and dam gene strings, with probabilities as percentages.
     * Base genes use Punnett odds; percentage genes use the configured percentage odds (dom/rec/none).
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
        $percentageOdds = $data['odds']['percentage'];
        $geneNames = array_keys($dict);

        $perGeneOutcomes = [];
        foreach ($geneNames as $index => $geneName) {
            $entry = $dict[$geneName];
            $alleles = $entry['alleles'];
            $sireRaw = trim($sireGenes[$index] ?? '');
            $damRaw = trim($damGenes[$index] ?? '');

            if (($entry['oddsType'] ?? '') === 'base') {
                if ($sireRaw === '' || $damRaw === '') {
                    continue;
                }
                $sireAlleles = $this->parseParentGenotype($sireRaw, $alleles);
                $damAlleles = $this->parseParentGenotype($damRaw, $alleles);
                $perGeneOutcomes[$geneName] = $this->getPunnettOutcomesForGene($sireAlleles, $damAlleles, $alleles, $baseOdds);

                continue;
            }

            if (($entry['oddsType'] ?? '') === 'percentage') {
                $sireClass = $this->classifyPercentageParent($sireRaw, $alleles);
                $damClass = $this->classifyPercentageParent($damRaw, $alleles);
                $perGeneOutcomes[$geneName] = $this->getPercentageOutcomesForGene($sireClass, $damClass, $alleles, $percentageOdds);
            }
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
