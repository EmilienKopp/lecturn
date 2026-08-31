<?php

use App\Models\User;

test('workos authentication attributes are mass assignable', function () {
    $user = new User;

    $user->fill([
        'name' => 'Taylor Otwell',
        'email' => 'taylor@example.com',
        'email_verified_at' => now(),
        'workos_id' => 'user_01ABC',
        'avatar' => 'https://example.com/avatar.png',
    ]);

    expect($user->email_verified_at)->not->toBeNull()
        ->and($user->workos_id)->toBe('user_01ABC');
});
