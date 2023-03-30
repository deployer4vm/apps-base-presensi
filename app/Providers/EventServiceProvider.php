<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        // \Illuminate\Database\Events\MigrationsStarted::class => [
        //     \App\Listeners\migrations\MigrationsStarted::class
        // ],
        // \Illuminate\Database\Events\MigrationsEnded::class => [
        //     \App\Listeners\migrations\MigrationsEnded::class
        // ],
        // \Illuminate\Database\Events\MigrationStarted::class => [
        //     \App\Listeners\migrations\MigrationStarted::class
        // ],
        \Illuminate\Database\Events\MigrationEnded::class => [
            \App\Listeners\migrations\MigrationEnded::class
        ]
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
    }
}
