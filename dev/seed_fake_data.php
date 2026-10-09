<?php
// Dev only: made-up Steam data so the Steam Leaderboard block shows something without a Steam connection.
// Profiles get steam IDs 765611900000000xx (not a real range); run again to regenerate, dev/remove_fake_data.php removes it.
// Run: docker exec -u www-data devturicanech-app-1 php /app/root/public/concrete/bin/concrete c5:exec --no-interaction /app/root/public/packages/steam_connect/dev/seed_fake_data.php

$app = Concrete\Core\Support\Facade\Application::getFacadeApplication();
$db = $app->make('database')->connection();
mt_srand(35);

// appId, name, icon hash (real, from the Steam app info), share of members owning it, typical hours of a fan
$games = [
    [730, "Counter-Strike 2", '8dbc71957312bbd3baea65848b545be9eae2a355', 0.9, 900],
    [570, "Dota 2", '0bbb630d63262dd66d2fdd0f7d37e8661a410075', 0.35, 1400],
    [813780, "Age of Empires II: Definitive Edition", 'e2b5a7beb58136b892e517cb93ae08b36065363c', 0.6, 260],
    [252950, "Rocket League", '9ad6dd3d173523354385955b5fb2af87639c4163', 0.55, 180],
    [440, "Team Fortress 2", 'f568912870a4684f9ec76277a1a404dda6bab213', 0.7, 120],
    [550, "Left 4 Dead 2", '7d5a243f9500d2f8467312822f8af2a2928777ed', 0.65, 90],
    [548430, "Deep Rock Galactic", 'e033e23c29a192a17c16a7645a2b423ac64ff447', 0.5, 150],
    [892970, "Valheim", '2f64c9a826e2c6cf3253fea4834c2e612db09143', 0.45, 110],
    [427520, "Factorio", '267f5a89f36ab287e600a4e7d4e73d3d11f0fd7d', 0.3, 400],
    [413150, "Stardew Valley", '35d1377200084a4034238c05b0c8930451e2fb40', 0.4, 120],
    [13230, "Unreal Tournament 2004", 'db3782214c4285c254f632b0e803ed341c250c9d', 0.35, 60],
    [2225070, "Trackmania", '4b0f99cb45eef02df25bee27eb3d52052e8f8b55', 0.4, 140],
    [289070, "Sid Meier's Civilization VI", '9dc914132fec244adcede62fb8e7524a72a7398c', 0.35, 300],
    [105600, "Terraria", '858961e95fbf869f136e1770d586e0caefd4cfac', 0.5, 140],
    [1086940, "Baldur's Gate 3", 'd866cae7ea1e471fdbc206287111f1b642373bd9', 0.4, 160],
    [553850, "HELLDIVERS\u2122 2", null, 0.3, 90],
    [240, "Counter-Strike: Source", '9052fa60c496a1c03383b27687ec50f4bf0f0e10', 0.55, 200],
    [1966720, "Lethal Company", '80d2453285274e4723c884a671cdd3f8fe2f766f', 0.35, 40],
    [526870, "Satisfactory", 'ee3406fe5ec813b1987ad67e37e5cd6fb4f620e6', 0.25, 260],
    [431240, "Golf With Your Friends", 'c6379c8ec66ac02565f1155bf3821b846164d93c', 0.4, 25],
    [327030, "Worms W.M.D", '9f18090e88fd50a0a85a66a1a9872bd36c856b58', 0.3, 30],
    [282440, "Quake Live", 'bac9828d3e193c948801b14660490576fbbf9f72', 0.2, 150],
    [1172470, "Apex Legends", '8986dd626da56db5f3fe09bc1b8871739de8b00d', 0.3, 220],
];

include __DIR__ . '/remove_fake_data.php';

$users = $db->fetchFirstColumn('SELECT ug.uID FROM UserGroups ug LEFT JOIN SteamConnectProfiles p ON p.uID = ug.uID WHERE ug.gID = 34 AND p.uID IS NULL ORDER BY ug.uID LIMIT 24');
$now = new DateTimeImmutable();
$fmt = static fn (DateTimeImmutable $d) => $d->format('Y-m-d H:i:s');
foreach ($games as [$appId, $name, $icon]) {
    $db->executeStatement('INSERT INTO SteamConnectApps (appId, name, iconHash, hasCommunityVisibleStats, updatedAt) VALUES (?, ?, ?, 1, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), iconHash = VALUES(iconHash)', [$appId, $name, $icon, $fmt($now)]);
}
$rand = static fn () => mt_rand() / mt_getrandmax();
foreach ($users as $n => $uID) {
    $steamId = sprintf('765611900000000%02d', $n + 1);
    $ui = $app->make(Concrete\Core\User\UserInfoRepository::class)->getByID($uID);
    $total = $recent = $count = 0;
    foreach ($games as [$appId, , , $share, $hours]) {
        if ($rand() > $share) {
            continue;
        }
        $count++;
        // most people dabble, a few sink hundreds of hours into a game
        $minutes = $rand() < .15 ? 0 : (int) round($hours * 60 * (0.05 + $rand() ** 2.2 * 2.5));
        $twoWeeks = $minutes > 0 && $rand() < .3 ? (int) min($minutes, round($rand() * 1500)) : 0;
        $total += $minutes;
        $recent += $twoWeeks;
        $db->insert('SteamConnectOwnedGames', [
            'steamId' => $steamId, 'appId' => $appId, 'playtimeForever' => $minutes, 'playtime2Weeks' => $twoWeeks,
            'lastPlayedAt' => $minutes ? $fmt($now->modify('-' . ($twoWeeks ? mt_rand(0, 13) : mt_rand(14, 400)) . ' days')) : null,
            'firstSeenAt' => $fmt($now->modify('-400 days')), 'updatedAt' => $fmt($now),
        ]);
        // snapshots of the daily sync: the total grew towards today, the last two weeks are Steam's own value
        // snapshots of the daily sync: the total grew towards today; two weeks ago it was today's minus Steam's 2-week value
        if ($minutes > 0) {
            $base = $minutes - $twoWeeks;
            $pace = 0.6 + $rand() * 0.8;
            foreach ([380 => .25, 200 => .45, 95 => .65, 40 => .85, 14 => 1] as $daysAgo => $share) {
                $db->insert('SteamConnectPlaytimeHistory', ['steamId' => $steamId, 'appId' => $appId, 'recordedAt' => $fmt($now->modify("-$daysAgo days")),
                    'playtimeForever' => (int) round($base * min(1, $share ** $pace))]);
            }
        }
    }
    $db->insert('SteamConnectProfiles', [
        'steamId' => $steamId, 'uID' => $uID, 'personaName' => $ui ? $ui->getUserName() : 'player' . $n,
        'profileUrl' => 'https://steamcommunity.com/profiles/' . $steamId . '/', 'avatarUrl' => null,
        'communityVisibilityState' => 3, 'gameDetailsVisible' => 1, 'countryCode' => 'CH', 'steamLevel' => mt_rand(3, 80),
        'gameCount' => $count, 'totalPlaytime' => $total, 'recentPlaytime' => $recent, 'syncedAt' => $fmt($now), 'clubMember' => 1,
    ]);
}
$app->make('cache/request')->flush();
echo count($users) . " fake Steam profiles\n";
