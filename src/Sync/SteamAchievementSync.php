<?php

namespace SteamConnect\Sync;

use Concrete\Core\Database\Connection\Connection;
use DateTime;
use SteamConnect\Api\SteamApiException;
use SteamConnect\Api\SteamWebApi;

/**
 * Copies the achievements of the linked users into SteamConnectAchievements / SteamConnectPlayerAchievements.
 *
 * Achievements can only change while a game is played, so a game is only checked again when its playtime changed
 * since the last check (SteamConnectOwnedGames.achievementsPlaytime). That keeps the number of requests low after
 * the first run, which is spread over several runs by MAX_GAMES_PER_RUN.
 */
class SteamAchievementSync
{
    protected const DATE_FORMAT = 'Y-m-d H:i:s';

    /** Max. number of games per user and run, the rest is done in the next runs */
    public const MAX_GAMES_PER_RUN = 50;

    /** Reload the achievement list and global unlock rates of a game after this many days */
    protected const SCHEMA_MAX_AGE_DAYS = 7;

    /** @var Connection */
    protected $db;

    /** @var SteamWebApi */
    protected $api;

    public function __construct(Connection $db, SteamWebApi $api)
    {
        $this->db = $db;
        $this->api = $api;
    }

    /**
     * @param callable|null $log receives a message per updated game
     *
     * @throws SteamApiException if the API can't be used at all (invalid key, rate limit)
     *
     * @return int number of checked games
     */
    public function syncAccount(string $steamId, ?callable $log = null): int
    {
        $log = $log ?: static function () {};
        $games = $this->db->fetchAllAssociative(
            'SELECT g.appId, g.playtimeForever, a.name, a.achievementCount, a.achievementsUpdatedAt, a.achievementsLanguage
            FROM SteamConnectOwnedGames g INNER JOIN SteamConnectApps a ON a.appId = g.appId
            WHERE g.steamId = ? AND a.hasCommunityVisibleStats = 1 AND g.playtimeForever > 0
                AND (g.achievementsPlaytime IS NULL OR g.achievementsPlaytime <> g.playtimeForever)
            ORDER BY g.playtime2Weeks DESC, g.lastPlayedAt DESC
            LIMIT ' . self::MAX_GAMES_PER_RUN,
            [$steamId]
        );

        foreach ($games as $game) {
            $appId = (int) $game['appId'];
            try {
                $achievementCount = $this->ensureSchema($appId, $game['achievementCount'], $game['achievementsUpdatedAt'], $game['achievementsLanguage']);
                $unlocked = $achievementCount === 0 ? [] : $this->api->getPlayerAchievements($steamId, $appId);
            } catch (SteamApiException $e) {
                if ($e->isFatal()) {
                    throw $e;
                }
                $log(t('%s: %s', $game['name'], $e->getMessage()));
                continue;
            }
            $count = $this->savePlayerAchievements($steamId, $appId, $unlocked ?? [], (int) $game['playtimeForever']);
            if ($achievementCount > 0) {
                $log(t('%s: %s of %s achievements', $game['name'], $count, $achievementCount));
            }
        }

        return count($games);
    }

    /**
     * Load the achievements of a game if we don't have them, they're outdated or in another language.
     *
     * @return int number of achievements of the game
     */
    protected function ensureSchema(int $appId, $achievementCount, $updatedAt, $language): int
    {
        if ($achievementCount !== null && $updatedAt !== null && $language === $this->api->getLanguage()
            && new DateTime($updatedAt) > new DateTime('-' . self::SCHEMA_MAX_AGE_DAYS . ' days')
        ) {
            return (int) $achievementCount;
        }

        $schema = $this->api->getAchievementSchema($appId);
        $percentages = $schema === [] ? [] : $this->api->getGlobalAchievementPercentages($appId);
        $now = (new DateTime())->format(self::DATE_FORMAT);

        $this->db->transactional(function () use ($appId, $schema, $percentages, $now) {
            $existing = array_flip($this->db->fetchFirstColumn('SELECT apiName FROM SteamConnectAchievements WHERE appId = ?', [$appId]));
            foreach ($schema as $achievement) {
                $apiName = (string) $achievement['name'];
                $values = [
                    'displayName' => mb_substr((string) ($achievement['displayName'] ?? $apiName), 0, 255),
                    'description' => ($achievement['description'] ?? '') === '' ? null : $achievement['description'],
                    'iconUrl' => $achievement['icon'] ?? null,
                    'iconGrayUrl' => $achievement['icongray'] ?? null,
                    'hidden' => empty($achievement['hidden']) ? 0 : 1,
                    'globalPercent' => $percentages[$apiName] ?? null,
                    'updatedAt' => $now,
                ];
                if (isset($existing[$apiName])) {
                    $this->db->update('SteamConnectAchievements', $values, ['appId' => $appId, 'apiName' => $apiName]);
                    unset($existing[$apiName]);
                } else {
                    $this->db->insert('SteamConnectAchievements', ['appId' => $appId, 'apiName' => $apiName] + $values);
                }
            }
            // Achievements the developer removed
            foreach (array_keys($existing) as $apiName) {
                $this->db->delete('SteamConnectAchievements', ['appId' => $appId, 'apiName' => $apiName]);
                $this->db->delete('SteamConnectPlayerAchievements', ['appId' => $appId, 'apiName' => $apiName]);
            }
            $this->db->update('SteamConnectApps', [
                'achievementCount' => count($schema),
                'achievementsUpdatedAt' => $now,
                'achievementsLanguage' => $this->api->getLanguage(),
            ], ['appId' => $appId]);
        });

        return count($schema);
    }

    /**
     * @param array[] $achievements the result of SteamWebApi::getPlayerAchievements()
     *
     * @return int number of unlocked achievements
     */
    protected function savePlayerAchievements(string $steamId, int $appId, array $achievements, int $playtime): int
    {
        $unlocked = [];
        foreach ($achievements as $achievement) {
            if (!empty($achievement['achieved']) && isset($achievement['apiname'])) {
                $unlocked[(string) $achievement['apiname']] = empty($achievement['unlocktime']) ? null : (new DateTime('@' . (int) $achievement['unlocktime']))->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format(self::DATE_FORMAT);
            }
        }

        $this->db->transactional(function () use ($steamId, $appId, $unlocked, $playtime) {
            $existing = array_flip($this->db->fetchFirstColumn('SELECT apiName FROM SteamConnectPlayerAchievements WHERE steamId = ? AND appId = ?', [$steamId, $appId]));
            foreach ($unlocked as $apiName => $unlockedAt) {
                if (isset($existing[$apiName])) {
                    unset($existing[$apiName]);
                } else {
                    $this->db->insert('SteamConnectPlayerAchievements', ['steamId' => $steamId, 'appId' => $appId, 'apiName' => $apiName, 'unlockedAt' => $unlockedAt]);
                }
            }
            // Achievements that were reset
            foreach (array_keys($existing) as $apiName) {
                $this->db->delete('SteamConnectPlayerAchievements', ['steamId' => $steamId, 'appId' => $appId, 'apiName' => $apiName]);
            }
            $this->db->update(
                'SteamConnectOwnedGames',
                ['achievementsUnlocked' => count($unlocked), 'achievementsPlaytime' => $playtime],
                ['steamId' => $steamId, 'appId' => $appId]
            );
        });

        return count($unlocked);
    }
}
