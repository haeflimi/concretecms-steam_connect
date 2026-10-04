<?php

namespace SteamConnect\Sync;

use Concrete\Core\Http\Client\Client;
use Concrete\Core\User\Group\GroupRepository;
use Concrete\Core\User\User;
use DateTime;
use GuzzleHttp\Exception\GuzzleException;
use SteamConnect\Api\SteamApiException;
use SteamConnect\SteamConfig;

/**
 * Checks which linked users are members of our Steam group ("club") and optionally keeps a Concrete group in sync.
 *
 * Steam has no API to add or invite group members: users have to join the group themselves (see hooked.php).
 * The member list is read from the public XML export of the group, which doesn't need the API key.
 */
class SteamClubSync
{
    /** Members per page of the XML export */
    protected const MEMBERS_PER_PAGE = 1000;

    /** Safety limit for the number of pages to read */
    protected const MAX_PAGES = 100;

    /** @var Client */
    protected $httpClient;

    /** @var SteamConfig */
    protected $config;

    /** @var SteamDataSync */
    protected $dataSync;

    /** @var GroupRepository */
    protected $groupRepository;

    public function __construct(Client $httpClient, SteamConfig $config, SteamDataSync $dataSync, GroupRepository $groupRepository)
    {
        $this->httpClient = $httpClient;
        $this->config = $config;
        $this->dataSync = $dataSync;
        $this->groupRepository = $groupRepository;
    }

    /**
     * Turn what an admin entered (group name, /groups/ or /gid/ URL) into the URL of the group.
     *
     * @return string|null null if the input is not a Steam group
     */
    public static function normalizeClubUrl(string $input): ?string
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }
        if (preg_match('#^(?:https?://)?steamcommunity\.com/(groups|gid)/([^/?\#]+)#i', $input, $matches)) {
            return 'https://steamcommunity.com/' . strtolower($matches[1]) . '/' . $matches[2];
        }
        if (preg_match('#^[A-Za-z0-9_-]+$#', $input)) {
            return 'https://steamcommunity.com/groups/' . $input;
        }

        return null;
    }

    public function getClubUrl(): ?string
    {
        return self::normalizeClubUrl((string) $this->config->get('club_url', ''));
    }

    /**
     * @param array<string, int> $accounts user IDs by SteamID of all linked accounts
     * @param callable|null $log
     *
     * @throws SteamApiException if the member list can't be read
     */
    public function syncMembership(array $accounts, ?callable $log = null): void
    {
        $log = $log ?: static function () {};
        $clubUrl = $this->getClubUrl();
        if ($clubUrl === null) {
            return;
        }

        $members = array_flip($this->getMemberIds($clubUrl));
        $log(t('The Steam group has %s members.', count($members)));

        $now = (new DateTime())->format('Y-m-d H:i:s');
        $memberUserIDs = [];
        foreach ($accounts as $steamId => $uID) {
            $isMember = isset($members[(string) $steamId]);
            $this->dataSync->saveProfile((string) $steamId, ['uID' => $uID, 'clubMember' => $isMember ? 1 : 0, 'clubCheckedAt' => $now]);
            if ($isMember) {
                $memberUserIDs[$uID] = true;
            }
        }
        $log(t('%s of %s linked users are members of the Steam group.', count($memberUserIDs), count($accounts)));

        $this->syncGroup(array_keys($memberUserIDs), $log);
    }

    /**
     * @return string[] SteamID64 of all members of the group
     */
    public function getMemberIds(string $clubUrl): array
    {
        $memberIds = [];
        for ($page = 1; $page <= self::MAX_PAGES; ++$page) {
            $xml = $this->fetchPage($clubUrl, $page);
            foreach ($xml->members->steamID64 ?? [] as $steamId) {
                $memberIds[] = (string) $steamId;
            }
            if ($page >= (int) $xml->totalPages || count($xml->members->steamID64 ?? []) < self::MEMBERS_PER_PAGE) {
                return $memberIds;
            }
        }

        // A partial list would mark real members as non-members
        throw new SteamApiException(t('The Steam group has more than %s members, which is not supported.', self::MAX_PAGES * self::MEMBERS_PER_PAGE));
    }

    protected function fetchPage(string $clubUrl, int $page): \SimpleXMLElement
    {
        try {
            $response = $this->httpClient->request('GET', $clubUrl . '/memberslistxml/', [
                'query' => ['xml' => 1, 'p' => $page],
                'timeout' => 20,
            ]);
        } catch (GuzzleException $e) {
            throw new SteamApiException(t('Unable to read the members of the Steam group: %s', $e->getMessage()), (int) $e->getCode(), $e);
        }
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string((string) $response->getBody(), \SimpleXMLElement::class, LIBXML_NOCDATA);
        libxml_use_internal_errors($previous);
        if ($xml === false || $xml->getName() !== 'memberList') {
            throw new SteamApiException(t('The Steam group %s was not found or its member list is not public.', $clubUrl));
        }

        return $xml;
    }

    /**
     * Make the members of the configured Concrete group match the linked Steam group members.
     *
     * @param int[] $memberUserIDs
     */
    protected function syncGroup(array $memberUserIDs, callable $log): void
    {
        $groupID = (int) $this->config->get('club_group_id');
        $group = $groupID ? $this->groupRepository->getGroupById($groupID) : null;
        if (!$group) {
            return;
        }

        $current = array_map('intval', $group->getGroupMemberIDs());
        $added = array_diff($memberUserIDs, $current);
        $removed = array_diff($current, $memberUserIDs);
        foreach ($added as $uID) {
            if ($user = User::getByUserID($uID)) {
                $user->enterGroup($group);
            }
        }
        foreach ($removed as $uID) {
            if ($user = User::getByUserID($uID)) {
                $user->exitGroup($group);
            }
        }
        $log(t('Group "%s": %s added, %s removed.', $group->getGroupDisplayName(false), count($added), count($removed)));
    }
}
