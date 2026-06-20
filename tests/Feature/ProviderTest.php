<?php

use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores provider decorations data when created', function (): void {
    $provider = Provider::factory()->create([
        'decorations' => [
            'icon' => ['id' => 'bank-bold', 'value' => 'ph:bank-bold'],
            'color' => ['id' => 'blue-600', 'value' => '#2563eb'],
        ],
    ]);

    expect($provider->decorations)->toMatchArray([
        'icon' => ['id' => 'bank-bold', 'value' => 'ph:bank-bold'],
        'color' => ['id' => 'blue-600', 'value' => '#2563eb'],
    ]);
});
