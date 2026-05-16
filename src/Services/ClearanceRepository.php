<?php

namespace Stake\BetLookup\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Stake\BetLookup\Models\StakeClearance;

class ClearanceRepository
{
    private const CREDENTIAL_CACHE_TTL = 604800; // 7 days
    private const MAINTENANCE_TTL = 300;          // 5 minutes

    private const KEY_COOKIE       = 'stake_clearance_cookie';
    private const KEY_USER_AGENT   = 'stake_user_agent';
    private const KEY_EXPIRY       = 'stake_clearance_expiry';
    private const KEY_MAINTENANCE  = 'stake_clearance_maintenance_mode';
    private const KEY_ALERT_SENT   = 'stake_clearance_alert_sent';

    public function __construct(private readonly array $config)
    {
    }

    public function getCredentials(): array
    {
        $cached = $this->readFromCache();

        if ($cached) {
            return $cached;
        }

        $fromDb = $this->readFromDatabase();

        if ($fromDb) {
            $this->writeToCache($fromDb['clearance_cookie'], $fromDb['user_agent'], $fromDb['expiry']);
            return $fromDb;
        }

        return ['clearance_cookie' => null, 'user_agent' => null, 'expiry' => null];
    }

    private function readFromCache(): ?array
    {
        $cookie    = Cache::get(self::KEY_COOKIE);
        $userAgent = Cache::get(self::KEY_USER_AGENT);

        if (! $cookie || ! $userAgent) {
            return null;
        }

        return [
            'clearance_cookie' => $cookie,
            'user_agent'       => $userAgent,
            'expiry'           => Cache::get(self::KEY_EXPIRY),
        ];
    }

    private function readFromDatabase(): ?array
    {
        if (! $this->tableExists()) {
            return null;
        }

        $record = StakeClearance::on($this->dbConnection())->latest()->first();

        if (! $record) {
            return null;
        }

        return [
            'clearance_cookie' => $record->clearance_cookie,
            'user_agent'       => $record->user_agent,
            'expiry'           => $record->expires_at->timestamp,
        ];
    }

    private function tableExists(): bool
    {
        try {
            return Schema::connection($this->dbConnection())->hasTable('stake_clearance');
        } catch (\Exception) {
            return false;
        }
    }

    private function dbConnection(): string
    {
        return $this->config['db_connection'] ?? config('database.default', 'mysql');
    }

    public function updateCredentials(
        string $clearanceCookie,
        string $userAgent,
        int $expiry,
        ?string $updatedBy = null
    ): void {
        $this->writeToCache($clearanceCookie, $userAgent, $expiry);
        $this->persistToDatabase($clearanceCookie, $userAgent, $expiry, $updatedBy);
        $this->disableMaintenanceMode();

        Log::info('Stake API: Clearance credentials updated', [
            'expiry'       => date('Y-m-d H:i:s', $expiry),
            'updated_by'   => $updatedBy ?? 'unknown',
            'stored_in_db' => $this->tableExists(),
        ]);
    }

    private function writeToCache(string $cookie, string $userAgent, int $expiry): void
    {
        Cache::put(self::KEY_COOKIE,      $cookie,    self::CREDENTIAL_CACHE_TTL);
        Cache::put(self::KEY_USER_AGENT,  $userAgent, self::CREDENTIAL_CACHE_TTL);
        Cache::put(self::KEY_EXPIRY,      $expiry,    self::CREDENTIAL_CACHE_TTL);
    }

    private function persistToDatabase(
        string $cookie,
        string $userAgent,
        int $expiry,
        ?string $updatedBy
    ): void {
        if (! $this->tableExists()) {
            return;
        }

        $db = $this->dbConnection();

        StakeClearance::on($db)->create([
            'clearance_cookie' => $cookie,
            'user_agent'       => $userAgent,
            'expires_at'       => date('Y-m-d H:i:s', $expiry),
            'updated_by'       => $updatedBy,
        ]);

        StakeClearance::on($db)->where('expires_at', '<', now())->delete();
    }

    public function isValid(): bool
    {
        $credentials = $this->getCredentials();

        if (empty($credentials['clearance_cookie'])) {
            return false;
        }

        $expiry = $credentials['expiry'] ?? null;

        return $expiry === null || time() < $expiry;
    }

    public function isExpiringSoon(): bool
    {
        $expiry = $this->getCredentials()['expiry'] ?? null;

        if ($expiry === null) {
            return false;
        }

        $remaining = $expiry - time();
        $threshold = $this->config['clearance_warning_threshold'] ?? 3600;

        return $remaining > 0 && $remaining <= $threshold;
    }

    public function getTimeUntilExpiry(): ?int
    {
        $expiry = $this->getCredentials()['expiry'] ?? null;

        return $expiry !== null ? max(0, $expiry - time()) : null;
    }

    public function getExpiryStatus(): string
    {
        if (! $this->isValid()) {
            return 'Expired';
        }

        $remaining = $this->getTimeUntilExpiry();

        if ($this->isExpiringSoon()) {
            $minutes = (int) ceil($remaining / 60);
            return "Expiring soon ({$minutes} minutes remaining)";
        }

        $hours   = (int) floor($remaining / 3600);
        $minutes = (int) floor(($remaining % 3600) / 60);

        return "Active ({$hours}h {$minutes}m remaining)";
    }

    public function isInMaintenanceMode(): bool
    {
        return Cache::has(self::KEY_MAINTENANCE);
    }

    public function enableMaintenanceMode(): void
    {
        Cache::put(self::KEY_MAINTENANCE, true, self::MAINTENANCE_TTL);
        Log::warning('Stake API: Maintenance mode enabled');
    }

    public function disableMaintenanceMode(): void
    {
        Cache::forget(self::KEY_MAINTENANCE);
        Cache::forget(self::KEY_ALERT_SENT);
        Log::info('Stake API: Maintenance mode disabled');
    }

    public function wasAlertSent(): bool
    {
        return Cache::has(self::KEY_ALERT_SENT);
    }

    public function markAlertSent(): void
    {
        Cache::put(self::KEY_ALERT_SENT, true, self::MAINTENANCE_TTL);
    }
}
