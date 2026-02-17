<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Roller extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'is_core',
        'genes_dict',
        'pheno_dict',
        'punnett_odds',
        'percentage_odds',
        'visibility',
        'group_id',
    ];

    protected function casts(): array
    {
        return [
            'is_core' => 'boolean',
            'genes_dict' => 'array',
            'pheno_dict' => 'array',
            'punnett_odds' => 'array',
            'percentage_odds' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Rollers the user can see (owner, public, or group member).
     *
     * @param  Builder<Roller>  $query
     */
    public function scopeVisibleTo(Builder $query, ?User $user): void
    {
        $query->where(function (Builder $q) use ($user): void {
            $q->where('visibility', 'public');
            if ($user) {
                $q->orWhere('user_id', $user->id);
                $q->orWhereHas('group', fn (Builder $g) => $g->whereHas('members', fn (Builder $m) => $m->where('user_id', $user->id)));
            }
        });
    }

    /**
     * Normalize phenos to sectioned format. Legacy flat list becomes one section.
     *
     * @return array<int, array{name: string, match_mode: string, phenos: array<int, array{name: string, alleles: array<string>}>}>
     */
    public function getPhenoSections(): array
    {
        $phenoDict = $this->pheno_dict ?? [];
        if (empty($phenoDict)) {
            return [];
        }
        $first = $phenoDict[0] ?? null;
        if (is_array($first) && ! isset($first['match_mode']) && isset($first['name'], $first['alleles'])) {
            return [
                [
                    'name' => 'Base',
                    'match_mode' => 'first_dominant',
                    'phenos' => $phenoDict,
                ],
            ];
        }

        return $phenoDict;
    }

    /**
     * @return array{oddsDict: array{punnett: array<string, int>, percentage: array<string, array<string, int>>}, genesDict: array<string, array{oddsType: string, alleles: array<string>}>}
     */
    public function toGeneticsArray(): array
    {
        return [
            'oddsDict' => [
                'punnett' => $this->punnett_odds ?? [],
                'percentage' => $this->percentage_odds ?? [],
            ],
            'genesDict' => $this->genes_dict ?? [],
        ];
    }
}
