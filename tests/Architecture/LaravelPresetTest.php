<?php

use Illuminate\Support\ServiceProvider;
use App\Providers\TelescopeServiceProvider;
use Illuminate\Contracts\Container\ContextualAttribute;
use Illuminate\Console\Command;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

// Since the pest arch preset can't be excluded and folded into single test case, then a split is necessary for more controls

describe('app', function (): void {
    arch('App\Providers to have the ServiceProvider suffix')
        ->expect('App\Providers')
        ->toHaveSuffix('ServiceProvider');

    arch('App\Providers to extend the framework ServiceProvider')
        ->expect('App\Providers')
        ->classes()
        ->toExtend(ServiceProvider::class);

    arch('App\Providers not to be used, except the local-only Telescope shim')
        ->expect('App\Providers')
        ->not->toBeUsed()
        ->ignoring(TelescopeServiceProvider::class);

    arch('App not to extend ServiceProvider outside App\Providers')
        ->expect('App')
        ->not->toExtend(ServiceProvider::class)
        ->ignoring('App\Providers');

    arch('App not to have the ServiceProvider suffix outside App\Providers')
        ->expect('App')
        ->not->toHaveSuffix('ServiceProvider')
        ->ignoring('App\Providers');

    arch('App\Attributes to be contextual attributes')
        ->expect('App\Attributes')
        ->classes()
        ->toImplement(ContextualAttribute::class)
        ->toHaveAttribute('Attribute')
        ->toHaveMethod('resolve');

    arch('debugging and env helpers not to be used')
        ->expect(['dd', 'ddd', 'dump', 'env', 'exit', 'ray'])
        ->not->toBeUsed();
});

describe('console', function (): void {
    arch('App\Console\Commands to have the Command suffix')
        ->expect('App\Console\Commands')
        ->classes()
        ->toHaveSuffix('Command');

    arch('App\Console\Commands to extend the console Command')
        ->expect('App\Console\Commands')
        ->classes()
        ->toExtend(Command::class);

    arch('App\Console\Commands to have a handle method')
        ->expect('App\Console\Commands')
        ->classes()
        ->toHaveMethod('handle');

    arch('App not to extend the console Command outside App\Console\Commands')
        ->expect('App')
        ->not->toExtend(Command::class)
        ->ignoring('App\Console\Commands');
});

describe('enums', function (): void {
    arch('App not to contain enums outside App\Enums')
        ->expect('App')
        ->not->toBeEnums()
        ->ignoring('App\Enums');

    arch('App\Enums to be enums')
        ->expect('App\Enums')
        ->toBeEnums()
        ->ignoring('App\Enums\Concerns');
});

describe('events & listeners', function (): void {
    arch('App\Listeners to have a handle method')
        ->expect('App\Listeners')
        ->toHaveMethod('handle');
});

describe('exceptions', function (): void {
    arch('App\Exceptions to implement Throwable')
        ->expect('App\Exceptions')
        ->classes()
        ->toImplement('Throwable')
        ->ignoring('App\Exceptions\Handler');

    arch('App not to implement Throwable outside App\Exceptions')
        ->expect('App')
        ->not->toImplement(Throwable::class)
        ->ignoring('App\Exceptions');
});

describe('features', function (): void {
    arch('App\Features to be classes')
        ->expect('App\Features')
        ->toBeClasses()
        ->ignoring('App\Features\Concerns');

    arch('App\Features to have a resolve method')
        ->expect('App\Features')
        ->toHaveMethod('resolve')
        ->ignoring('App\Features\Concerns');
});

describe('http', function (): void {
    arch('App\Http\Middleware to have a handle method')
        ->expect('App\Http\Middleware')
        ->classes()
        ->toHaveMethod('handle');

    arch('App\Http\Requests to have the Request suffix')
        ->expect('App\Http\Requests')
        ->classes()
        ->toHaveSuffix('Request');

    arch('App\Http\Requests to extend FormRequest')
        ->expect('App\Http\Requests')
        ->classes()
        ->toExtend(FormRequest::class);

    arch('App\Http\Requests to have a rules method')
        ->expect('App\Http\Requests')
        ->toHaveMethod('rules');

    arch('App not to extend FormRequest outside App\Http\Requests')
        ->expect('App')
        ->not->toExtend(FormRequest::class)
        ->ignoring('App\Http\Requests');

    arch('App not to have the Controller suffix outside App\Http\Controllers')
        ->expect('App')
        ->not->toHaveSuffix('Controller')
        ->ignoring('App\Http\Controllers');

    arch('controllers to be classes suffixed with Controller')
        ->expect('App\Http\Controllers')
        ->classes()
        ->toHaveSuffix('Controller');

    arch('App\Http to only be used in App\Http and App\Providers')
        ->expect('App\Http')
        ->toOnlyBeUsedIn(['App\Http', 'App\Providers']);
});

describe('jobs', function (): void {
    arch('App\Jobs to be queueable')
        ->expect('App\Jobs')
        ->classes()
        ->toImplement(ShouldQueue::class);

    arch('App\Jobs to have a handle method')
        ->expect('App\Jobs')
        ->classes()
        ->toHaveMethod('handle');
});

describe('mail', function (): void {
    arch('App\Mail to extend Mailable')
        ->expect('App\Mail')
        ->classes()
        ->toExtend(Mailable::class);

    arch('App\Mail to be queueable')
        ->expect('App\Mail')
        ->classes()
        ->toImplement(ShouldQueue::class);

    arch('App not to extend Mailable outside App\Mail')
        ->expect('App')
        ->not->toExtend(Mailable::class)
        ->ignoring('App\Mail');

    arch('App\Notifications to extend Notification')
        ->expect('App\Notifications')
        ->classes()
        ->toExtend(Notification::class);

    arch('App not to extend Notification outside App\Notifications')
        ->expect('App')
        ->not->toExtend(Notification::class)
        ->ignoring('App\Notifications');
});

describe('models', function (): void {
    arch('App\Models to extend Eloquent Model')
        ->expect('App\Models')
        ->classes()
        ->toExtend(Model::class)
        ->ignoring(['App\Models\Scopes', 'App\Models\Concerns']);

    arch('App\Models not to have the Model suffix')
        ->expect('App\Models')
        ->classes()
        ->not->toHaveSuffix('Model');

    arch('App not to extend Eloquent Model outside App\Models')
        ->expect('App')
        ->not->toExtend(Model::class)
        ->ignoring('App\Models');
});

describe('policies', function (): void {
    arch('App\Policies to have the Policy suffix')
        ->expect('App\Policies')
        ->classes()
        ->toHaveSuffix('Policy');
});

describe('traits', function (): void {
    arch('App\Traits to be traits')
        ->expect('App\Traits')
        ->toBeTraits();

    arch('App\Concerns to be traits')
        ->expect('App\Concerns')
        ->toBeTraits();
});
