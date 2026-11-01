<?php

namespace SteamConnect\Activity;

use CommunityEventManager\Activity\ActivitySource;
use Concrete\Core\Cache\Level\ExpensiveCache;
use Concrete\Core\Logging\LoggerFactory;
use Doctrine\DBAL\Connection;
use SteamConnect\Api\SteamWebApi;
use SteamConnect\Entity\SteamApp;
use SteamConnect\SteamConfig;

/**
 * Activity source of the block "Event Activity" (community_event_manager): the game the linked users are in right
 * now, from GetPlayerSummaries (needs public game details on the profile). The answer is cached for a minute, one
 * request per 100 users.
 */
class SteamActivity implements ActivitySource
{
    protected const CACHE_SECONDS = 60;

    /** Steam app IDs are small; bigger "game IDs" are non-Steam shortcuts and mods */
    protected const MAX_APP_ID = 100000000;

    /** @var SteamWebApi */
    protected $api;

    /** @var Connection */
    protected $db;

    /** @var ExpensiveCache */
    protected $cache;

    /** @var SteamConfig */
    protected $config;

    /** @var LoggerFactory */
    protected $loggers;

    public function __construct(SteamWebApi $api, Connection $db, ExpensiveCache $cache, SteamConfig $config, LoggerFactory $loggers)
    {
        $this->api = $api;
        $this->db = $db;
        $this->cache = $cache;
        $this->config = $config;
        $this->loggers = $loggers;
    }

    public function getHandle(): string
    {
        return 'steam';
    }

    public function getName(): string
    {
        return t('Steam');
    }

    public function getPlaying(array $uIDs): array
    {
        $uIDs = array_values(array_filter(array_map('intval', $uIDs)));
        if ($uIDs === []) {
            return [];
        }
        $rows = $this->db->fetchAllAssociative('SELECT uID, steamId, avatarUrl FROM SteamConnectProfiles WHERE uID IN (?)', [$uIDs], [Connection::PARAM_INT_ARRAY]);
        if ($this->config->get('activity_demo', false)) {
            return $this->demo($rows);
        }
        if ($rows === [] || !$this->api->hasApiKey()) {
            return [];
        }
        $summaries = $this->summaries(array_column($rows, 'steamId'));
        $out = [];
        foreach ($rows as $row) {
            $summary = $summaries[$row['steamId']] ?? null;
            $game = trim((string) ($summary['gameextrainfo'] ?? ''));
            if ($game === '') {
                continue;
            }
            $appId = (int) ($summary['gameid'] ?? 0);
            $appId = $appId > 0 && $appId < self::MAX_APP_ID ? $appId : null;
            $out[(int) $row['uID']] = [
                'game' => $game,
                'appId' => $appId,
                'image' => $appId ? SteamApp::buildHeaderImageUrl($appId) : null,
                'avatar' => $summary['avatarfull'] ?? $row['avatarUrl'] ?: null,
            ];
        }

        return $out;
    }

    public function getExtras(array $uIDs): ?array
    {
        return null;
    }

    /**
     * @param string[] $steamIds
     *
     * @return array<string, array> player summaries by SteamID
     */
    protected function summaries(array $steamIds): array
    {
        sort($steamIds);
        $item = $this->cache->getItem('steam_connect/activity/' . md5(implode(',', $steamIds)));
        if (!$item->isMiss()) {
            return $item->get();
        }
        $summaries = [];
        try {
            foreach (array_chunk($steamIds, SteamWebApi::MAX_IDS_PER_REQUEST) as $chunk) {
                $summaries += $this->api->getPlayerSummaries($chunk);
            }
        } catch (\Throwable $e) {
            // keep the empty answer for a minute too: no hammering of a failing API from every screen
            $this->loggers->createLogger('steam_connect')->warning('Event activity: GetPlayerSummaries failed: ' . $e->getMessage());
        }
        $this->cache->save($item->set($summaries)->expiresAfter(self::CACHE_SECONDS));

        return $summaries;
    }

    /**
     * Development: about two thirds play one of the known games, changing every few minutes.
     */
    protected function demo(array $rows): array
    {
        $apps = $this->db->fetchAllAssociative('SELECT a.appId, a.name FROM SteamConnectApps a JOIN SteamConnectOwnedGames g ON g.appId = a.appId GROUP BY a.appId, a.name ORDER BY SUM(g.playtimeForever) DESC LIMIT 12');
        if ($apps === []) {
            return [];
        }
        $slot = (int) floor(time() / 300);
        $out = [];
        foreach ($rows as $row) {
            $hash = crc32($row['uID'] . '|' . $slot);
            if ($hash % 3 === 0) {
                continue;
            }
            // a few popular games: skew towards the first ones
            $app = $apps[(int) floor((($hash >> 4) % 1000) / 1000 * ($hash % 2 ? 4 : count($apps)))];
            $out[(int) $row['uID']] = ['game' => $app['name'], 'appId' => (int) $app['appId'], 'image' => SteamApp::buildHeaderImageUrl((int) $app['appId']), 'avatar' => $row['avatarUrl'] ?: null];
        }

        return $out;
    }
}
