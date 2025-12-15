<?php

use App\Models\User;

test('user has first name, middle name, and last name fields', function () {
    $user = User::factory()->create([
        'first_name' => 'John',
        'middle_name' => 'William',
        'last_name' => 'Doe',
    ]);

    expect($user->first_name)->toBe('John');
    expect($user->middle_name)->toBe('William');
    expect($user->last_name)->toBe('Doe');
});

test('user full name includes all name parts', function () {
    $user = User::factory()->create([
        'first_name' => 'John',
        'middle_name' => 'William',
        'last_name' => 'Doe',
    ]);

    expect($user->full_name)->toBe('John William Doe');
});

test('user full name works without middle name', function () {
    $user = User::factory()->create([
        'first_name' => 'Jane',
        'middle_name' => null,
        'last_name' => 'Smith',
    ]);

    expect($user->full_name)->toBe('Jane Smith');
});

test('user full name is included in json serialization', function () {
    $user = User::factory()->create([
        'first_name' => 'John',
        'middle_name' => null,
        'last_name' => 'Doe',
    ]);

    $array = $user->toArray();

    expect($array)->toHaveKey('full_name');
    expect($array['full_name'])->toBe('John Doe');
});

test('user can be created with factory', function () {
    $user = User::factory()->create();

    expect($user->first_name)->not->toBeNull();
    expect($user->last_name)->not->toBeNull();
    expect($user->email)->not->toBeNull();
});

test('user can have a profile picture', function () {
    $user = User::factory()->create([
        'profile_picture' => 'avatars/user-123.jpg',
    ]);

    expect($user->profile_picture)->toBe('avatars/user-123.jpg');
});

test('user profile picture is nullable', function () {
    $user = User::factory()->create([
        'profile_picture' => null,
    ]);

    expect($user->profile_picture)->toBeNull();
});

