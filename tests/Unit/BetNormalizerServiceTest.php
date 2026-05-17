<?php

namespace Stake\BetLookup\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stake\BetLookup\Exceptions\SeedNotRevealedException;
use Stake\BetLookup\Exceptions\StakeApiException;
use Stake\BetLookup\Services\BetNormalizerService;

class BetNormalizerServiceTest extends TestCase
{
    private BetNormalizerService $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new BetNormalizerService();
    }

    private function casinoBetData(string $game = 'dice', ?array $state = null): array
    {
        return [
            'bet' => [
                '__typename' => 'CasinoBet',
                'game'       => $game,
                'nonce'      => 7,
                'clientSeed' => ['seed' => 'myclient'],
                'serverSeed' => ['seed' => 'myserver', 'seedHash' => 'hashxyz'],
                'state'      => $state,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // normalize
    // -------------------------------------------------------------------------

    // Casino bets

    #[Test]
    public function it_normalizes_casino_bet(): void
    {
        $result = $this->normalizer->normalize($this->casinoBetData());

        $this->assertEquals('CasinoBet', $result['betType']);
        $this->assertEquals('dice', $result['game']);
        $this->assertEquals('myclient', $result['inputs']['clientSeed']);
        $this->assertEquals('myserver', $result['inputs']['serverSeed']);
        $this->assertEquals('hashxyz', $result['inputs']['serverSeedHash']);
        $this->assertEquals(7, $result['inputs']['nonce']);
        $this->assertArrayNotHasKey('difficulty', $result['inputs']);
        $this->assertArrayNotHasKey('minesCount', $result['inputs']);
        $this->assertArrayNotHasKey('risk', $result['inputs']);
    }

    #[Test]
    public function it_normalizes_casino_bet_for_various_games(): void
    {
        $games = ['dice', 'limbo', 'hilo', 'keno', 'blackjack', 'baccarat', 'roulette'];

        foreach ($games as $game) {
            $result = $this->normalizer->normalize($this->casinoBetData($game));
            $this->assertEquals($game, $result['game'], "Failed for game: {$game}");
        }
    }

    // State extraction — mines

    #[Test]
    public function it_includes_mines_count_in_inputs(): void
    {
        $result = $this->normalizer->normalize(
            $this->casinoBetData('mines', ['minesCount' => 5])
        );

        $this->assertEquals(5, $result['inputs']['minesCount']);
        $this->assertArrayNotHasKey('difficulty', $result['inputs']);
    }

    // State extraction — moles

    #[Test]
    public function it_includes_moles_count_in_inputs(): void
    {
        $result = $this->normalizer->normalize(
            $this->casinoBetData('moles', ['molesCount' => 3])
        );

        $this->assertEquals(3, $result['inputs']['molesCount']);
    }

    // State extraction — plinko

    #[Test]
    public function it_includes_plinko_risk_and_rows_in_inputs(): void
    {
        $result = $this->normalizer->normalize(
            $this->casinoBetData('plinko', ['plinkoRisk' => 'high', 'plinkoRows' => 16])
        );

        $this->assertEquals('high', $result['inputs']['risk']);
        $this->assertEquals(16, $result['inputs']['rows']);
    }

    // State extraction — wheel

    #[Test]
    public function it_includes_wheel_risk_and_segments_in_inputs(): void
    {
        $result = $this->normalizer->normalize(
            $this->casinoBetData('wheel', ['wheelRisk' => 'medium', 'wheelSegments' => 20])
        );

        $this->assertEquals('medium', $result['inputs']['risk']);
        $this->assertEquals(20, $result['inputs']['segments']);
    }

    // State extraction — bars

    #[Test]
    public function it_includes_bars_difficulty_and_tiles_in_inputs(): void
    {
        $result = $this->normalizer->normalize(
            $this->casinoBetData('bars', ['barsDifficulty' => 'hard', 'barsTiles' => 3])
        );

        $this->assertEquals('hard', $result['inputs']['difficulty']);
        $this->assertEquals(3, $result['inputs']['tiles']);
    }

    // State extraction — difficulty-only games

    #[Test]
    public function it_includes_difficulty_for_difficulty_based_games(): void
    {
        $cases = [
            ['cases',       ['casesDifficulty'       => 'easy']],
            ['chicken',     ['chickenDifficulty'      => 'medium']],
            ['darts',       ['dartsDifficulty'        => 'hard']],
            ['dragontower', ['dragonTowerDifficulty'  => 'expert']],
            ['pump',        ['pumpDifficulty'         => 'easy']],
            ['snakes',      ['snakesDifficulty'       => 'master']],
            ['tarot',       ['tarotDifficulty'        => 'hard']],
        ];

        foreach ($cases as [$game, $state]) {
            $result = $this->normalizer->normalize($this->casinoBetData($game, $state));
            $this->assertArrayHasKey('difficulty', $result['inputs'], "Missing difficulty for {$game}");
            $this->assertArrayNotHasKey('risk', $result['inputs']);
            $this->assertArrayNotHasKey('minesCount', $result['inputs']);
        }
    }

    // Multiplayer crash bets

    #[Test]
    public function it_normalizes_multiplayer_crash_bet(): void
    {
        $result = $this->normalizer->normalize([
            'bet' => [
                '__typename' => 'MultiplayerCrashBet',
                'crashGame'  => [
                    'seed' => ['seed' => 'crashseed'],
                    'hash' => ['hash' => 'crashhash'],
                ],
            ],
        ]);

        $this->assertEquals('MultiplayerCrashBet', $result['betType']);
        $this->assertEquals('crash', $result['game']);
        $this->assertEquals('crashseed', $result['inputs']['serverSeed']);
        $this->assertEquals('crashhash', $result['inputs']['gameHash']);
        $this->assertArrayNotHasKey('nonce', $result['inputs']);
    }

    // Multiplayer slide bets

    #[Test]
    public function it_normalizes_multiplayer_slide_bet(): void
    {
        $result = $this->normalizer->normalize([
            'bet' => [
                '__typename' => 'MultiplayerSlideBet',
                'slideGame'  => [
                    'seed' => ['seed' => 'slideseed'],
                    'hash' => ['hash' => 'slidehash'],
                ],
            ],
        ]);

        $this->assertEquals('MultiplayerSlideBet', $result['betType']);
        $this->assertEquals('slide', $result['game']);
        $this->assertEquals('slideseed', $result['inputs']['serverSeed']);
        $this->assertEquals('slidehash', $result['inputs']['gameHash']);
        $this->assertArrayNotHasKey('nonce', $result['inputs']);
    }

    // Unknown bet type

    #[Test]
    public function it_throws_exception_for_unknown_bet_type(): void
    {
        $this->expectException(StakeApiException::class);

        $this->normalizer->normalize([
            'bet' => ['__typename' => 'UnknownBetType'],
        ]);
    }

    // Seed not revealed

    #[Test]
    public function it_throws_when_casino_server_seed_is_not_revealed(): void
    {
        $this->expectException(SeedNotRevealedException::class);

        $this->normalizer->normalize([
            'bet' => [
                '__typename' => 'CasinoBet',
                'serverSeed' => null,
            ],
        ]);
    }

    #[Test]
    public function it_throws_when_crash_server_seed_is_not_revealed(): void
    {
        $this->expectException(SeedNotRevealedException::class);

        $this->normalizer->normalize([
            'bet' => [
                '__typename' => 'MultiplayerCrashBet',
                'crashGame'  => ['seed' => null],
            ],
        ]);
    }

    #[Test]
    public function it_throws_when_slide_server_seed_is_not_revealed(): void
    {
        $this->expectException(SeedNotRevealedException::class);

        $this->normalizer->normalize([
            'bet' => [
                '__typename' => 'MultiplayerSlideBet',
                'slideGame'  => ['seed' => null],
            ],
        ]);
    }
}
