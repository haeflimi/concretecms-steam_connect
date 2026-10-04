<?php

// Defaults of the Steam Connect package. Override them in application/config/steam_connect/settings.php,
// e.g. return ['achievement_language' => 'german'];
// Values saved in the dashboard (Steam authentication type) end up in application/config/generated_overrides.
return [
    // Our Steam group: its URL (https://steamcommunity.com/groups/<name> or /gid/<id>) or just its name.
    // The "Sync Steam Data" task checks which linked users are members, empty = disabled.
    'club_url' => '',
    // ID of a Concrete group that is kept in sync with the linked members of the Steam group, 0 = none
    'club_group_id' => 0,
    // Language of achievement names and descriptions, a Steam API language code:
    // english, german, french, italian, spanish, latam, portuguese, brazilian, dutch, danish, swedish,
    // norwegian, finnish, polish, czech, hungarian, romanian, russian, ukrainian, turkish, greek,
    // japanese, koreana, schinese, tchinese, thai, vietnamese, bulgarian, arabic
    'achievement_language' => 'english',
];
