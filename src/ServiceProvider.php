<?php

namespace Pencilled\Statamic;

use Illuminate\Console\Scheduling\Schedule;
use Pencilled\Statamic\Commands\SyncCommand;
use Pencilled\Statamic\Tags\Pencilled;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    /**
     * Disable slug-based config discovery — the package is pencilled/statamic,
     * so the config is registered manually as `pencilled` instead.
     *
     * @var bool
     */
    protected $config = false;

    protected $commands = [
        SyncCommand::class,
    ];

    protected $tags = [
        Pencilled::class,
    ];

    public function register()
    {
        parent::register();

        $this->mergeConfigFrom(__DIR__.'/../config/pencilled.php', 'pencilled');

        $this->app->singleton(PencilledApi::class);
        $this->app->singleton(EventSync::class);
    }

    public function bootAddon()
    {
        $this->publishes([
            __DIR__.'/../config/pencilled.php' => config_path('pencilled.php'),
        ], 'pencilled-config');

        // Registered outside the web middleware group so the webhook is not
        // subject to CSRF verification — it is authenticated by signature.
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    }

    protected function schedule(Schedule $schedule)
    {
        if (config('pencilled.schedule', true)) {
            $schedule->command('pencilled:sync')->everyTenMinutes();
        }
    }
}
