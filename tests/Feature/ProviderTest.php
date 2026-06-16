<?php

use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores provider cosmetics data when created', function (): void {
    $provider = Provider::factory()->create([
        'cosmetics' => [
            'icon' => 'ph:bank-bold',
            'color' => '#2563eb',
        ],
    ]);

    expect($provider->cosmetics)->toMatchArray(['icon' => 'ph:bank-bold', 'color' => '#2563eb']);
});
