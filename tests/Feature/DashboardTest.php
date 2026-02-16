<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('genetics', fn (Assert $page) => $page
            ->has('odds')
            ->has('dict')
            ->where('dict.black.oddsType', 'punnett')
            ->where('dict.black.alleles', ['E', 'e'])
            ->where('dict.silver.oddsType', 'percentage')
        )
    );
});

test('authenticated users can roll breeding outcomes', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('dashboard.roll'), [
        'sire_genes' => 'Ee Aa',
        'dam_genes' => 'Ee Aa',
    ]);

    $response->assertRedirect(route('dashboard'));

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('genetics')
        ->has('outcomes')
        ->where('outcomes', fn ($outcomes) => count($outcomes) > 0 && isset($outcomes[0]['genotype'], $outcomes[0]['percentage']))
    );
});

test('gene input order does not matter for roll', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('dashboard.roll'), [
        'sire_genes' => 'aa ee',
        'dam_genes' => 'ee aa',
    ]);

    $response->assertRedirect(route('dashboard'));

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('outcomes')
        ->where('outcomes', fn ($outcomes) => count($outcomes) > 0)
    );
});
