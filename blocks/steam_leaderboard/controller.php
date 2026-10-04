<?php

namespace Concrete\Package\SteamConnect\Block\SteamLeaderboard;

use Concrete\Core\Block\BlockController;
use DateTime;
use DateTimeInterface;
use SteamConnect\Entity\SteamApp;
use SteamConnect\Statistics\AchievementLeaderboard;
use SteamConnect\Statistics\GameStatistics;

defined('C5_EXECUTE') or die('Access Denied.');

class Controller extends BlockController
{
    protected $btTable = 'btSteamConnectLeaderboard';
    protected $btInterfaceWidth = 500;
    protected $btInterfaceHeight = 480;
    protected $btDefaultSet = 'social';

    // The data changes once a day (Sync Steam Data task), the queries are not free
    protected $btCacheBlockOutput = true;
    protected $btCacheBlockOutputOnPost = true;
    protected $btCacheBlockOutputForRegisteredUsers = true;
    protected $btCacheBlockOutputLifetime = 3600;

    /** @var string|null */
    public $title;
    /** @var string achievements, perfect_games, most_played or most_owned */
    public $leaderboard;
    /** @var string all, 14, 30, 90, 365 or since */
    public $period;
    /** @var string|null Y-m-d, used with period "since" */
    public $sinceDate;
    /** @var int achievements of this game only, 0 = all games */
    public $appId;
    /** @var int */
    public $maxItems;

    public function getBlockTypeName()
    {
        return t('Steam Leaderboard');
    }

    public function getBlockTypeDescription()
    {
        return t('Achievement leaderboard and most played / most owned games of the users who linked their Steam account.');
    }

    public function add()
    {
        $this->set('title', '');
        $this->set('leaderboard', 'achievements');
        $this->set('period', 'all');
        $this->set('sinceDate', null);
        $this->set('appId', 0);
        $this->set('maxItems', 10);
        $this->setFormOptions();
    }

    public function edit()
    {
        $this->setFormOptions();
    }

    public function validate($args)
    {
        $error = $this->app->make('helper/validation/error');
        if (!isset($this->getLeaderboardOptions()[$args['leaderboard'] ?? ''])) {
            $error->add(t('Please choose a leaderboard.'));
        }
        if (($args['period'] ?? '') === 'since' && !$this->parseDate($args['sinceDate'] ?? '')) {
            $error->add(t('Please enter the start date.'));
        }

        return $error;
    }

    public function save($args)
    {
        $leaderboard = (string) ($args['leaderboard'] ?? 'achievements');
        $period = isset($this->getPeriodOptions()[$args['period'] ?? '']) ? $args['period'] : 'all';
        $since = $period === 'since' ? $this->parseDate($args['sinceDate'] ?? '') : null;
        parent::save([
            'title' => trim((string) ($args['title'] ?? '')),
            'leaderboard' => $leaderboard,
            'period' => $period,
            'sinceDate' => $since ? $since->format('Y-m-d') : null,
            'appId' => $leaderboard === 'achievements' ? (int) ($args['appId'] ?? 0) : 0,
            'maxItems' => min(100, max(1, (int) ($args['maxItems'] ?? 10))),
        ]);
    }

    public function view()
    {
        $limit = max(1, (int) $this->maxItems);
        $since = $this->getSince();
        $numbers = $this->app->make('helper/number');

        switch ($this->leaderboard) {
            case 'perfect_games':
                $rows = $this->playerRows(
                    $this->app->make(AchievementLeaderboard::class)->getPerfectGamesRanking($limit),
                    'perfectGames',
                    function ($row) use ($numbers) {
                        return [t2('%s perfect game', '%s perfect games', (int) $row['perfectGames'], $numbers->format($row['perfectGames'])), ''];
                    }
                );
                break;
            case 'most_played':
                $period = $this->period === 'all' ? 'all' : ($this->period === '14' ? 'two_weeks' : $since);
                $rows = $this->gameRows(
                    $this->app->make(GameStatistics::class)->getMostPlayed($period, $limit),
                    'minutes',
                    function ($row) use ($numbers) {
                        return [$this->formatPlaytime((int) $row['minutes']), t2('%s player', '%s players', (int) $row['players'], $numbers->format($row['players']))];
                    }
                );
                break;
            case 'most_owned':
                $statistics = $this->app->make(GameStatistics::class);
                $libraries = $statistics->getLibraryCount();
                $rows = $this->gameRows(
                    $statistics->getMostOwned($limit),
                    'owners',
                    function ($row) use ($numbers, $libraries) {
                        return [
                            t('%s of %s', $numbers->format($row['owners']), $numbers->format($libraries)),
                            t('%s played in total', $this->formatPlaytime((int) $row['minutes'])),
                        ];
                    }
                );
                break;
            default:
                $rows = $this->playerRows(
                    $this->app->make(AchievementLeaderboard::class)->getRanking((int) $this->appId ?: null, $since, $limit),
                    'points',
                    function ($row) use ($numbers) {
                        return [
                            t('%s pts', $numbers->format($row['points'])),
                            t2('%s achievement', '%s achievements', (int) $row['achievements'], $numbers->format($row['achievements'])),
                        ];
                    }
                );
                break;
        }

        $this->set('rows', $rows);
        $this->set('heading', $this->title !== null && $this->title !== '' ? $this->title : $this->getLeaderboardOptions()[$this->leaderboard] ?? '');
        $this->set('subtitle', $this->getSubtitle($since));
    }

    protected function getSubtitle(?DateTimeInterface $since): string
    {
        $parts = [];
        if ($this->leaderboard === 'achievements' && $this->appId) {
            $games = $this->app->make(GameStatistics::class)->getGamesWithAchievements();
            if (isset($games[(int) $this->appId])) {
                $parts[] = $games[(int) $this->appId];
            }
        }
        if ($this->supportsPeriod()) {
            if ($this->period === 'since' && $since) {
                $parts[] = t('Since %s', $this->app->make('date')->formatDate($since));
            } else {
                $parts[] = $this->getPeriodOptions()[$this->period] ?? '';
            }
        }

        return implode(' · ', array_filter($parts));
    }

    protected function supportsPeriod(): bool
    {
        return in_array($this->leaderboard, ['achievements', 'most_played'], true);
    }

    protected function getSince(): ?DateTimeInterface
    {
        if (!$this->supportsPeriod() || $this->period === 'all') {
            return null;
        }
        if ($this->period === 'since') {
            return $this->parseDate((string) $this->sinceDate);
        }

        return new DateTime('-' . (int) $this->period . ' days');
    }

    /**
     * @param callable $labels returns [value label, meta text] for a row
     */
    protected function playerRows(array $ranking, string $valueColumn, callable $labels): array
    {
        $rows = [];
        foreach ($ranking as $row) {
            [$valueLabel, $meta] = $labels($row);
            $rows[] = [
                'rank' => (int) $row['rank'],
                'name' => (string) ($row['personaName'] ?: $row['steamId']),
                'url' => 'https://steamcommunity.com/profiles/' . $row['steamId'],
                'image' => $row['avatarUrl'] ?: null,
                'value' => (float) $row[$valueColumn],
                'valueLabel' => $valueLabel,
                'meta' => $meta,
            ];
        }

        return $rows;
    }

    /**
     * @param callable $labels returns [value label, meta text] for a row
     */
    protected function gameRows(array $games, string $valueColumn, callable $labels): array
    {
        $rows = [];
        $rank = 0;
        $previous = null;
        foreach ($games as $index => $game) {
            if ($game[$valueColumn] !== $previous) {
                $rank = $index + 1;
                $previous = $game[$valueColumn];
            }
            [$valueLabel, $meta] = $labels($game);
            $rows[] = [
                'rank' => $rank,
                'name' => (string) $game['name'],
                'url' => SteamApp::buildStoreUrl((int) $game['appId']),
                'image' => SteamApp::buildIconUrl((int) $game['appId'], $game['iconHash']),
                'value' => (float) $game[$valueColumn],
                'valueLabel' => $valueLabel,
                'meta' => $meta,
            ];
        }

        return $rows;
    }

    protected function formatPlaytime(int $minutes): string
    {
        if ($minutes < 60) {
            return t('%s min', $minutes);
        }

        return t('%s h', $this->app->make('helper/number')->format(round($minutes / 60)));
    }

    protected function parseDate(string $date): ?DateTime
    {
        $parsed = DateTime::createFromFormat('!Y-m-d', trim($date));

        return $parsed ?: null;
    }

    protected function setFormOptions(): void
    {
        $this->set('leaderboardOptions', $this->getLeaderboardOptions());
        $this->set('periodOptions', $this->getPeriodOptions());
        $this->set('gameOptions', [0 => t('All games')] + $this->app->make(GameStatistics::class)->getGamesWithAchievements());
    }

    protected function getLeaderboardOptions(): array
    {
        return [
            'achievements' => t('Achievement Leaderboard'),
            'perfect_games' => t('Perfect Games (all achievements unlocked)'),
            'most_played' => t('Most Played Games'),
            'most_owned' => t('Most Owned Games'),
        ];
    }

    protected function getPeriodOptions(): array
    {
        return [
            'all' => t('All time'),
            '14' => t('Last 2 weeks'),
            '30' => t('Last 30 days'),
            '90' => t('Last 90 days'),
            '365' => t('Last 12 months'),
            'since' => t('Since a date'),
        ];
    }
}
