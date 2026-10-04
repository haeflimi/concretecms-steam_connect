<?php

namespace SteamConnect\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * Total playtime of a game at a point in time. A row is only written when the total changed since the last sync,
 * so the playtime within any period is the difference between the last values before its start and its end.
 *
 * @ORM\Entity()
 * @ORM\Table(name="SteamConnectPlaytimeHistory", indexes={
 *     @ORM\Index(name="steam_app_time", columns={"steamId", "appId", "recordedAt"}),
 *     @ORM\Index(name="app_time", columns={"appId", "recordedAt"})
 * })
 */
class SteamPlaytimeHistory
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer", options={"unsigned": true})
     * @ORM\GeneratedValue
     */
    protected $id;

    /**
     * @ORM\Column(type="string", length=20)
     */
    protected $steamId;

    /**
     * @ORM\Column(type="integer", options={"unsigned": true})
     */
    protected $appId;

    /**
     * @ORM\Column(type="datetime")
     */
    protected $recordedAt;

    /**
     * Total playtime in minutes at recordedAt.
     *
     * @ORM\Column(type="integer")
     */
    protected $playtimeForever;

    public function getID(): int
    {
        return (int) $this->id;
    }

    public function getSteamId(): string
    {
        return $this->steamId;
    }

    public function getAppId(): int
    {
        return (int) $this->appId;
    }

    public function getRecordedAt(): DateTime
    {
        return $this->recordedAt;
    }

    public function getPlaytimeForever(): int
    {
        return (int) $this->playtimeForever;
    }
}
