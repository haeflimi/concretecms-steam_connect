<?php

namespace SteamConnect\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * An achievement a linked user unlocked. Only unlocked achievements are stored.
 *
 * @ORM\Entity()
 * @ORM\Table(name="SteamConnectPlayerAchievements", indexes={
 *     @ORM\Index(name="app_achievement", columns={"appId", "apiName"}),
 *     @ORM\Index(name="unlockedAt", columns={"unlockedAt"})
 * })
 */
class SteamPlayerAchievement
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
     * @ORM\Id
     * @ORM\Column(type="string", length=128)
     */
    protected $apiName;

    /**
     * Null for very old achievements Steam has no unlock time for.
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $unlockedAt;

    public function getSteamId(): string
    {
        return $this->steamId;
    }

    public function getAppId(): int
    {
        return (int) $this->appId;
    }

    public function getApiName(): string
    {
        return $this->apiName;
    }

    public function getUnlockedAt(): ?DateTime
    {
        return $this->unlockedAt;
    }
}
