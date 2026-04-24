<?php

namespace Stake\BetLookup\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Stake\BetLookup\BetLookupServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [BetLookupServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('bet-lookup.stake_api_url', 'https://stake.games/_api/graphql');
        $app['config']->set('bet-lookup.stake_access_token', 'test-token');
        $app['config']->set('bet-lookup.admin_token', hash('sha256', 'test-admin-token'));
        $app['config']->set('bet-lookup.rate_limit', 60);
        $app['config']->set('bet-lookup.timeout', 10);
    }

    protected function adminToken(): string
    {
        return 'test-admin-token';
    }
}
