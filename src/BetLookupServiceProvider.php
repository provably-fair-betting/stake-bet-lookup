<?php

namespace Stake\BetLookup;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Stake\BetLookup\Console\Commands\CheckClearanceCommand;
use Stake\BetLookup\Console\Commands\UpdateClearanceCommand;
use GuzzleHttp\HandlerStack;
use Stake\BetLookup\Services\BetNormalizerService;
use Stake\BetLookup\Services\ClearanceAlerter;
use Stake\BetLookup\Services\ClearanceRepository;
use Stake\BetLookup\Services\StakeApiService;
use Stake\BetLookup\Services\StakeHttpClientFactory;

class BetLookupServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/bet-lookup.php', 'bet-lookup');

        $this->app->singleton(ClearanceRepository::class, fn () => new ClearanceRepository(config('bet-lookup')));

        $this->app->singleton(ClearanceAlerter::class, fn () => new ClearanceAlerter(config('bet-lookup')));

        $this->app->singleton(StakeHttpClientFactory::class, fn () => new StakeHttpClientFactory(
            config('bet-lookup'),
            HandlerStack::create()
        ));

        $this->app->singleton(StakeApiService::class, function ($app) {
            return new StakeApiService(
                $app->make(ClearanceRepository::class),
                $app->make(ClearanceAlerter::class),
                $app->make(StakeHttpClientFactory::class),
            );
        });

        $this->app->singleton(BetNormalizerService::class, fn () => new BetNormalizerService());
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/bet-lookup.php' => config_path('bet-lookup.php'),
            ], 'bet-lookup-config');

            $this->publishes([
                __DIR__ . '/../database/migrations/' => database_path('migrations'),
            ], 'bet-lookup-migrations');

            $this->publishes([
                __DIR__ . '/../scripts/' => base_path('stake-clearance'),
            ], 'bet-lookup-scripts');

            $this->publishes([
                __DIR__ . '/../bruno/' => base_path('stake-bruno'),
            ], 'bet-lookup-bruno');

            $this->commands([
                CheckClearanceCommand::class,
                UpdateClearanceCommand::class,
            ]);
        }

        RateLimiter::for('bet-lookup', function (Request $request) {
            return Limit::perMinute(config('bet-lookup.rate_limit', 60))->by($request->ip());
        });

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
    }
}
