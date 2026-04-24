<?php

namespace Stake\BetLookup\Services;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

class StakeHttpClientFactory
{
    public function __construct(
        private readonly array $config,
        private readonly HandlerStack $handlerStack
    ) {
    }

    public function makeApiClient(array $credentials): Client
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];

        if (! empty($credentials['clearance_cookie'])) {
            $headers['Cookie'] = 'cf_clearance=' . $credentials['clearance_cookie'];
        }

        if (! empty($credentials['user_agent'])) {
            $headers['User-Agent'] = $credentials['user_agent'];
        }

        if (! empty($this->config['stake_access_token'])) {
            $headers['x-access-token'] = $this->config['stake_access_token'];
        }

        return new Client([
            'base_uri' => $this->config['stake_api_url'],
            'timeout'  => $this->config['timeout'] ?? 10,
            'headers'  => $headers,
            'handler'  => $this->handlerStack,
        ]);
    }

    public function makeProbeClient(): Client
    {
        return new Client([
            'timeout' => $this->config['timeout'] ?? 10,
            'handler' => $this->handlerStack,
        ]);
    }
}
