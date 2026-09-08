<?php

declare(strict_types=1);

namespace Jengo\Storage\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use Jengo\Storage\Config\Storage as StorageConfig;
use Jengo\Storage\Exceptions\InvalidSignatureException;
use Jengo\Storage\Security\FileSanitizer;
use Jengo\Storage\Security\HmacUrlSigner;
use Jengo\Storage\Storage;

class SignedStorageController extends Controller
{
    /**
     * Handle local private signed downloads with HTTP Range support.
     */
    public function download(...$segments): ResponseInterface
    {
        $path = implode('/', $segments);
        $cleanPath = FileSanitizer::sanitizePath($path);

        $expires = (int) ($this->request->getGet('expires') ?? 0);
        $signature = (string) ($this->request->getGet('signature') ?? '');

        /** @var StorageConfig $config */
        if (function_exists('service')) {
            $manager = service('storage');
            $config = $manager instanceof \Jengo\Storage\FilesystemManager
                ? $manager->getStorageConfig()
                : (config('Storage') ?? new StorageConfig());
        } else {
            $config = config('Storage') ?? new StorageConfig();
        }
        $signer = new HmacUrlSigner($config->signingKey ?? '', '/' . trim($config->signedRoutePrefix ?? 'storage/signed', '/'));

        $queryParams = $this->request->getGet();
        unset($queryParams['signature']);

        if (! $signer->isValid($cleanPath, $expires, $signature, $queryParams)) {
            if (time() > $expires) {
                return $this->response->setStatusCode(403)->setBody('Signed download URL has expired.');
            }
            return $this->response->setStatusCode(403)->setBody('Invalid URL signature.');
        }

        $diskName = $this->request->getGet('disk') ?? $config->default;
        $disk = Storage::disk($diskName);

        if (! $disk->exists($cleanPath)) {
            return $this->response->setStatusCode(404)->setBody('Requested file does not exist.');
        }

        $fileSize = $disk->size($cleanPath);
        $mimeType = $disk->mimeType($cleanPath) ?: 'application/octet-stream';
        $filename = basename($cleanPath);

        // Check for HTTP Range header
        $rangeHeader = $this->request->getHeaderLine('Range');

        if ($rangeHeader !== '' && preg_match('/bytes=(\d+)-(\d*)/', $rangeHeader, $matches)) {
            $start = (int) $matches[1];
            $end   = $matches[2] !== '' ? (int) $matches[2] : $fileSize - 1;

            if ($start > $end || $start >= $fileSize) {
                return $this->response
                    ->setStatusCode(416)
                    ->setHeader('Content-Range', "bytes */{$fileSize}");
            }

            $length = $end - $start + 1;
            $stream = $disk->readStream($cleanPath);

            if (is_resource($stream)) {
                fseek($stream, $start);
                $chunk = fread($stream, $length);
                fclose($stream);
            } else {
                $chunk = substr($disk->get($cleanPath), $start, $length);
            }

            return $this->response
                ->setStatusCode(206)
                ->setHeader('Content-Type', $mimeType)
                ->setHeader('Content-Range', "bytes {$start}-{$end}/{$fileSize}")
                ->setHeader('Content-Length', (string) $length)
                ->setHeader('Accept-Ranges', 'bytes')
                ->setBody((string) $chunk);
        }

        $stream = $disk->readStream($cleanPath);
        $contents = is_resource($stream) ? stream_get_contents($stream) : $disk->get($cleanPath);
        if (is_resource($stream)) {
            fclose($stream);
        }

        return $this->response
            ->setStatusCode(200)
            ->setHeader('Content-Type', $mimeType)
            ->setHeader('Content-Length', (string) $fileSize)
            ->setHeader('Accept-Ranges', 'bytes')
            ->setHeader('Content-Disposition', 'inline; filename="' . $filename . '"')
            ->setBody((string) $contents);
    }
}
