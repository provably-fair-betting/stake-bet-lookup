<?php

namespace Stake\BetLookup\Console\Commands;

use Illuminate\Console\Command;
use Stake\BetLookup\Services\ClearanceRepository;
use Stake\BetLookup\Services\StakeApiService;

class CheckClearanceCommand extends Command
{
    protected $signature = 'stake:check-clearance';

    protected $description = 'Check the status of Stake API clearance credentials';

    public function __construct(
        private readonly ClearanceRepository $clearanceRepository,
        private readonly StakeApiService $stakeApiService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $credentials = $this->clearanceRepository->getCredentials();

        $this->printHeader();
        $this->printStatus();
        $this->printCredentials($credentials);
        $this->printExpiry($credentials);
        $this->printProbe();
        $this->printMaintenanceWarning();
        $this->printRecommendations();

        return self::SUCCESS;
    }

    private function printHeader(): void
    {
        $this->line('');
        $this->line('═══════════════════════════════════════════════════');
        $this->line('  Stake API Clearance Status');
        $this->line('═══════════════════════════════════════════════════');
        $this->line('');
    }

    private function printStatus(): void
    {
        $this->line('Status: ' . $this->clearanceRepository->getExpiryStatus());
        $this->line('');
    }

    private function printCredentials(array $credentials): void
    {
        if (! empty($credentials['clearance_cookie'])) {
            $this->line('Clearance Cookie: ' . substr($credentials['clearance_cookie'], 0, 30) . '...');
        } else {
            $this->warn('Clearance Cookie: Not set');
        }

        if (! empty($credentials['user_agent'])) {
            $this->line('User Agent: ' . substr($credentials['user_agent'], 0, 60) . '...');
        } else {
            $this->warn('User Agent: Not set');
        }

        $this->line('');
    }

    private function printExpiry(array $credentials): void
    {
        if (! $credentials['expiry']) {
            $this->warn('Expiry: Not set');
            return;
        }

        $this->line('Expires: ' . date('Y-m-d H:i:s', $credentials['expiry']));

        $remaining = $this->clearanceRepository->getTimeUntilExpiry();

        if ($remaining !== null) {
            $hours   = (int) floor($remaining / 3600);
            $minutes = (int) floor(($remaining % 3600) / 60);
            $this->line("Time Remaining: {$hours}h {$minutes}m");
        }

        $this->line('');
    }

    private function printProbe(): void
    {
        $this->line('Probing stake.games...');
        $status = $this->stakeApiService->probe();

        match (true) {
            $status === 0   => $this->warn('Probe: Connection failed (network error or credentials not set)'),
            $status === 200 => $this->info('Probe: Active (HTTP 200)'),
            $status === 403 => $this->error('Probe: Clearance rejected (HTTP 403) — credentials need renewal'),
            default         => $this->warn("Probe: Unexpected response (HTTP {$status})"),
        };

        $this->line('');
    }

    private function printMaintenanceWarning(): void
    {
        if (! $this->clearanceRepository->isInMaintenanceMode()) {
            return;
        }

        $this->error('MAINTENANCE MODE ACTIVE');
        $this->line('API requests are currently blocked');
        $this->line('');
    }

    private function printRecommendations(): void
    {
        $this->line('═══════════════════════════════════════════════════');
        $this->line('');

        if (! $this->clearanceRepository->isValid()) {
            $this->error('Action Required: Clearance has expired');
            $this->line('Run: make capture');
        } elseif ($this->clearanceRepository->isExpiringSoon()) {
            $this->warn('Recommendation: Clearance expiring soon');
            $this->line('Consider renewing credentials to avoid downtime');
        }
    }
}
