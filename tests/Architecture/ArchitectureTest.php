<?php

use App\Providers\AppServiceProvider;
use App\Http\Controllers\Controller;

arch()->preset()->php();

// AppServiceProvider is exempted for a false positive: its sha1($email) builds the
// email-verification {hash} param — Laravel's own idiom; the URL is HMAC-signed.
arch()->preset()->security()->ignoring(['Database\Seeders', 'Database\Factories', AppServiceProvider::class]);

arch('app classes to be final, except data, exceptions, helpers, providers, and the abstract base Controller')
    ->expect('App')
    ->classes()
    ->toBeFinal()
    ->ignoring(['App\Data', 'App\Exceptions', 'App\Helpers', 'App\Providers', Controller::class]);
