<?php

namespace Concrete\Package\SteamConnect;

use Concrete\Core\Authentication\AuthenticationType;
use Concrete\Core\Package\Package;
use Throwable;

defined('C5_EXECUTE') or die('Access Denied.');

class Controller extends Package
{
    protected $pkgHandle = 'steam_connect';
    protected $appVersionRequired = '9.0.0';
    protected $pkgVersion = '1.0.0';
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

    public function install()
    {
        $pkg = parent::install();
        $this->installAuthenticationType($pkg);

        return $pkg;
    }

    public function upgrade()
    {
        parent::upgrade();
        $this->installAuthenticationType($this->getPackageEntity());
    }

    public function uninstall()
    {
        $type = $this->getAuthenticationType();
        if ($type !== null) {
            $type->delete();
        }
        parent::uninstall();
    }

    protected function installAuthenticationType($pkg): void
    {
        if ($this->getAuthenticationType() === null) {
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
