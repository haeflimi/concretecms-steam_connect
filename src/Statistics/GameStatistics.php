<?php

namespace SteamConnect\Statistics;

use Concrete\Core\Database\Connection\Connection;
use DateTimeInterface;

/**
 * Game rankings of the linked users, based on the data of the "Sync Steam Data" task.
 * Only users whose game details are public are counted. Playtimes are in minutes.
 */
class GameStatistics
{
    /** @var Connection */
    protected $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * Number of users whose library we know (public game details).
     */
    public function getLibraryCount(): int
    {
        return (int) $this->db->fetchOne('SELECT COUNT(*) FROM SteamConnectProfiles WHERE gameDetailsVisible = 1');
    }

    /**
     * @return array[] rows with appId, name, iconHash, owners, players (owners who ever played it), minutes (total playtime)
     */
    public function getMostOwned(int $limit = 10): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT a.appId, a.name, a.iconHash, COUNT(*) AS owners, SUM(g.playtimeForever > 0) AS players, SUM(g.playtimeForever) AS minutes
            FROM SteamConnectOwnedGames g INNER JOIN SteamConnectApps a ON a.appId = g.appId
            GROUP BY a.appId, a.name, a.iconHash
            ORDER BY owners DESC, minutes DESC
            LIMIT ' . max(1, $limit)
        );
    }

    /**
     * @param string|DateTimeInterface $period "all" (total playtime), "two_weeks" (Steam's own last-2-weeks value)
     *                                        or a start date (calculated from the playtime history)
     *
     * @return array[] rows with appId, name, iconHash, minutes, players (users who played it in the period)
     */
    public function getMostPlayed($period, int $limit = 10): array
    {
        if ($period instanceof DateTimeInterface) {
            return $this->getMostPlayedSince($period, $limit);
        }
        $column = $period === 'two_weeks' ? 'g.playtime2Weeks' : 'g.playtimeForever';

        return $this->db->fetchAllAssociative(
            "SELECT a.appId, a.name, a.iconHash, SUM($column) AS minutes, SUM($column > 0) AS players
            FROM SteamConnectOwnedGames g INNER JOIN SteamConnectApps a ON a.appId = g.appId
            WHERE $column > 0
            GROUP BY a.appId, a.name, a.iconHash
            ORDER BY minutes DESC
            LIMIT " . max(1, $limit)
        );
    }

    /**
     * Playtime since a date: the current total minus the last total recorded before the date. If the game was
     * synced for the first time after the date, the first recorded total is used, so the time played before
     * the first sync is never counted.
     */
    protected function getMostPlayedSince(DateTimeInterface $since, int $limit): array
    {
        $sinceString = $since->format('Y-m-d H:i:s');

        return $this->db->fetchAllAssociative(
            'SELECT a.appId, a.name, a.iconHash, SUM(p.minutes) AS minutes, COUNT(*) AS players
            FROM (
                SELECT g.appId, g.playtimeForever - COALESCE(
                    (SELECT h.playtimeForever FROM SteamConnectPlaytimeHistory h
                        WHERE h.steamId = g.steamId AND h.appId = g.appId AND h.recordedAt <= :since
                        ORDER BY h.recordedAt DESC LIMIT 1),
                    (SELECT MIN(h.playtimeForever) FROM SteamConnectPlaytimeHistory h
                        WHERE h.steamId = g.steamId AND h.appId = g.appId AND h.recordedAt > :since)
                ) AS minutes
                FROM SteamConnectOwnedGames g
                WHERE g.updatedAt > :since
            ) p
            INNER JOIN SteamConnectApps a ON a.appId = p.appId
            WHERE p.minutes > 0
            GROUP BY a.appId, a.name, a.iconHash
            ORDER BY minutes DESC
            LIMIT ' . max(1, $limit),
            ['since' => $sinceString]
        );
    }

    /**
     * Who played these games, most playtime first: the faces next to the most played / most owned games. Same periods
     * as getMostPlayed(); "owned" lists everybody who ever played a game they own.
     *
     * @param int[] $appIds
     * @param string|DateTimeInterface $period "all", "two_weeks" or a start date
     *
     * @return array<int, array[]> per app ID: rows with uID, userName, steamName, steamAvatar, minutes
     */
    public function getPlayers(array $appIds, $period = 'all'): array
    {
        $appIds = array_values(array_unique(array_map('intval', $appIds)));
        if (!$appIds) {
            return [];
        }
        $params = ['apps' => $appIds];
        $types = ['apps' => Connection::PARAM_INT_ARRAY];
        if ($period instanceof DateTimeInterface) {
            $minutes = 'g.playtimeForever - COALESCE(
                (SELECT h.playtimeForever FROM SteamConnectPlaytimeHistory h
                    WHERE h.steamId = g.steamId AND h.appId = g.appId AND h.recordedAt <= :since ORDER BY h.recordedAt DESC LIMIT 1),
                (SELECT MIN(h.playtimeForever) FROM SteamConnectPlaytimeHistory h
                    WHERE h.steamId = g.steamId AND h.appId = g.appId AND h.recordedAt > :since))';
            $where = 'g.updatedAt > :since';
            $params['since'] = $period->format('Y-m-d H:i:s');
        } else {
            $minutes = $period === 'two_weeks' ? 'g.playtime2Weeks' : 'g.playtimeForever';
            $where = '1 = 1';
        }
        $players = [];
        foreach ($this->db->fetchAllAssociative(
            "SELECT * FROM (
                SELECT g.appId, p.uID, u.uName AS userName, p.personaName AS steamName, p.avatarUrl AS steamAvatar, $minutes AS minutes
                FROM SteamConnectOwnedGames g
                INNER JOIN SteamConnectProfiles p ON p.steamId = g.steamId
                LEFT JOIN Users u ON u.uID = p.uID
                WHERE g.appId IN (:apps) AND $where
            ) x WHERE x.minutes > 0 ORDER BY x.minutes DESC",
            $params,
            $types
        ) as $row) {
            $players[(int) $row['appId']][] = $row;
        }

        return $players;
    }

    /**
     * Games that have achievements somebody unlocked, for the game filter of the achievement leaderboard.
     *
     * @return array<int, string> names by app ID
     */
    public function getGamesWithAchievements(): array
    {
        $games = [];
        foreach ($this->db->fetchAllAssociative(
            'SELECT a.appId, a.name FROM SteamConnectApps a
            WHERE EXISTS (SELECT 1 FROM SteamConnectPlayerAchievements pa WHERE pa.appId = a.appId)
            ORDER BY a.name'
        ) as $row) {
            $games[(int) $row['appId']] = $row['name'];
        }

        return $games;
    }
}
