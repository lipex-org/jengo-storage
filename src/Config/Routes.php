<?php

declare(strict_types=1);

namespace Jengo\Storage\Config;

use CodeIgniter\Router\RouteCollection;
use Jengo\Storage\Controllers\ChunkUploadController;
use Jengo\Storage\Controllers\SignedStorageController;

/**
 * @var RouteCollection $routes
 */
if (isset($routes)) {
    $routes->get('storage/signed/(.+)', [SignedStorageController::class, 'download']);
    $routes->post('storage/chunks/upload', [ChunkUploadController::class, 'upload']);
    $routes->post('storage/chunks/assemble', [ChunkUploadController::class, 'assemble']);
    $routes->post('storage/chunks/abort', [ChunkUploadController::class, 'abort']);
}

