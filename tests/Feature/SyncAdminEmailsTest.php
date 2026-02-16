<?php

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Config;

test('sync admin emails sets is_admin for users in config list on login event', function () {
    Config::set('auth.admin_emails', ['admin@example.com']);

    $user = User::factory()->create([
        'email' => 'admin@example.com',
        'is_admin' => false,
    ]);

    event(new Login('web', $user, false));

    expect($user->fresh()->is_admin)->toBeTrue();
});

test('sync admin emails does not set is_admin for users not in config list', function () {
    Config::set('auth.admin_emails', ['admin@example.com']);

    $user = User::factory()->create([
        'email' => 'other@example.com',
        'is_admin' => false,
    ]);

    event(new Login('web', $user, false));

    expect($user->fresh()->is_admin)->toBeFalse();
});

test('sync admin emails updates all matching users', function () {
    Config::set('auth.admin_emails', ['first@example.com', 'second@example.com']);

    $user1 = User::factory()->create(['email' => 'first@example.com', 'is_admin' => false]);
    $user2 = User::factory()->create(['email' => 'second@example.com', 'is_admin' => false]);

    event(new Login('web', $user1, false));

    expect($user1->fresh()->is_admin)->toBeTrue();
    expect($user2->fresh()->is_admin)->toBeTrue();
});
