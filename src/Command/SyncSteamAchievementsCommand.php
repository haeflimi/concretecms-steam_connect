<?php

namespace SteamConnect\Command;

use Concrete\Core\Foundation\Command\Command;

/**
 * Sync the achievements of one linked account, see SteamConnect\Sync\SteamAchievementSync.
 */
class SyncSteamAchievementsCommand extends Command
{
    /** @var string */
    protected $steamId;

    public function __construct(string $steamId)
    {
        $this->steamId = $steamId;
    }

    public function getSteamId(): string
    {
        return $this->steamId;
    }
}
