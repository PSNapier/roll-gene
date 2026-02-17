<?php

use App\Models\Roller;
use App\Models\User;

test('admin can update any roller', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create(['is_admin' => false]);
    $roller = Roller::factory()->create(['user_id' => $owner->id, 'is_core' => false]);

    $newDict = [
        'extension' => ['oddsType' => 'punnett', 'alleles' => ['E', 'e']],
        'agouti' => ['oddsType' => 'percentage', 'alleles' => ['A']],
    ];

    $response = $this->actingAs($admin)->patch(route('rollers.update', $roller), [
        'genesDict' => $newDict,
    ]);

    $response->assertRedirect(route('rollers.show', $roller));
    $roller->refresh();
    expect($roller->genes_dict)->toBe($newDict);
});

test('owner can update their own non-core roller', function () {
    $user = User::factory()->create();
    $roller = Roller::factory()->create(['user_id' => $user->id, 'is_core' => false]);
    $newDict = [
        'gene' => ['oddsType' => 'punnett', 'alleles' => ['X', 'x']],
    ];

    $response = $this->actingAs($user)->patch(route('rollers.update', $roller), [
        'genesDict' => $newDict,
    ]);

    $response->assertRedirect(route('rollers.show', $roller));
    $roller->refresh();
    expect($roller->genes_dict)->toBe($newDict);
});

test('guest cannot update roller', function () {
    $roller = Roller::factory()->create(['is_core' => false]);

    $response = $this->patch(route('rollers.update', $roller), [
        'genesDict' => $roller->genes_dict,
    ]);

    $response->assertForbidden();
});

test('non-owner cannot update another users roller', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $roller = Roller::factory()->create(['user_id' => $owner->id, 'is_core' => false]);

    $response = $this->actingAs($other)->patch(route('rollers.update', $roller), [
        'genesDict' => ['x' => ['oddsType' => 'punnett', 'alleles' => ['A', 'a']]],
    ]);

    $response->assertForbidden();
});

test('show passes canEdit when user can update', function () {
    $user = User::factory()->create();
    $roller = Roller::factory()->create(['user_id' => $user->id, 'is_core' => false]);

    $response = $this->actingAs($user)->get(route('rollers.show', $roller));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('RollerShow')->has('canEdit')->where('canEdit', true));
});

test('show passes canEdit false when user cannot update', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $roller = Roller::factory()->create(['user_id' => $owner->id, 'visibility' => 'public', 'is_core' => false]);

    $response = $this->actingAs($other)->get(route('rollers.show', $roller));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('RollerShow')->has('canEdit')->where('canEdit', false));
});

test('owner can update phenos only', function () {
    $user = User::factory()->create();
    $roller = Roller::factory()->create(['user_id' => $user->id, 'is_core' => false]);

    $phenoDict = [
        [
            'name' => 'Base',
            'match_mode' => 'first_dominant',
            'phenos' => [
                ['name' => 'black', 'alleles' => ['E', 'e']],
                ['name' => 'chestnut', 'alleles' => ['e']],
            ],
        ],
    ];

    $response = $this->actingAs($user)->patch(route('rollers.update', $roller), [
        'phenoDict' => $phenoDict,
    ]);

    $response->assertRedirect(route('rollers.show', $roller));
    $roller->refresh();
    expect($roller->pheno_dict)->toBe($phenoDict);
});

test('show passes phenoSections to RollerShow', function () {
    $roller = Roller::factory()->create([
        'visibility' => 'public',
        'pheno_dict' => [['name' => 'bay', 'alleles' => ['A']]],
    ]);

    $response = $this->get(route('rollers.show', $roller));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('RollerShow')
        ->has('phenoSections')
        ->where('phenoSections', [
            ['name' => 'Base', 'match_mode' => 'first_dominant', 'phenos' => [['name' => 'bay', 'alleles' => ['A']]]],
        ]));
});
