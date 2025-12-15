<?php

use Pusher\Pusher;

test('pusher configuration is properly set', function () {
    $key = config('broadcasting.connections.pusher.key');
    $secret = config('broadcasting.connections.pusher.secret');
    $appId = config('broadcasting.connections.pusher.app_id');
    $cluster = config('broadcasting.connections.pusher.options.cluster');

    expect($key)->not->toBeNull()->not->toBeEmpty();
    expect($secret)->not->toBeNull()->not->toBeEmpty();
    expect($appId)->not->toBeNull()->not->toBeEmpty();
    expect($cluster)->not->toBeNull()->not->toBeEmpty();
});

test('pusher connection is successful', function () {
    $pusher = new Pusher(
        config('broadcasting.connections.pusher.key'),
        config('broadcasting.connections.pusher.secret'),
        config('broadcasting.connections.pusher.app_id'),
        [
            'cluster' => config('broadcasting.connections.pusher.options.cluster'),
            'useTLS' => true,
        ]
    );

    // Trigger a test event on a test channel
    $response = $pusher->trigger('test-channel', 'test-event', [
        'message' => 'Connection test successful',
        'timestamp' => now()->toISOString(),
    ]);

    expect($response)->toBeObject();
});

test('pusher can get channel info', function () {
    $pusher = new Pusher(
        config('broadcasting.connections.pusher.key'),
        config('broadcasting.connections.pusher.secret'),
        config('broadcasting.connections.pusher.app_id'),
        [
            'cluster' => config('broadcasting.connections.pusher.options.cluster'),
            'useTLS' => true,
        ]
    );

    // Get channels info - this validates the API connection
    $channels = $pusher->getChannels();

    expect($channels)->toBeObject();
});

