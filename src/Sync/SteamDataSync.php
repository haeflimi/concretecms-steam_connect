<?php

namespace SteamConnect\Sync;

use Concrete\Core\Database\Connection\Connection;
use DateTime;
use SteamConnect\Api\SteamApiException;
use SteamConnect\Api\SteamWebApi;

/**
 * Copies the Steam data of all linked users into the SteamConnect* tables (see SteamConnect\Entity).
 * Writes go through DBAL because a sync touches thousands of owned-game rows.
 */
class SteamDataSync
{
    protected const DATE_FORMAT = 'Y-m-d H:i:s';

    /** @var Connection */
    protected $db;

    /** @var SteamWebApi */
    protected $api;

    /** @var array<int, array{name: string, iconHash: ?string, hasStats: bool}>|null known apps, loaded on first use */
    protected $apps;

    public function __construct(Connection $db, SteamWebApi $api)
    {
        $this->db = $db;
        $this->api = $api;
    }

    /**
     * @return array<string, int> user IDs by SteamID of all users who linked their Steam account
     */
    public function getLinkedAccounts(): array
    {
        $accounts = [];
        foreach ($this->db->fetchAllAssociative("SELECT binding, user_id FROM OauthUserMap WHERE namespace = 'steam' ORDER BY user_id") as $row) {
            $accounts[(string) $row['binding']] = (int) $row['user_id'];
        }

        return $accounts;
    }

    /**
     * Remove the data of Steam accounts that are not linked anymore.
     *
     * @return int number of removed profiles
     */
    public function pruneUnlinkedAccounts(): int
    {
        $unlinked = $this->db->fetchFirstColumn(
            "SELECT p.steamId FROM SteamConnectProfiles p LEFT JOIN OauthUserMap m ON m.namespace = 'steam' AND m.binding = p.steamId WHERE m.user_id IS NULL"
        );
        foreach ($unlinked as $steamId) {
            $this->db->transactional(function () use ($steamId) {
                foreach (['SteamConnectPlayerAchievements', 'SteamConnectPlaytimeHistory', 'SteamConnectOwnedGames', 'SteamConnectProfiles'] as $table) {
                    $this->db->delete($table, ['steamId' => $steamId]);
                }
            });
        }

        return count($unlinked);
    }

    /**
     * @param array<string, int> $accounts user IDs by SteamID, at most SteamWebApi::MAX_IDS_PER_REQUEST
     * @param callable|null $log receives a message per account
     *
     * @throws SteamApiException if the API can't be used at all (invalid key, rate limit)
     */
    public function syncAccounts(array $accounts, ?callable $log = null): void
    {
        $log = $log ?: static function () {};
        $steamIds = array_map('strval', array_keys($accounts));

        try {
            $summaries = $this->api->getPlayerSummaries($steamIds);
            $bans = $this->api->getPlayerBans($steamIds);
        } catch (SteamApiException $e) {
            foreach ($accounts as $steamId => $uID) {
                $this->saveError((string) $steamId, $uID, $e->getMessage());
            }
            throw $e;
        }

        foreach ($accounts as $steamId => $uID) {
            $steamId = (string) $steamId;
            try {
                $gameCount = $this->syncAccount($steamId, $uID, $summaries[$steamId] ?? null, $bans[$steamId] ?? null);
                $log($gameCount === null
                    ? t('%s (user %s): game details are private', $steamId, $uID)
                    : t('%s (user %s): %s games', $steamId, $uID, $gameCount));
            } catch (SteamApiException $e) {
                $this->saveError($steamId, $uID, $e->getMessage());
                if ($e->isFatal()) {
                    throw $e;
                }
                $log(t('%s (user %s): %s', $steamId, $uID, $e->getMessage()));
            }
        }
    }

    /**
     * @return int|null number of owned games, null if they are private
     */
    protected function syncAccount(string $steamId, int $uID, ?array $summary, ?array $ban): ?int
    {
        // API calls first, so a failing request doesn't leave half-written data behind
        $games = $this->api->getOwnedGames($steamId);
        $level = $this->api->getSteamLevel($steamId);
        $now = new DateTime();

        $this->db->transactional(function () use ($steamId, $uID, $summary, $ban, $games, $level, $now) {
            $profile = [
                'uID' => $uID,
                'steamLevel' => $level,
                'gameDetailsVisible' => $games === null ? 0 : 1,
                'gameCount' => null,
                'totalPlaytime' => null,
                'recentPlaytime' => null,
                'syncedAt' => $now->format(self::DATE_FORMAT),
                'syncError' => null,
            ];
            if ($summary !== null) {
                $profile += [
                    'personaName' => $summary['personaname'] ?? null,
                    'profileUrl' => $summary['profileurl'] ?? null,
                    'avatarUrl' => $summary['avatarfull'] ?? null,
                    'communityVisibilityState' => $summary['communityvisibilitystate'] ?? null,
                    'countryCode' => $summary['loccountrycode'] ?? null,
                    'steamCreatedAt' => $this->formatTimestamp($summary['timecreated'] ?? null),
                    'lastLogoffAt' => $this->formatTimestamp($summary['lastlogoff'] ?? null),
                ];
            }
            if ($ban !== null) {
                $profile += [
                    'vacBanned' => empty($ban['VACBanned']) ? 0 : 1,
                    'numberOfVacBans' => (int) ($ban['NumberOfVACBans'] ?? 0),
                    'numberOfGameBans' => (int) ($ban['NumberOfGameBans'] ?? 0),
                    'daysSinceLastBan' => isset($ban['DaysSinceLastBan']) ? (int) $ban['DaysSinceLastBan'] : null,
                    'communityBanned' => empty($ban['CommunityBanned']) ? 0 : 1,
                    'economyBan' => $ban['EconomyBan'] ?? null,
                ];
            }

            if ($games === null) {
                // The user made the game details private: don't keep showing their library and achievements.
                $this->db->delete('SteamConnectOwnedGames', ['steamId' => $steamId]);
                $this->db->delete('SteamConnectPlayerAchievements', ['steamId' => $steamId]);
            } else {
                $this->saveApps($games, $now);
                $this->saveOwnedGames($steamId, $games, $now);
                $profile['gameCount'] = count($games);
                $profile['totalPlaytime'] = array_sum(array_column($games, 'playtime_forever'));
                $profile['recentPlaytime'] = array_sum(array_column($games, 'playtime_2weeks'));
            }

            $this->saveProfile($steamId, $profile);
        });

        return $games === null ? null : count($games);
    }

    protected function saveOwnedGames(string $steamId, array $games, DateTime $now): void
    {
        $nowString = $now->format(self::DATE_FORMAT);
        $existing = [];
        foreach ($this->db->fetchAllAssociative('SELECT appId, playtimeForever, playtime2Weeks, lastPlayedAt FROM SteamConnectOwnedGames WHERE steamId = ?', [$steamId]) as $row) {
            $existing[(int) $row['appId']] = $row;
        }

        foreach ($games as $game) {
            $appId = (int) $game['appid'];
            $values = [
                'playtimeForever' => (int) ($game['playtime_forever'] ?? 0),
                'playtime2Weeks' => (int) ($game['playtime_2weeks'] ?? 0),
                'lastPlayedAt' => $this->formatTimestamp($game['rtime_last_played'] ?? null),
            ];
            $old = $existing[$appId] ?? null;
            unset($existing[$appId]);

            if ($old === null) {
                $this->db->insert('SteamConnectOwnedGames', ['steamId' => $steamId, 'appId' => $appId, 'firstSeenAt' => $nowString, 'updatedAt' => $nowString] + $values);
            } elseif ((int) $old['playtimeForever'] !== $values['playtimeForever']
                || (int) $old['playtime2Weeks'] !== $values['playtime2Weeks']
                || $old['lastPlayedAt'] !== $values['lastPlayedAt']
            ) {
                $this->db->update('SteamConnectOwnedGames', ['updatedAt' => $nowString] + $values, ['steamId' => $steamId, 'appId' => $appId]);
            }

            if ($old === null || (int) $old['playtimeForever'] !== $values['playtimeForever']) {
                $this->db->insert('SteamConnectPlaytimeHistory', [
                    'steamId' => $steamId,
                    'appId' => $appId,
                    'recordedAt' => $nowString,
                    'playtimeForever' => $values['playtimeForever'],
                ]);
            }
        }

        // Games that are not in the library anymore (refunds, family sharing ended, ...)
        foreach (array_keys($existing) as $appId) {
            $this->db->delete('SteamConnectOwnedGames', ['steamId' => $steamId, 'appId' => $appId]);
            $this->db->delete('SteamConnectPlayerAchievements', ['steamId' => $steamId, 'appId' => $appId]);
        }
    }

    protected function saveApps(array $games, DateTime $now): void
    {
        if ($this->apps === null) {
            $this->apps = [];
            foreach ($this->db->fetchAllAssociative('SELECT appId, name, iconHash, hasCommunityVisibleStats FROM SteamConnectApps') as $row) {
                $this->apps[(int) $row['appId']] = ['name' => $row['name'], 'iconHash' => $row['iconHash'], 'hasStats' => (bool) $row['hasCommunityVisibleStats']];
            }
        }

        foreach ($games as $game) {
            $appId = (int) $game['appid'];
            $app = [
                'name' => (string) ($game['name'] ?? ('App ' . $appId)),
                'iconHash' => ($game['img_icon_url'] ?? '') === '' ? null : $game['img_icon_url'],
                'hasStats' => !empty($game['has_community_visible_stats']),
            ];
            if (($this->apps[$appId] ?? null) == $app) {
                continue;
            }
            $values = [
                'name' => $app['name'],
                'iconHash' => $app['iconHash'],
                'hasCommunityVisibleStats' => $app['hasStats'] ? 1 : 0,
                'updatedAt' => $now->format(self::DATE_FORMAT),
            ];
            if (isset($this->apps[$appId])) {
                $this->db->update('SteamConnectApps', $values, ['appId' => $appId]);
            } else {
                $this->db->insert('SteamConnectApps', ['appId' => $appId] + $values);
            }
            $this->apps[$appId] = $app;
        }
    }

    public function saveProfile(string $steamId, array $values): void
    {
        if ($this->db->fetchOne('SELECT 1 FROM SteamConnectProfiles WHERE steamId = ?', [$steamId])) {
            $this->db->update('SteamConnectProfiles', $values, ['steamId' => $steamId]);
        } else {
            $this->db->insert('SteamConnectProfiles', ['steamId' => $steamId] + $values);
        }
    }

    protected function saveError(string $steamId, int $uID, string $error): void
    {
        $this->saveProfile($steamId, ['uID' => $uID, 'syncError' => mb_substr($error, 0, 255)]);
    }

    protected function formatTimestamp($timestamp): ?string
    {
        return empty($timestamp) ? null : (new DateTime('@' . (int) $timestamp))->setTimezone(new \DateTimeZone(date_default_timezone_get()))->format(self::DATE_FORMAT);
    }
}
