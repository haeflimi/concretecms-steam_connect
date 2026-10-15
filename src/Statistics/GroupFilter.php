<?php

namespace SteamConnect\Statistics;

/**
 * Limits the statistics to the Steam accounts of the members of one user group (direct members) and/or of a list of
 * users (e.g. the participants of an event). Both limits combine.
 */
trait GroupFilter
{
    /** @var int 0 = all linked users */
    protected $groupID = 0;

    /** @var int[] empty = all linked users */
    protected $userIDs = [];

    /**
     * @return static a copy that only counts the members of the group (0 or null = everybody)
     */
    public function forGroup(?int $groupID)
    {
        $copy = clone $this;
        $copy->groupID = max(0, (int) $groupID);

        return $copy;
    }

    /**
     * @param int[] $userIDs
     *
     * @return static a copy that only counts these users (empty = everybody); an unknown user simply counts nothing
     */
    public function forUsers(array $userIDs)
    {
        $copy = clone $this;
        $copy->userIDs = array_values(array_unique(array_filter(array_map('intval', $userIDs), static fn (int $id) => $id > 0)));

        return $copy;
    }

    /**
     * SQL condition on a Steam ID column, "1 = 1" without a group or user list.
     */
    protected function groupCondition(string $steamIdColumn): string
    {
        $conditions = [];
        if ($this->groupID !== 0) {
            $conditions[] = $steamIdColumn . ' IN (SELECT gp.steamId FROM SteamConnectProfiles gp INNER JOIN UserGroups gug ON gug.uID = gp.uID WHERE gug.gID = ' . $this->groupID . ')';
        }
        if ($this->userIDs !== []) {
            $conditions[] = $steamIdColumn . ' IN (SELECT up.steamId FROM SteamConnectProfiles up WHERE up.uID IN (' . implode(',', $this->userIDs) . '))';
        }

        return $conditions ? '(' . implode(' AND ', $conditions) . ')' : '1 = 1';
    }
}
