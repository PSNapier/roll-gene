<?php

namespace Database\Factories;

use App\Models\Roller;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Roller>
 */
class RollerFactory extends Factory
{
    protected $model = Roller::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);
        $slug = Str::slug($name);

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => $slug,
            'is_core' => false,
            'dictionary' => [
                'black' => ['oddsType' => 'punnett', 'alleles' => ['E', 'e']],
                'agouti' => ['oddsType' => 'punnett', 'alleles' => ['At', 'A', 'a']],
                'silver' => ['oddsType' => 'percentage', 'alleles' => ['Z']],
            ],
            'punnett_odds' => ['roll1' => 25, 'roll2' => 25, 'roll3' => 25, 'roll4' => 25],
            'percentage_odds' => [
                'domXdom' => ['dom' => 100],
                'domXrec' => ['dom' => 100],
                'domXnone' => ['rec' => 50],
                'recXrec' => ['dom' => 50, 'rec' => 50],
                'recXnone' => ['rec' => 50],
                'noneXnone' => ['none' => 100],
            ],
            'visibility' => 'private',
            'group_id' => null,
        ];
    }
}
