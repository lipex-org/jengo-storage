<?php

declare(strict_types=1);

namespace Jengo\Storage\Drivers;

use Aws\S3\S3Client;
use Jengo\Storage\Contracts\FilesystemInterface;
use Jengo\Storage\Filesystem;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem as FlysystemOperator;

class S3Driver
{
    public static function create(array $config): FilesystemInterface
    {
        $clientConfig = [
            'version' => 'latest',
            'region'  => $config['region'] ?? 'us-east-1',
        ];

        if (isset($config['key'], $config['secret']) && $config['key'] !== '') {
            $clientConfig['credentials'] = [
                'key'    => $config['key'],
                'secret' => $config['secret'],
                'token'  => $config['token'] ?? null,
            ];
        }

        if (isset($config['endpoint']) && $config['endpoint'] !== '') {
            $clientConfig['endpoint'] = $config['endpoint'];
        }

        if (isset($config['use_path_style_endpoint'])) {
            $clientConfig['use_path_style_endpoint'] = (bool) $config['use_path_style_endpoint'];
        }

        $s3Client = new S3Client($clientConfig);
        $bucket = $config['bucket'] ?? '';
        $prefix = $config['prefix'] ?? '';

        $adapter = new AwsS3V3Adapter(
            client: $s3Client,
            bucket: $bucket,
            prefix: $prefix
        );

        $operator = new FlysystemOperator($adapter);

        return new Filesystem($operator, $config, null, $s3Client);
    }
}
