<?php

namespace SteamConnect\Command;

use Concrete\Core\Command\Task\Output\OutputAwareInterface;
use Concrete\Core\Command\Task\Output\OutputAwareTrait;
use SteamConnect\Sync\SteamAchievementSync;

class SyncSteamAchievementsCommandHandler implements OutputAwareInterface
{
    use OutputAwareTrait;

    /** @var SteamAchievementSync */
    protected $sync;

    public function __construct(SteamAchievementSync $sync)
    {
        $this->sync = $sync;
    }

    public function __invoke(SyncSteamAchievementsCommand $command)
    {
        $steamId = $command->getSteamId();
        $checked = $this->sync->syncAccount($steamId, function (string $message) use ($steamId) {
            $this->output->write($steamId . ' ' . $message);
        });
        if ($checked === 0) {
            $this->output->write(t('%s: achievements are up to date.', $steamId));
        }
    }
}
