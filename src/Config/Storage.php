<?php

declare(strict_types=1);

namespace Jengo\Storage\Config;

use CodeIgniter\Config\BaseConfig;

class Storage extends BaseConfig
{
    /**
     * Default filesystem disk name.
     */
    public string $default = 'local';

    /**
     * Secret key used to generate and verify HMAC signed URLs for local downloads.
     * If left blank, falls back to the app encryption key.
     */
    public string $signingKey = '';

    /**
     * URI route prefix for local temporary signed downloads.
     */
    public string $signedRoutePrefix = 'storage/signed';

    /**
     * Preferred image manipulation driver ('gd' or 'imagick').
     */
    public string $imageDriver = 'gd';

    /**
     * Configured filesystem disks.
     */
    public array $disks = [
        'local' => [
            'driver'     => 'local',
            'root'       => WRITEPATH . 'storage/app',
            'visibility' => 'private',
            'throw'      => true,
        ],

        'public' => [
            'driver'     => 'local',
            'root'       => WRITEPATH . 'storage/app/public',
            'url'        => '/storage',
            'visibility' => 'public',
            'throw'      => true,
        ],

        's3' => [
            'driver'                  => 's3',
            'key'                     => '',
            'secret'                  => '',
            'region'                  => 'us-east-1',
            'bucket'                  => '',
            'url'                     => '',
            'endpoint'                => '',
            'use_path_style_endpoint' => false,
            'throw'                   => true,
        ],

        'r2' => [
            'driver'                  => 's3',
            'key'                     => '',
            'secret'                  => '',
            'region'                  => 'auto',
            'bucket'                  => '',
            'url'                     => '',
            'endpoint'                => '',
            'use_path_style_endpoint' => false,
            'throw'                   => true,
        ],

        'minio' => [
            'driver'                  => 's3',
            'key'                     => 'minioadmin',
            'secret'                  => 'minioadmin',
            'region'                  => 'us-east-1',
            'bucket'                  => 'local-bucket',
            'endpoint'                => 'http://127.0.0.1:9000',
            'use_path_style_endpoint' => true,
            'throw'                   => true,
        ],

        'memory' => [
            'driver' => 'memory',
            'url'    => '/storage/memory',
        ],
    ];
}
