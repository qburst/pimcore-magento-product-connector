<?php

namespace QBurst\ProductSyncBundle;

use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Pimcore\Extension\Bundle\PimcoreBundleAdminClassicInterface;
use Pimcore\Extension\Bundle\Traits\BundleAdminClassicTrait;

class QBurstProductSyncBundle extends AbstractPimcoreBundle implements PimcoreBundleAdminClassicInterface
{
    use BundleAdminClassicTrait;

    /**
     * Name of the bundle
     */
    const PACKAGE_NAME = 'qburst/productsyncbundle';

    /**
     * Get the list of js files for loading
     *
     * @return array
     */
    public function getJsPaths(): array
    {
        return [
            '/bundles/qburstproductsync/js/pimcore/startup.js',
        ];
    }

    /**
     * Get the list of css files for loading
     *
     * @return array
     */
    public function getCssPaths(): array
    {
        return [
            '/bundles/qburstproductsync/css/message.css',
        ];
    }

    /**
     * Returns the name
     *
     * @return string
     */
    public function getNiceName(): string
    {
        return 'Magento Connector';
    }

    /**
     * Returns the description
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Magento Connector for Pimcore';
    }

    /**
     * Returns the composer package name
     *
     * @return string
     */
    protected function getComposerPackageName(): string
    {
        return self::PACKAGE_NAME;
    }

    public function getInstaller(): Installer
    {
        return $this->container->get(Installer::class);
    }
}
