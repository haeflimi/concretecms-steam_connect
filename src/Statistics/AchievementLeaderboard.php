<?php

namespace SteamConnect\Statistics;

use Concrete\Core\Database\Connection\Connection;
use DateTimeInterface;

/**
 * Achievement rankings of the linked users, based on the data of the "Sync Steam Data" task.
 *
 * Besides the number of achievements, every achievement is worth points depending on how rare it is
 * (see POINTS_SQL), so 100 easy "finish the tutorial" achievements don't beat a few hard ones.
 */
class AchievementLeaderboard
{
    /**
     * 1 point per achievement plus up to 10 points for rarity: 1% unlock rate = +9.9, 90% = +1.
     * Achievements without a global rate (not loaded yet) count as 50%.
     */
    protected const POINTS_SQL = 'ROUND(1 + (100 - COALESCE(a.globalPercent, 50)) / 10)';

    /** @var Connection */
    protected $db;

    public function __construct(Connection $db)
    {
        $this->db = $db;
    }

    /**
     * @param int|null $appId only count the achievements of this game
     * @param DateTimeInterface|null $since only count achievements unlocked since then (e.g. during a LAN)
     *
     * @return array[] rows with rank, uID, steamId, personaName, avatarUrl, achievements, points, rarest (lowest globalPercent)
     */
    public function getRanking(?int $appId = null, ?DateTimeInterface $since = null, int $limit = 20): array
    {
        $where = [];
        $params = [];
        if ($appId !== null) {
            $where[] = 'pa.appId = ?';
            $params[] = $appId;
        }
        if ($since !== null) {
            $where[] = 'pa.unlockedAt >= ?';
            $params[] = $since->format('Y-m-d H:i:s');
        }

        $rows = $this->db->fetchAllAssociative(
            'SELECT p.uID, p.steamId, p.personaName, p.avatarUrl,
                COUNT(*) AS achievements, SUM(' . self::POINTS_SQL . ') AS points, MIN(a.globalPercent) AS rarest
            FROM SteamConnectPlayerAchievements pa
            INNER JOIN SteamConnectProfiles p ON p.steamId = pa.steamId
            LEFT JOIN SteamConnectAchievements a ON a.appId = pa.appId AND a.apiName = pa.apiName
            ' . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . '
            GROUP BY p.steamId, p.uID, p.personaName, p.avatarUrl
            ORDER BY points DESC, achievements DESC, rarest ASC
            LIMIT ' . max(1, $limit),
            $params
        );

        return $this->addRanks($rows);
    }

    /**
     * Users who unlocked all achievements of the most games.
     *
     * @return array[] rows with rank, uID, steamId, personaName, avatarUrl, perfectGames
     */
    public function getPerfectGamesRanking(int $limit = 20): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT p.uID, p.steamId, p.personaName, p.avatarUrl, COUNT(*) AS perfectGames
            FROM SteamConnectOwnedGames g
            INNER JOIN SteamConnectApps a ON a.appId = g.appId
            INNER JOIN SteamConnectProfiles p ON p.steamId = g.steamId
            WHERE a.achievementCount > 0 AND g.achievementsUnlocked >= a.achievementCount
            GROUP BY p.steamId, p.uID, p.personaName, p.avatarUrl
            ORDER BY perfectGames DESC
            LIMIT ' . max(1, $limit)
        );

        return $this->addRanks($rows, 'perfectGames');
    }

    /**
     * Same score = same rank.
     */
    protected function addRanks(array $rows, string $scoreColumn = 'points'): array
    {
        $rank = 0;
        $previous = null;
        foreach ($rows as $index => &$row) {
            if ($row[$scoreColumn] !== $previous) {
                $rank = $index + 1;
                $previous = $row[$scoreColumn];
            }
            $row = ['rank' => $rank] + $row;
        }

        return $rows;
    }
}
