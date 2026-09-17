<?php

declare(strict_types=1);

namespace Jengo\Storage\Testing;

use CodeIgniter\HTTP\Files\UploadedFile;

class FileFactory
{
    /**
     * Create a fake uploaded file with given size in kilobytes.
     */
    public static function create(string $name = 'test.txt', int $kilobytes = 10, string $mimeType = 'text/plain'): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'jengo_test_');
        $content = str_repeat('A', $kilobytes * 1024);
        file_put_contents($tempPath, $content);

        return new UploadedFile(
            path: $tempPath,
            originalName: $name,
            mimeType: $mimeType,
            size: strlen($content),
            error: UPLOAD_ERR_OK
        );
    }

    /**
     * Create a fake image file with real image headers using GD.
     */
    public static function image(string $name = 'photo.jpg', int $width = 100, int $height = 100, string $format = 'jpg'): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'jengo_img_');

        $im = imagecreatetruecolor(max(1, $width), max(1, $height));
        $bg = imagecolorallocate($im, 100, 150, 200);
        imagefilledrectangle($im, 0, 0, $width, $height, $bg);

        $mime = match (strtolower($format)) {
            'png'  => (function () use ($im, $tempPath) {
                imagepng($im, $tempPath);
                return 'image/png';
            })(),
            'webp' => (function () use ($im, $tempPath) {
                imagewebp($im, $tempPath);
                return 'image/webp';
            })(),
            default => (function () use ($im, $tempPath) {
                imagejpeg($im, $tempPath, 90);
                return 'image/jpeg';
            })(),
        };

        $size = filesize($tempPath);

        return new UploadedFile(
            path: $tempPath,
            originalName: $name,
            mimeType: $mime,
            size: $size ?: 100,
            error: UPLOAD_ERR_OK
        );
    }

    /**
     * Create a fake file with exact custom contents.
     */
    public static function createWithContent(string $name, string $content, string $mimeType = 'text/plain'): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'jengo_test_');
        file_put_contents($tempPath, $content);

        return new UploadedFile(
            path: $tempPath,
            originalName: $name,
            mimeType: $mimeType,
            size: strlen($content),
            error: UPLOAD_ERR_OK
        );
    }
}
