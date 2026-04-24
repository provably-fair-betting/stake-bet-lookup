<?php

namespace Stake\BetLookup\Tests\Unit;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use Stake\BetLookup\Services\StakeHttpClientFactory;

class StakeHttpClientFactoryTest extends \PHPUnit\Framework\TestCase
{
    private function makeFactory(array $configOverrides, array &$history): StakeHttpClientFactory
    {
        $stack = HandlerStack::create(new MockHandler([new Response(200), new Response(200)]));
        $stack->push(Middleware::history($history));

        return new StakeHttpClientFactory(array_merge([
            'stake_api_url'      => 'https://example.com/graphql',
            'stake_access_token' => null,
            'timeout'            => 10,
        ], $configOverrides), $stack);
    }

    // -------------------------------------------------------------------------
    // makeApiClient
    // -------------------------------------------------------------------------

    #[Test]
    public function it_sets_cookie_header_when_clearance_cookie_provided(): void
    {
        $history = [];
        $this->makeFactory([], $history)
            ->makeApiClient(['clearance_cookie' => 'abc123', 'user_agent' => null])
            ->post('', ['json' => []]);

        $this->assertEquals('cf_clearance=abc123', $history[0]['request']->getHeaderLine('Cookie'));
    }

    #[Test]
    public function it_sets_user_agent_header_when_provided(): void
    {
        $history = [];
        $this->makeFactory([], $history)
            ->makeApiClient(['clearance_cookie' => null, 'user_agent' => 'Mozilla/5.0 Test'])
            ->post('', ['json' => []]);

        $this->assertEquals('Mozilla/5.0 Test', $history[0]['request']->getHeaderLine('User-Agent'));
    }

    #[Test]
    public function it_sets_access_token_header_when_configured(): void
    {
        $history = [];
        $this->makeFactory(['stake_access_token' => 'my-token'], $history)
            ->makeApiClient(['clearance_cookie' => null, 'user_agent' => null])
            ->post('', ['json' => []]);

        $this->assertEquals('my-token', $history[0]['request']->getHeaderLine('x-access-token'));
    }

    #[Test]
    public function it_omits_optional_headers_when_not_configured(): void
    {
        $history = [];
        $this->makeFactory([], $history)
            ->makeApiClient(['clearance_cookie' => null, 'user_agent' => null])
            ->post('', ['json' => []]);

        $request = $history[0]['request'];
        $this->assertEmpty($request->getHeaderLine('Cookie'));
        $this->assertEmpty($request->getHeaderLine('x-access-token'));
    }

    // -------------------------------------------------------------------------
    // makeProbeClient
    // -------------------------------------------------------------------------

    #[Test]
    public function probe_client_sends_request_successfully(): void
    {
        $history = [];
        $this->makeFactory([], $history)
            ->makeProbeClient()
            ->get('https://example.com');

        $this->assertCount(1, $history);
        $this->assertEquals('GET', $history[0]['request']->getMethod());
    }
}
