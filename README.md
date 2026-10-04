# Steam Connect Package #

This package integrates an authenticator for Valve's Steam gaming platform. It allows your website
visitors to register and login using their Steam login, linking the concreteCMS account with their Steam ID
in the process.

**This is NOT a official implementation by the Valve Corporation. The creator is not associated with
the Valve Corporation in any way.**

## Usage ##

- Clone or download this repo from GitHub or install via the concrete5 Marketplace.
- Install the Package via c5 Dashboard.
- On /dashboard/system/registration/authentication Steam should be available as a Authentication
type now.
- Edit it, optionally enter a Steam Web API Key (used to suggest the Steam display name as username on registration), and enable it.
- You are good to login with Steam now.

## Configuration ##

- **Dashboard** (System & Settings › Login & Registration › Authentication Types › Steam): Steam Web API key,
  registration, our Steam group and the Concrete group its members are put in.
- **Config file**: the package settings and their defaults are in `config/settings.php` (Steam group, Concrete
  group for its members, language of achievement names). Override them in
  `application/config/steam_connect/settings.php`, e.g.

  ```php
  <?php

  return [
      'achievement_language' => 'german',
  ];
  ```

  Values saved in the dashboard are written to `application/config/generated_overrides/steam_connect/`.

## Features ##

- Register new Users using Steam Login
- Login existing Users using Steam Login
- Connect Steam Account to concreteCMS account via User Profile

## planned Features ##

- maybe Integrate Steam API capabilities for developers
- maybe integrate a Block to display common/ interesting Information from Steam

## Prerequisites ##

- Concrete CMS 9.0 or higher, PHP 8
- optional: Steam Web API Key [Get your own Steam API Key here](https://steamcommunity.com/dev/apikey)

## Credits ##

- Thanks to the concrete 5 core team for creating and maintaining an amazing CMS.

## Support ##

This Package is Open Source software under the MIT License. It is provided "as is",
without warranty of any kind.
However, if you find a Bug or any kind of problem with it.: Feel free to create a Issue or
Pull Request here on Github.
