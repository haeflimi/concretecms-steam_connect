<?php

namespace SteamConnect\Install;

use Concrete\Core\Application\Application;
use Concrete\Core\Database\Connection\Connection;
use Concrete\Core\Entity\Package as PackageEntity;
use Concrete\Core\Logging\Channels;
use Concrete\Core\Package\ItemCategory\Manager;
use Concrete\Core\Package\PackageService;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Removes the predecessor of this package ("steam_authentication", in its very first version "steam").
 *
 * The old package only installed the "steam" authentication type. The linked accounts (OauthUserMap, namespace
 * "steam") and the settings (auth.steam.*) don't belong to a package, so they're simply used by this package.
 * The authentication type is taken over instead of being recreated, so it keeps its ID, its enabled state and
 * its position on the login page. The old package is then removed without running its own controller: that
 * code is outdated, and its uninstall() would delete the "steam" authentication type we just took over.
 */
class LegacyPackageCleanup
{
    /** Handles the old package was installed with */
    protected const LEGACY_HANDLES = ['steam_authentication', 'steam'];

    /** @var Application */
    protected $app;

    /** @var Connection */
    protected $db;

    /** @var PackageService */
    protected $packageService;

    /** @var EntityManagerInterface */
    protected $entityManager;

    public function __construct(Application $app, Connection $db, PackageService $packageService, EntityManagerInterface $entityManager)
    {
        $this->app = $app;
        $this->db = $db;
        $this->packageService = $packageService;
        $this->entityManager = $entityManager;
    }

    /**
     * @return string[] handles of the removed packages
     */
    public function run(PackageEntity $package): array
    {
        $removed = [];
        foreach ($this->findLegacyPackages() as $legacy) {
            $this->takeOverAuthenticationType($legacy, $package);
            $this->removePackage($legacy);
            $removed[] = $legacy->getPackageHandle();
        }
        if ($removed) {
            $linkedAccounts = (int) $this->db->fetchOne("SELECT COUNT(*) FROM OauthUserMap WHERE namespace = 'steam'");
            $this->app->make('log/factory')->createLogger(Channels::CHANNEL_PACKAGES)->notice(
                t('Steam Connect replaced the old package(s) %s and took over %s linked Steam accounts and the settings. The folder(s) %s can be deleted.',
                    implode(', ', $removed),
                    $linkedAccounts,
                    implode(', ', array_map(static function ($handle) { return DIRNAME_PACKAGES . '/' . $handle; }, $removed))
                )
            );
        }

        return $removed;
    }

    /**
     * @return PackageEntity[]
     */
    protected function findLegacyPackages(): array
    {
        $packages = [];
        foreach (self::LEGACY_HANDLES as $handle) {
            $legacy = $this->packageService->getByHandle($handle);
            if ($legacy === null) {
                continue;
            }
            // "steam" is a generic name: only touch it if it is really the old version of this package
            if ($handle === 'steam' && !$this->ownsAuthenticationType($legacy)) {
                continue;
            }
            $packages[] = $legacy;
        }

        return $packages;
    }

    protected function ownsAuthenticationType(PackageEntity $package): bool
    {
        return (bool) $this->db->fetchOne(
            "SELECT 1 FROM AuthenticationTypes WHERE authTypeHandle = 'steam' AND pkgID = ?",
            [$package->getPackageID()]
        );
    }

    protected function takeOverAuthenticationType(PackageEntity $legacy, PackageEntity $package): void
    {
        $this->db->update(
            'AuthenticationTypes',
            ['pkgID' => $package->getPackageID()],
            ['authTypeHandle' => 'steam', 'pkgID' => $legacy->getPackageID()]
        );
    }

    /**
     * What Package::uninstall() does, without loading the old package controller.
     */
    protected function removePackage(PackageEntity $legacy): void
    {
        $manager = $this->app->make(Manager::class, ['application' => $this->app]);
        foreach ($manager->getPackageItemCategories() as $category) {
            if ($category->hasItems($legacy)) {
                $category->removeItems($legacy);
            }
        }
        $this->app->make('config')->clearNamespace($legacy->getPackageHandle());
        $this->app->make('config/database')->clearNamespace($legacy->getPackageHandle());

        $this->entityManager->remove($legacy);
        $this->entityManager->flush();
    }
}
