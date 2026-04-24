<?php

namespace Stake\BetLookup\Models;

use Illuminate\Database\Eloquent\Model;

class StakeClearance extends Model
{
    protected $table = 'stake_clearance';

    protected $fillable = [
        'clearance_cookie',
        'user_agent',
        'expires_at',
        'updated_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Get the latest (most recent) clearance credentials.
     */
    public static function getLatest(): ?self
    {
        return static::latest()->first();
    }

    /**
     * Check if clearance is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if clearance is expiring soon (within given seconds).
     */
    public function isExpiringSoon(int $thresholdSeconds = 3600): bool
    {
        $secondsUntilExpiry = $this->expires_at->diffInSeconds(now(), false);
        return $secondsUntilExpiry > 0 && $secondsUntilExpiry <= $thresholdSeconds;
    }

    /**
     * Get time remaining until expiry in seconds.
     */
    public function getTimeUntilExpiry(): int
    {
        return max(0, now()->diffInSeconds($this->expires_at, false));
    }
}
