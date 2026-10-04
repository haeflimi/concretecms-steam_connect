<?php

namespace SteamConnect;

use Concrete\Core\Package\PackageService;

/**
 * Access to the package settings, see config/settings.php for the available keys and their defaults.
 */
class SteamConfig
{
    /** @var \Concrete\Core\Config\Repository\Liaison */
    protected $config;

    public function __construct(PackageService $packageService)
    {
        $this->config = $packageService->getClass('steam_connect')->getFileConfig();
    }

    public function get(string $key, $default = null)
    {
        return $this->config->get('settings.' . $key, $default);
    }

    public function save(string $key, $value): void
    {
        $this->config->save('settings.' . $key, $value);
    }

    public function getAchievementLanguage(): string
    {
        $language = strtolower(trim((string) $this->get('achievement_language', 'english')));

        return preg_match('/^[a-z]+$/', $language) ? $language : 'english';
    }
}
