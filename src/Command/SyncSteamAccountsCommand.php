<?php

namespace SteamConnect\Command;

use Concrete\Core\Foundation\Command\Command;

/**
 * Sync the Steam data of a chunk of linked accounts, see SteamConnect\Sync\SteamDataSync::syncAccounts().
 */
class SyncSteamAccountsCommand extends Command
{
    /** @var array<string, int> */
    protected $accounts;

    /**
     * @param array<string, int> $accounts user IDs by SteamID
     */
    public function __construct(array $accounts)
    {
        $this->accounts = $accounts;
    }

    /**
     * @return array<string, int>
     */
    public function getAccounts(): array
    {
        return $this->accounts;
    }
}
