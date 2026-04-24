<?php

namespace Stake\BetLookup\Console\Commands;

use Illuminate\Console\Command;
use Stake\BetLookup\Services\ClearanceRepository;

class UpdateClearanceCommand extends Command
{
    protected $signature = 'stake:update-clearance
                            {clearance : The cf_clearance cookie value}
                            {user-agent : The User-Agent header value}
                            {expiry : Unix timestamp when the clearance expires}';

    protected $description = 'Update Stake API clearance credentials';

    public function __construct(private readonly ClearanceRepository $clearanceRepository)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $clearance = $this->argument('clearance');
        $userAgent = $this->argument('user-agent');
        $expiry    = (int) $this->argument('expiry');

        if ($expiry <= time()) {
            $this->error('Expiry timestamp must be in the future');
            return self::FAILURE;
        }

        $this->clearanceRepository->updateCredentials($clearance, $userAgent, $expiry);

        $this->info('Clearance credentials updated successfully');
        $this->line('Clearance Cookie: ' . substr($clearance, 0, 20) . '...');
        $this->line('User Agent: ' . substr($userAgent, 0, 50) . '...');
        $this->line('Expires: ' . date('Y-m-d H:i:s', $expiry));
        $this->line('The API will now use the new credentials');

        return self::SUCCESS;
    }
}
