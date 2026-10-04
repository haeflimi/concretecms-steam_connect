<?php

namespace SteamConnect\Api;

use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Http\Client\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use SteamConnect\SteamConfig;

/**
 * Thin client for the parts of the Steam Web API we use. Needs the key configured in the Steam authentication type.
 *
 * @see https://developer.valvesoftware.com/wiki/Steam_Web_API
 */
class SteamWebApi
{
    protected const BASE_URL = 'https://api.steampowered.com/';

    /** Max. number of SteamIDs per GetPlayerSummaries / GetPlayerBans request */
    public const MAX_IDS_PER_REQUEST = 100;

    /** @var Client */
    protected $httpClient;

    /** @var string */
    protected $apiKey;

    /** @var string language of achievement names and descriptions */
    protected $language;

    public function __construct(Client $httpClient, Repository $config, SteamConfig $steamConfig)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = (string) $config->get('auth.steam.apikey', '');
        $this->language = $steamConfig->getAchievementLanguage();
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * @param string[] $steamIds
     *
     * @return array<string, array> player summaries by SteamID (missing for unknown IDs)
     */
    public function getPlayerSummaries(array $steamIds): array
    {
        $data = $this->get('ISteamUser/GetPlayerSummaries/v2/', ['steamids' => implode(',', $steamIds)]);

        return $this->indexBy($data['response']['players'] ?? [], 'steamid');
    }

    /**
     * @param string[] $steamIds
     *
     * @return array<string, array> ban information by SteamID
     */
    public function getPlayerBans(array $steamIds): array
    {
        $data = $this->get('ISteamUser/GetPlayerBans/v1/', ['steamids' => implode(',', $steamIds)]);

        return $this->indexBy($data['players'] ?? [], 'SteamId');
    }

    /**
     * @return array[]|null the owned games, null if the game details of the profile are not public
     */
    public function getOwnedGames(string $steamId): ?array
    {
        $data = $this->get('IPlayerService/GetOwnedGames/v1/', [
            'steamid' => $steamId,
            'include_appinfo' => 1,
            'include_played_free_games' => 1,
        ]);
        if (!isset($data['response']['game_count'])) {
            return null;
        }

        return $data['response']['games'] ?? [];
    }

    /**
     * @return int|null null if the profile is not public
     */
    public function getSteamLevel(string $steamId): ?int
    {
        $data = $this->get('IPlayerService/GetSteamLevel/v1/', ['steamid' => $steamId]);

        return isset($data['response']['player_level']) ? (int) $data['response']['player_level'] : null;
    }

    /**
     * @return array[]|null the achievements of the player (apiname, achieved, unlocktime),
     *                      null if the game has no stats or the player's game details are private
     */
    public function getPlayerAchievements(string $steamId, int $appId): ?array
    {
        try {
            $data = $this->get('ISteamUserStats/GetPlayerAchievements/v1/', ['steamid' => $steamId, 'appid' => $appId]);
        } catch (SteamApiException $e) {
            // Steam answers 400/403 with {"playerstats": {"success": false, "error": "..."}} for games
            // without stats and for private profiles: that's not a problem of the request itself.
            if (isset($e->getResponseData()['playerstats']['error'])) {
                return null;
            }
            throw $e;
        }
        if (empty($data['playerstats']['success'])) {
            return null;
        }

        return $data['playerstats']['achievements'] ?? [];
    }

    /**
     * @return array[] the achievements of a game (name, displayName, description, icon, icongray, hidden)
     */
    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getAchievementSchema(int $appId): array
    {
        $data = $this->get('ISteamUserStats/GetSchemaForGame/v2/', ['appid' => $appId, 'l' => $this->language]);

        return $data['game']['availableGameStats']['achievements'] ?? [];
    }

    /**
     * @return array<string, float> percentage of all players who unlocked an achievement, by achievement API name
     */
    public function getGlobalAchievementPercentages(int $appId): array
    {
        $data = $this->get('ISteamUserStats/GetGlobalAchievementPercentagesForApp/v2/', ['gameid' => $appId]);
        $result = [];
        foreach ($data['achievementpercentages']['achievements'] ?? [] as $achievement) {
            if (isset($achievement['name'])) {
                $result[(string) $achievement['name']] = (float) ($achievement['percent'] ?? 0);
            }
        }

        return $result;
    }

    protected function get(string $method, array $query): array
    {
        if (!$this->hasApiKey()) {
            throw new SteamApiException('No Steam Web API key configured.', 401);
        }
        try {
            $response = $this->httpClient->request('GET', self::BASE_URL . $method, [
                'query' => ['key' => $this->apiKey, 'format' => 'json'] + $query,
                'timeout' => 15,
            ]);
        } catch (RequestException $e) {
            $response = $e->getResponse();
            $status = $response ? $response->getStatusCode() : 0;
            $data = $response ? json_decode((string) $response->getBody(), true) : null;
            throw (new SteamApiException(sprintf('Steam API request %s failed with HTTP status %d.', $method, $status), $status, $e))
                ->setResponseData(is_array($data) ? $data : null);
        } catch (GuzzleException $e) {
            throw new SteamApiException(sprintf('Steam API request %s failed: %s', $method, $e->getMessage()), 0, $e);
        }

        $data = json_decode((string) $response->getBody(), true);
        if (!is_array($data)) {
            throw new SteamApiException(sprintf('Steam API request %s returned an invalid response.', $method));
        }

        return $data;
    }

    protected function indexBy(array $rows, string $key): array
    {
        $result = [];
        foreach ($rows as $row) {
            if (isset($row[$key])) {
                $result[(string) $row[$key]] = $row;
            }
        }

        return $result;
    }
}
