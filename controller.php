<?php

namespace Concrete\Package\SteamConnect;

use Concrete\Core\Authentication\AuthenticationType;
use Concrete\Core\Backup\ContentImporter;
use Concrete\Core\Command\Task\Manager as TaskManager;
use Concrete\Core\Database\EntityManager\Provider\ProviderAggregateInterface;
use Concrete\Core\Database\EntityManager\Provider\StandardPackageProvider;
use Concrete\Core\Package\Package;
use SteamConnect\Command\Task\SyncSteamDataController;
use SteamConnect\Install\LegacyPackageCleanup;
use SteamConnect\SteamConfig;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

class Controller extends Package implements ProviderAggregateInterface
{
    protected $pkgHandle = 'steam_connect';
    protected $appVersionRequired = '9.0.0';
    protected $pkgVersion = '1.5.0';
    protected $pkgAutoloaderRegistries = [
        'src' => 'SteamConnect',
    ];

    public function getPackageName()
    {
        return t('Steam Connect');
    }

    public function getPackageDescription()
    {
        return t('Adds an Authenticator for Valve\'s Steam gaming platform.');
    }

    public function getEntityManagerProvider()
    {
        return new StandardPackageProvider($this->app, $this, [
            'src/Entity' => 'SteamConnect\Entity',
        ]);
    }

    public function on_start()
    {
        $this->app->make(TaskManager::class)->extend('sync_steam_data', function () {
            return $this->app->make(SyncSteamDataController::class);
        });
    }

    public function install()
    {
        $pkg = parent::install();
        $this->app->make(LegacyPackageCleanup::class)->run($pkg);
        $this->installAuthenticationType($pkg);
        $this->installContent();

        return $pkg;
    }

    public function upgrade()
    {
        parent::upgrade();
        $this->app->make(LegacyPackageCleanup::class)->run($this->getPackageEntity());
        $this->installAuthenticationType($this->getPackageEntity());
        $this->installContent();
        $this->migrateClubSettings();
    }

    public function uninstall()
    {
        $type = $this->getAuthenticationType();
        if ($type !== null) {
            $type->delete();
        }
        parent::uninstall();
    }

    /**
     * Up to 1.2.0 the Steam group was stored in the core config (auth.steam.club.*).
     */
    protected function migrateClubSettings(): void
    {
        $config = $this->app->make('config');
        $steamConfig = $this->app->make(SteamConfig::class);
        foreach (['url' => 'club_url', 'group' => 'club_group_id'] as $old => $new) {
            $value = $config->get('auth.steam.club.' . $old);
            if ($value !== null) {
                if (empty($steamConfig->get($new))) {
                    $steamConfig->save($new, $old === 'group' ? (int) $value : (string) $value);
                }
                $config->save('auth.steam.club.' . $old, null);
            }
        }
    }

    protected function installContent(): void
    {
        $importer = new ContentImporter();
        $importer->importContentFile($this->getPackagePath() . '/install.xml');
    }

    protected function installAuthenticationType($pkg): void
    {
        // Checked in the database: loading the type would need its controller, which is not available while
        // installing (and not at all if it still belongs to a removed package)
        if (!$this->app->make('database')->connection()->fetchOne("SELECT 1 FROM AuthenticationTypes WHERE authTypeHandle = 'steam'")) {
            // Installed disabled: it has to be enabled in /dashboard/system/registration/authentication.
            AuthenticationType::add('steam', 'Steam', 0, $pkg)->disable();
        }
    }

    protected function getAuthenticationType(): ?AuthenticationType
    {
        try {
            $type = AuthenticationType::getByHandle('steam');
        } catch (Throwable $e) {
            return null;
        }

        return is_object($type) && !$type->isError() ? $type : null;
    }
}
