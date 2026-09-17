# Jengo Storage

Unified filesystem abstraction, asset management, universal signed URLs, chunked resumable uploads, and image processing pipeline for CodeIgniter 4 and the Jengo Framework.

Documentation: https://lipex-org.github.io/jengophp.com/packages/storage

## Features

- Multi-Disk Filesystem Abstraction: Seamlessly switch between Local, AWS S3, Cloudflare R2, MinIO, and In-Memory virtual disks.
- Universal Temporary Signed URLs: HMAC-SHA256 expiring links for local private files and native pre-signed URLs for cloud object storage.
- Chunked Multipart File Uploads: Built-in controllers (`/storage/chunks/upload`, `/storage/chunks/assemble`, `/storage/chunks/abort`) with low-memory 64 KB buffered stream concatenation (< 2 MB RAM) and SHA-256 integrity verification.
- Companion Client Package: `@jengo/storage` universal TypeScript client featuring concurrency streaming, pause/resume/abort, instant thumbnail previews, and adapters for React, Vue 3, and Svelte.
- Direct-to-Cloud Pre-Signed Uploads: Direct browser-to-bucket transfers bypassing PHP worker processes.
- Fluent Image Transformation Engine: Aspect-ratio resizing, cropping, fitting, watermarking, and modern format transcoding (WebP/AVIF) via GD or Imagick.
- Zero-Cost Test Doubles: `Storage::fake()` with rich assertions (`assertExists`, `assertMissing`, `assertSize`, `assertChecksum`).
- Spark CLI Management: `php spark storage:link` and `php spark storage:cleanup`.

## Installation

```bash
composer require jengo/storage
php spark storage:link
```

Optional companion client package:
```bash
npm install @jengo/storage
```

## Quick Start

### Standard Backend File Operations

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

### Chunked Uploads with `@jengo/storage`

```typescript
import { ChunkedUploader, createFilePreview } from '@jengo/storage';

// 1. Instant preview before network transmission
const preview = await createFilePreview(file);

// 2. Stream chunks concurrently with pause/resume and integrity verification
const uploader = new ChunkedUploader(file, {
    chunkSize: 2 * 1024 * 1024, // 2 MB parts
    concurrency: 3,             // 3 concurrent network streams
    disk: 'public',
    computeChecksums: true,
    onProgress: (p) => console.log(`${p.percent}% | ${p.speed} | ETA: ${p.remainingSeconds}s`),
    onSuccess: (res) => console.log('File assembled:', res.url),
});

await uploader.start();
```

## Documentation

For full documentation on multi-disk configuration, direct browser uploads, signed local routes, the image transformation pipeline, and testing fakes, visit https://lipex-org.github.io/jengophp.com/packages/storage.

## License

Released under the MIT License.

