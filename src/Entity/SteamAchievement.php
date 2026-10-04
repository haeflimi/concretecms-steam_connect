<?php

namespace SteamConnect\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * An achievement of a game (from GetSchemaForGame), with its global unlock rate.
 *
 * @ORM\Entity()
 * @ORM\Table(name="SteamConnectAchievements")
 */
class SteamAchievement
{
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
     * @ORM\Column(type="string", length=255)
     */
    protected $displayName;

    /**
     * Empty for hidden achievements until they're unlocked.
     *
     * @ORM\Column(type="text", nullable=true)
     */
    protected $description;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $iconUrl;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    protected $iconGrayUrl;

    /**
     * @ORM\Column(type="boolean", options={"default": false})
     */
    protected $hidden = false;

    /**
     * Percentage of all Steam players of the game who unlocked it (0-100).
     *
     * @ORM\Column(type="float", nullable=true)
     */
    protected $globalPercent;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $updatedAt;

    public function getAppId(): int
    {
        return (int) $this->appId;
    }

    public function getApiName(): string
    {
        return $this->apiName;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getIconUrl(): ?string
    {
        return $this->iconUrl;
    }

    public function getIconGrayUrl(): ?string
    {
        return $this->iconGrayUrl;
    }

    public function isHidden(): bool
    {
        return (bool) $this->hidden;
    }

    public function getGlobalPercent(): ?float
    {
        return $this->globalPercent === null ? null : (float) $this->globalPercent;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }
}
