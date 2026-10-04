<?php

namespace SteamConnect\Command\Task;

use Concrete\Core\Command\Batch\Batch;
use Concrete\Core\Command\Task\Controller\AbstractController;
use Concrete\Core\Command\Task\Input\InputInterface;
use Concrete\Core\Command\Task\Runner\BatchProcessTaskRunner;
use Concrete\Core\Command\Task\Runner\TaskRunnerInterface;
use Concrete\Core\Command\Task\TaskInterface;
use Concrete\Core\Error\UserMessageException;
use SteamConnect\Api\SteamWebApi;
use SteamConnect\Command\PruneSteamDataCommand;
use SteamConnect\Command\SyncSteamAccountsCommand;
use SteamConnect\Command\SyncSteamAchievementsCommand;
use SteamConnect\Command\SyncSteamClubCommand;
use SteamConnect\Sync\SteamClubSync;
use SteamConnect\Sync\SteamDataSync;

class SyncSteamDataController extends AbstractController
{
    /** Accounts per batch step: one GetPlayerSummaries/GetPlayerBans request plus two requests per account */
    protected const CHUNK_SIZE = 20;

    /** @var SteamDataSync */
    protected $sync;

    /** @var SteamWebApi */
    protected $api;

    /** @var SteamClubSync */
    protected $clubSync;

    public function __construct(SteamDataSync $sync, SteamWebApi $api, SteamClubSync $clubSync)
    {
        $this->sync = $sync;
        $this->api = $api;
        $this->clubSync = $clubSync;
    }

    public function getName(): string
    {
        return t('Sync Steam Data');
    }

    public function getDescription(): string
    {
        return t('Stores the Steam profile, bans, owned games, playtime, achievements and Steam group membership of all users who linked their Steam account.');
    }

    public function getConsoleCommandName(): string
    {
        return 'sync-steam-data';
    }

    public function getTaskRunner(TaskInterface $task, InputInterface $input): TaskRunnerInterface
    {
        if (!$this->api->hasApiKey()) {
            throw new UserMessageException(t('Please configure a Steam Web API key in the Steam authentication type first.'));
        }

        $linkedAccounts = $this->sync->getLinkedAccounts();
        $batch = Batch::create(t('Sync Steam Data'));
        $batch->add(new PruneSteamDataCommand());
        if ($this->clubSync->getClubUrl() !== null) {
            $batch->add(new SyncSteamClubCommand());
        }
        foreach (array_chunk($linkedAccounts, self::CHUNK_SIZE, true) as $accounts) {
            $batch->add(new SyncSteamAccountsCommand($accounts));
        }
        // After the libraries, which tell which games have been played since the last run
        foreach (array_keys($linkedAccounts) as $steamId) {
            $batch->add(new SyncSteamAchievementsCommand((string) $steamId));
        }

        return new BatchProcessTaskRunner($task, $batch, $input, t('Syncing Steam data...'));
    }
}
