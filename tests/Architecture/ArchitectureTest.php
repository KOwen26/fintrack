<?php

use App\Http\Controllers\Controller;
use App\Http\Requests\MonthPeriodRequest;
use App\Providers\AppServiceProvider;

arch()->preset()->php();

// AppServiceProvider is exempted for a false positive: its sha1($email) builds the
// email-verification {hash} param — Laravel's own idiom; the URL is HMAC-signed.
arch()->preset()->security()->ignoring(['Database\Seeders', 'Database\Factories', AppServiceProvider::class]);

arch('app classes to be final, except data, exceptions, helpers, providers, and the abstract base Controller')
    ->expect('App')
    ->classes()
    ->toBeFinal()
    // MonthPeriodRequest is the only extendable FormRequest — StatisticsIndexRequest
    // subclasses it to reuse the month window alongside the trend span params.
    ->ignoring(['App\Data', 'App\Exceptions', 'App\Helpers', 'App\Providers', Controller::class, MonthPeriodRequest::class]);
