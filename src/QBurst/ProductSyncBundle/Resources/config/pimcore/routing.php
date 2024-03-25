<?php

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

$collection = new RouteCollection();

$collection->add(
    'productsync_connector_settings', new Route(
        '/productsync_connector_settings',
        [
            '_controller' => 'QBurst\ProductSyncBundle\Controller\AdminConfigurationController::indexAction',
        ]
    )
);

return $collection;
