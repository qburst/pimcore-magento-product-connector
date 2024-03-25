<?php

declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace QBurst\ProductSyncBundle;

use Doctrine\DBAL\Schema\Schema;
use Pimcore\Extension\Bundle\Installer\Exception\InstallationException;
use Pimcore\Extension\Bundle\Installer\SettingsStoreAwareInstaller;

class Installer extends SettingsStoreAwareInstaller
{
    /**
     * Category of user permission for this bundle
     */
    protected const USER_PERMISSIONS_CATEGORY = 'Product Sync Bundle';

    /**
     * Permissions for users to access this bundle
     */
    const USER_PERMISSIONS = [
        'magento_product_connector'
    ];

    /**
     * @var Schema|null
     */
    protected ?Schema $schema = null;

    /**
     * Insert new permisisons in DB
     *
     * @return void
     */
    protected function addPermissions(): void
    {
        $db = \Pimcore\Db::get();

        foreach (self::USER_PERMISSIONS as $permission) {
            $db->insert('users_permission_definitions', [
                $db->quoteIdentifier('key') => $permission,
                $db->quoteIdentifier('category') => self::USER_PERMISSIONS_CATEGORY,
            ]);
        }
    }

    /**
     * Remove the permissions for this bundle from DB
     *
     * @return void
     */
    protected function removePermissions(): void
    {
        $db = \Pimcore\Db::get();

        foreach (self::USER_PERMISSIONS as $permission) {
            $db->delete('users_permission_definitions', [
                $db->quoteIdentifier('key') => $permission,
            ]);
        }
    }

    /**
     * Install the migrations
     *
     * @return void
     */
    public function install(): void
    {
        $this->addPermissions();
        parent::install();
    }

    /**
     * Uninstall the migrations
     *
     * @return void
     */
    public function uninstall(): void
    {
        $this->removePermissions();
        parent::uninstall();

        if (self::isInstalled()) {
            throw new InstallationException('Could not be uninstalled.');
        }
    }

    /**
     *@inheritDoc
     */
    public function needsReloadAfterInstall(): bool
    {
        return true;
    }

    /**
     * Get Schema details
     *
     * @return Schema
     */
    protected function getSchema(): Schema
    {
        return $this->schema ??= $this->db->createSchemaManager()->introspectSchema();
    }
}
