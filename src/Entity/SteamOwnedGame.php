<?php

namespace SteamConnect\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * A game in the library of a linked user, as of the last sync. Playtimes are in minutes.
 *
 * @ORM\Entity()
 * @ORM\Table(name="SteamConnectOwnedGames", indexes={@ORM\Index(name="appId", columns={"appId"})})
 */
class SteamOwnedGame
{
    /**
     * @ORM\Id
     * @ORM\Column(type="string", length=20)
     */
    protected $steamId;

    /**
     * @ORM\Id
     * @ORM\Column(type="integer", options={"unsigned": true})
     */
    protected $appId;

    /**
     * @ORM\Column(type="integer", options={"default": 0})
     */
    protected $playtimeForever = 0;

    /**
     * @ORM\Column(type="integer", options={"default": 0})
     */
    protected $playtime2Weeks = 0;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $lastPlayedAt;

    /**
     * Number of unlocked achievements, null until they've been loaded.
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $achievementsUnlocked;

    /**
     * playtimeForever when the achievements were loaded: they only have to be loaded again after playing.
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $achievementsPlaytime;

    /**
     * When the game showed up in the library for the first time (Steam doesn't tell the purchase date).
     *
     * @ORM\Column(type="datetime")
     */
    protected $firstSeenAt;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $updatedAt;

    public function getSteamId(): string
    {
        return $this->steamId;
    }

    public function getAppId(): int
    {
        return (int) $this->appId;
    }

    public function getPlaytimeForever(): int
    {
        return (int) $this->playtimeForever;
    }

    public function getPlaytime2Weeks(): int
    {
        return (int) $this->playtime2Weeks;
    }

    public function getLastPlayedAt(): ?DateTime
    {
        return $this->lastPlayedAt;
    }

    public function getFirstSeenAt(): DateTime
    {
        return $this->firstSeenAt;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    public function getAchievementsUnlocked(): ?int
    {
        return $this->achievementsUnlocked === null ? null : (int) $this->achievementsUnlocked;
    }

    public function getAchievementsPlaytime(): ?int
    {
        return $this->achievementsPlaytime === null ? null : (int) $this->achievementsPlaytime;
    }
}
