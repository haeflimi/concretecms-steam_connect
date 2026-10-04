<?php

namespace SteamConnect\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * Cached Steam profile of a user who linked their Steam account.
 * Written by the "Sync Steam Data" task (SteamConnect\Sync\SteamDataSync), read-only everywhere else.
 *
 * @ORM\Entity()
 * @ORM\Table(name="SteamConnectProfiles", indexes={@ORM\Index(name="uID", columns={"uID"})})
 */
class SteamProfile
{
    /**
     * SteamID64.
     *
     * @ORM\Id
     * @ORM\Column(type="string", length=20)
     */
    protected $steamId;

    /**
     * @ORM\Column(type="integer", options={"unsigned": true})
     */
    protected $uID;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $personaName;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $profileUrl;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $avatarUrl;

    /**
     * 1 = private / friends only, 3 = public.
     *
     * @ORM\Column(type="smallint", nullable=true)
     */
    protected $communityVisibilityState;

    /**
     * Whether the owned games could be read on the last sync (false = game details are private).
     *
     * @ORM\Column(type="boolean", nullable=true)
     */
    protected $gameDetailsVisible;

    /**
     * @ORM\Column(type="string", length=2, nullable=true)
     */
    protected $countryCode;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $steamCreatedAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $lastLogoffAt;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $steamLevel;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $gameCount;

    /**
     * Sum of the playtime of all owned games, in minutes.
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $totalPlaytime;

    /**
     * Sum of the playtime of the last two weeks, in minutes.
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $recentPlaytime;

    /**
     * @ORM\Column(type="boolean", options={"default": false})
     */
    protected $vacBanned = false;

    /**
     * @ORM\Column(type="integer", options={"default": 0})
     */
    protected $numberOfVacBans = 0;

    /**
     * @ORM\Column(type="integer", options={"default": 0})
     */
    protected $numberOfGameBans = 0;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $daysSinceLastBan;

    /**
     * @ORM\Column(type="boolean", options={"default": false})
     */
    protected $communityBanned = false;

    /**
     * "none", "probation" or "banned".
     *
     * @ORM\Column(type="string", length=32, nullable=true)
     */
    protected $economyBan;

    /**
     * Whether the user is a member of our Steam group, null if not checked (no group configured).
     *
     * @ORM\Column(type="boolean", nullable=true)
     */
    protected $clubMember;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $clubCheckedAt;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $syncedAt;

    /**
     * Error of the last sync, null if it succeeded.
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $syncError;

    public function getSteamId(): string
    {
        return $this->steamId;
    }

    public function getUserID(): int
    {
        return (int) $this->uID;
    }

    public function getPersonaName(): ?string
    {
        return $this->personaName;
    }

    public function getProfileUrl(): ?string
    {
        return $this->profileUrl;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatarUrl;
    }

    public function isProfilePublic(): bool
    {
        return (int) $this->communityVisibilityState === 3;
    }

    public function isGameDetailsVisible(): ?bool
    {
        return $this->gameDetailsVisible;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function getSteamCreatedAt(): ?DateTime
    {
        return $this->steamCreatedAt;
    }

    public function getLastLogoffAt(): ?DateTime
    {
        return $this->lastLogoffAt;
    }

    public function getSteamLevel(): ?int
    {
        return $this->steamLevel;
    }

    public function getGameCount(): ?int
    {
        return $this->gameCount;
    }

    public function getTotalPlaytime(): ?int
    {
        return $this->totalPlaytime;
    }

    public function getRecentPlaytime(): ?int
    {
        return $this->recentPlaytime;
    }

    public function isVacBanned(): bool
    {
        return (bool) $this->vacBanned;
    }

    public function getNumberOfVacBans(): int
    {
        return (int) $this->numberOfVacBans;
    }

    public function getNumberOfGameBans(): int
    {
        return (int) $this->numberOfGameBans;
    }

    public function getDaysSinceLastBan(): ?int
    {
        return $this->daysSinceLastBan;
    }

    public function isCommunityBanned(): bool
    {
        return (bool) $this->communityBanned;
    }

    public function getEconomyBan(): ?string
    {
        return $this->economyBan;
    }

    public function getSyncedAt(): ?DateTime
    {
        return $this->syncedAt;
    }

    public function getSyncError(): ?string
    {
        return $this->syncError;
    }

    public function isClubMember(): ?bool
    {
        return $this->clubMember === null ? null : (bool) $this->clubMember;
    }

    public function getClubCheckedAt(): ?DateTime
    {
        return $this->clubCheckedAt;
    }
}
