# Jengo Storage

Unified filesystem abstraction, asset management, universal signed URLs, and image processing pipeline for CodeIgniter 4 and the Jengo Framework.

Documentation: https://lipex-org.github.io/jengophp.com/packages/storage

## Installation

```bash
composer require jengo/storage
php spark storage:link
```

## Quick Start

```php
use Jengo\Storage\Storage;

// Standard file operations
Storage::put('avatars/user-1.jpg', $binaryData);
$contents = Storage::get('avatars/user-1.jpg');

// Multi-disk switching (Local, S3, Cloudflare R2, MinIO)
Storage::disk('s3')->put('backups/db.sql.gz', $backup);

// Temporary signed URLs (Local HMAC routes or native cloud pre-signed URLs)
$downloadUrl = Storage::temporaryUrl('vault/invoice.pdf', now()->addMinutes(15));

// Image pipeline: resize, crop, watermark, and convert to WebP
Storage::disk('public')
    ->image('photos/banner.png')
    ->fit(1200, 630)
    ->toWebp(85)
    ->save('photos/banner.webp');

// Zero-cost test doubles
Storage::fake('public');
Storage::disk('public')->assertMissing('photos/banner.webp');
```

## Documentation

For full documentation on multi-disk configuration, direct browser uploads, signed local routes, the image transformation pipeline, and testing fakes, visit https://lipex-org.github.io/jengophp.com/packages/storage.

## License

Released under the MIT License.
