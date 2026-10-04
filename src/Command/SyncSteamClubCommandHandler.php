<?php

namespace SteamConnect\Command;

use Concrete\Core\Command\Task\Output\OutputAwareInterface;
use Concrete\Core\Command\Task\Output\OutputAwareTrait;
use SteamConnect\Sync\SteamClubSync;
use SteamConnect\Sync\SteamDataSync;

class SyncSteamClubCommandHandler implements OutputAwareInterface
{
    use OutputAwareTrait;

    /** @var SteamClubSync */
    protected $clubSync;

    /** @var SteamDataSync */
    protected $dataSync;

    public function __construct(SteamClubSync $clubSync, SteamDataSync $dataSync)
    {
        $this->clubSync = $clubSync;
        $this->dataSync = $dataSync;
    }

    public function __invoke(SyncSteamClubCommand $command)
    {
        $this->clubSync->syncMembership($this->dataSync->getLinkedAccounts(), function (string $message) {
            $this->output->write($message);
        });
    }
}
