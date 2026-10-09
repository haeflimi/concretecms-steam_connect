<?php
// Dev only: removes the made-up Steam data of dev/seed_fake_data.php (steam IDs 765611900000000xx), apps stay.
$db = Concrete\Core\Support\Facade\Application::getFacadeApplication()->make('database')->connection();
foreach (['SteamConnectPlaytimeHistory', 'SteamConnectOwnedGames', 'SteamConnectProfiles'] as $table) {
    $db->executeStatement("DELETE FROM $table WHERE steamId LIKE '765611900000000%'");
}
