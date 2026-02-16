<?php

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Roller;
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
        ->has('rollers')
    );
});

test('authenticated users can roll breeding outcomes', function () {
    $roller = Roller::factory()->create([
        'name' => 'Realistic Equine',
        'slug' => 'realistic-equine',
        'visibility' => 'public',
    ]);
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('rollers.roll', ['roller' => 'realistic-equine']), [
        'sire_genes' => 'Ee Aa nZ',
        'dam_genes' => 'Ee Aa nZ',
    ]);

    $response->assertRedirect(route('rollers.show', ['roller' => 'realistic-equine']));

    $response = $this->get(route('rollers.show', ['roller' => 'realistic-equine']));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('RollerShow')
        ->has('genetics')
        ->has('outcomes')
        ->where('outcomes', fn ($outcomes) => count($outcomes) > 0 && isset($outcomes[0]['genotype'], $outcomes[0]['percentage']))
    );
});

test('gene input order does not matter for roll', function () {
    Roller::factory()->create([
        'name' => 'Realistic Equine',
        'slug' => 'realistic-equine',
        'visibility' => 'public',
    ]);
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('rollers.roll', ['roller' => 'realistic-equine']), [
        'sire_genes' => 'aa ee nZ',
        'dam_genes' => 'ee aa nZ',
    ]);

    $response->assertRedirect(route('rollers.show', ['roller' => 'realistic-equine']));

    $response = $this->get(route('rollers.show', ['roller' => 'realistic-equine']));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('RollerShow')
        ->has('outcomes')
        ->where('outcomes', fn ($outcomes) => count($outcomes) > 0)
    );
});

test('group member can view group roller and sees it on dashboard', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $group = Group::create(['name' => 'Test Group', 'owner_id' => $owner->id]);
    GroupMember::create(['group_id' => $group->id, 'user_id' => $member->id, 'role' => 'viewer']);
    $roller = Roller::factory()->create([
        'user_id' => $owner->id,
        'name' => 'Group Roller',
        'slug' => 'group-roller',
        'visibility' => 'group',
        'group_id' => $group->id,
    ]);

    $this->actingAs($member);
    $response = $this->get(route('rollers.show', ['roller' => $roller->slug]));
    $response->assertOk();

    $response = $this->get(route('dashboard'));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('rollers', fn ($rollers) => collect($rollers)->contains('slug', 'group-roller'))
    );
});
