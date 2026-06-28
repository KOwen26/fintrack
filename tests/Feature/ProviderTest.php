<?php

use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores provider decorations data when created', function (): void {
    $provider = Provider::factory()->create([
        'decorations' => [
            'icon' => 'ph--bank-bold',
            'color' => 'blue-600',
        ],
    ]);

    expect($provider->decorations)->toMatchArray([
        'icon' => 'ph--bank-bold',
        'color' => 'blue-600',
    ]);
});
