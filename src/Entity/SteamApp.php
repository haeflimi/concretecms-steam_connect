<?php

namespace SteamConnect\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * A Steam game (app) that at least one linked user owns.
 *
 * @ORM\Entity()
 * @ORM\Table(name="SteamConnectApps")
 */
class SteamApp
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer", options={"unsigned": true})
     */
    protected $appId;

    /**
     * @ORM\Column(type="string", length=255)
     */
    protected $name;

    /**
     * Hash of the icon, see getIconUrl().
     *
     * @ORM\Column(type="string", length=64, nullable=true)
     */
    protected $iconHash;

    /**
     * Whether the game publishes stats/achievements (ISteamUserStats).
     *
     * @ORM\Column(type="boolean", options={"default": false})
     */
    protected $hasCommunityVisibleStats = false;

    /**
     * Number of achievements of the game, null until the schema has been loaded.
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $achievementCount;

    /**
     * When the achievement schema and the global unlock rates were loaded.
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $achievementsUpdatedAt;

    /**
     * Steam API language the achievement names were loaded in, they're reloaded when the configured one changes.
     *
     * @ORM\Column(type="string", length=32, nullable=true)
     */
    protected $achievementsLanguage;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $updatedAt;

    public function getAppId(): int
    {
        return (int) $this->appId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getIconUrl(): ?string
    {
        return self::buildIconUrl((int) $this->appId, $this->iconHash);
    }

    public static function buildIconUrl(int $appId, ?string $iconHash): ?string
    {
        return $iconHash ? sprintf('https://media.steampowered.com/steamcommunity/public/images/apps/%d/%s.jpg', $appId, $iconHash) : null;
    }

    public function getHeaderImageUrl(): string
    {
        return sprintf('https://cdn.cloudflare.steamstatic.com/steam/apps/%d/header.jpg', $this->appId);
    }

    public function getStoreUrl(): string
    {
        return self::buildStoreUrl((int) $this->appId);
    }

    public static function buildStoreUrl(int $appId): string
    {
        return sprintf('https://store.steampowered.com/app/%d/', $appId);
    }

    public function hasCommunityVisibleStats(): bool
    {
        return (bool) $this->hasCommunityVisibleStats;
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    public function getAchievementCount(): ?int
    {
        return $this->achievementCount === null ? null : (int) $this->achievementCount;
    }

    public function getAchievementsUpdatedAt(): ?DateTime
    {
        return $this->achievementsUpdatedAt;
    }

    public function getAchievementsLanguage(): ?string
    {
        return $this->achievementsLanguage;
    }
}
