<?php

namespace SteamConnect\Command;

use Concrete\Core\Command\Task\Output\OutputAwareInterface;
use Concrete\Core\Command\Task\Output\OutputAwareTrait;
use SteamConnect\Sync\SteamDataSync;

class SyncSteamAccountsCommandHandler implements OutputAwareInterface
{
    use OutputAwareTrait;

    /** @var SteamDataSync */
    protected $sync;

    public function __construct(SteamDataSync $sync)
    {
        $this->sync = $sync;
    }

    public function __invoke(SyncSteamAccountsCommand $command)
    {
        $this->sync->syncAccounts($command->getAccounts(), function (string $message) {
            $this->output->write($message);
        });
    }
}
