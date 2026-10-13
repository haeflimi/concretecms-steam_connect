<?php

namespace SteamConnect\Statistics;

/**
 * Limits the statistics to the Steam accounts of the members of one user group (direct members).
 */
trait GroupFilter
{
    /** @var int 0 = all linked users */
    protected $groupID = 0;

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
     * SQL condition on a Steam ID column, "1 = 1" without a group.
     */
    protected function groupCondition(string $steamIdColumn): string
    {
        if ($this->groupID === 0) {
            return '1 = 1';
        }

        return $steamIdColumn . ' IN (SELECT gp.steamId FROM SteamConnectProfiles gp INNER JOIN UserGroups gug ON gug.uID = gp.uID WHERE gug.gID = ' . $this->groupID . ')';
    }
}
