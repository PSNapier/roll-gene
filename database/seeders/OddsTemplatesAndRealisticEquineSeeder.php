<?php

namespace Database\Seeders;

use App\Models\OddsTemplate;
use App\Models\Roller;
use Illuminate\Database\Seeder;

class OddsTemplatesAndRealisticEquineSeeder extends Seeder
{
    public function run(): void
    {
        $punnett = OddsTemplate::firstOrCreate(
            ['type' => 'punnett', 'name' => 'Default Punnett'],
            [
                'config' => [
                    'roll1' => 25,
                    'roll2' => 25,
                    'roll3' => 25,
                    'roll4' => 25,
                ],
            ]
        );

        $percentage = OddsTemplate::firstOrCreate(
            ['type' => 'percentage', 'name' => 'Default Percentage'],
            [
                'config' => [
                    'domXdom' => ['dom' => 100],
                    'domXrec' => ['dom' => 100],
                    'domXnone' => ['rec' => 50],
                    'recXrec' => ['dom' => 50, 'rec' => 50],
                    'recXnone' => ['rec' => 50, 'none' => 50],
                    'noneXnone' => ['none' => 100],
                ],
            ]
        );

        $punnettConfig = is_array($punnett->config) ? $punnett->config : [];
        $percentageConfig = is_array($percentage->config) ? $percentage->config : [];

        Roller::firstOrCreate(
            ['slug' => 'realistic-equine'],
            [
                'user_id' => null,
                'name' => 'Realistic Equine',
                'is_core' => true,
                'genes_dict' => [
                    'black' => ['oddsType' => 'punnett', 'alleles' => ['E', 'e']],
                    'agouti' => ['oddsType' => 'punnett', 'alleles' => ['At', 'A', 'a']],
                    'silver' => ['oddsType' => 'percentage', 'alleles' => ['Z']],
                ],
                'punnett_odds' => $punnettConfig,
                'percentage_odds' => $percentageConfig,
                'visibility' => 'public',
                'group_id' => null,
            ]
        );
    }
}
