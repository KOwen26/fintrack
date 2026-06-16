<?php

use App\Http\Middleware\OnlyDevelopment;
use Inertia\Inertia;

Route::group([
    'prefix' => 'dev',
    'as' => 'dev.',
    'middleware' => [OnlyDevelopment::class],
], function (): void {
    Route::get('', fn () => Inertia::render('dev/test'))->name('test');
    Route::get('color', fn () => Inertia::render('dev/color'))->name('color');
    Route::get('design', fn () => Inertia::render('dev/design'))->name('design');
    Route::get('form', fn () => Inertia::render('dev/form'))->name('form');
});
