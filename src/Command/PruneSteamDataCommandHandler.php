<?php

namespace SteamConnect\Command;

use Concrete\Core\Command\Task\Output\OutputAwareInterface;
use Concrete\Core\Command\Task\Output\OutputAwareTrait;
use SteamConnect\Sync\SteamDataSync;

class PruneSteamDataCommandHandler implements OutputAwareInterface
{
    use OutputAwareTrait;

    /** @var SteamDataSync */
    protected $sync;

    public function __construct(SteamDataSync $sync)
    {
        $this->sync = $sync;
    }

    public function __invoke(PruneSteamDataCommand $command)
    {
        $this->output->write(t('Removed the data of %s unlinked Steam accounts.', $this->sync->pruneUnlinkedAccounts()));
    }
}
