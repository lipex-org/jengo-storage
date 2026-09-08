<?php

declare(strict_types=1);

namespace Jengo\Storage\Config;

use CodeIgniter\Router\RouteCollection;
use Jengo\Storage\Controllers\SignedStorageController;

/**
 * @var RouteCollection $routes
 */
if (isset($routes)) {
    $routes->get('storage/signed/(.+)', [SignedStorageController::class, 'download']);
}
