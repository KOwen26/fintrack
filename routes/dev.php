<?php

use App\Http\Middleware\OnlyDevelopment;
use App\Models\DecorationColor;
use Inertia\Inertia;

Route::group([
    'prefix' => 'dev',
    'as' => 'dev.',
    'middleware' => [OnlyDevelopment::class],
], function (): void {
    Route::get('test', function () {
        $colors = DecorationColor::query()
            ->whereLike('slug', '%-50')
            ->orWhereLike('slug', '%-100')
            ->orWhereLike('slug', '%-900')
            ->orWhereLike('slug', '%-950')
            // ->update(['status' => 'Inactive'])
            ->get();

        return response()->json($colors);
    });
    Route::get('', fn () => Inertia::render('dev/test'))->name('test');
    Route::get('color', fn () => Inertia::render('dev/color'))->name('color');
    Route::get('design', fn () => Inertia::render('dev/design'))->name('design');
    Route::get('form', fn () => Inertia::render('dev/form'))->name('form');
});
