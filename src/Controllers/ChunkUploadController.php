<?php

declare(strict_types=1);

namespace Jengo\Storage\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use Jengo\Storage\Security\FileSanitizer;
use Jengo\Storage\Storage;
use Throwable;

class ChunkUploadController extends Controller
{
    /**
     * Directory path where temporary chunk parts are staged.
     */
    protected function getChunkDir(string $uuid): string
    {
        $cleanUuid = preg_replace('/[^a-zA-Z0-9_-]/', '', $uuid);
        return WRITEPATH . 'storage/temp/chunks/' . $cleanUuid;
    }

    /**
     * Handle receiving an individual chunk part.
     */
    public function upload(): ResponseInterface
    {
        $uuid        = (string) ($this->request->getPost('file_uuid') ?? $this->request->getHeaderLine('X-File-Id'));
        $chunkIndex  = (int) ($this->request->getPost('chunk_index') ?? $this->request->getHeaderLine('X-Chunk-Index'));
        $totalChunks = (int) ($this->request->getPost('total_chunks') ?? $this->request->getHeaderLine('X-Total-Chunks'));
        $chunkChecksum = (string) ($this->request->getPost('chunk_checksum') ?? $this->request->getHeaderLine('X-Chunk-Checksum'));

        if ($uuid === '' || $chunkIndex < 0 || $totalChunks < 1) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'Invalid chunk upload parameters (file_uuid, chunk_index, total_chunks required).',
            ])->setStatusCode(400);
        }

        $chunkDir = $this->getChunkDir($uuid);
        if (! is_dir($chunkDir) && ! mkdir($chunkDir, 0755, true) && ! is_dir($chunkDir)) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'Failed to create temporary chunk staging directory.',
            ])->setStatusCode(500);
        }

        $chunkPath = $chunkDir . '/' . $chunkIndex . '.part';

        // Extract chunk contents: check uploaded file first, then raw input body
        $file = $this->request->getFile('chunk');
        if ($file && $file->isValid()) {
            if (! move_uploaded_file($file->getTempName(), $chunkPath)) {
                // Fallback to copy if move_uploaded_file cannot cross mount points
                copy($file->getTempName(), $chunkPath);
                @unlink($file->getTempName());
            }
        } else {
            $rawBody = $this->request->getBody();
            if ($rawBody === null || $rawBody === '') {
                return $this->response->setJSON([
                    'status'  => 'error',
                    'message' => 'No chunk content received.',
                ])->setStatusCode(400);
            }
            file_put_contents($chunkPath, $rawBody);
        }

        if (! is_file($chunkPath) || filesize($chunkPath) === 0) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'Empty or corrupted chunk part written.',
            ])->setStatusCode(400);
        }

        // Verify checksum if provided by client
        if ($chunkChecksum !== '') {
            $computedHash = hash_file('sha256', $chunkPath);
            if (! hash_equals($chunkChecksum, (string) $computedHash)) {
                @unlink($chunkPath);
                return $this->response->setJSON([
                    'status'  => 'error',
                    'message' => 'Chunk checksum verification failed. Please retry.',
                ])->setStatusCode(422);
            }
        }

        // Find list of currently received chunk indexes
        $receivedChunks = [];
        $files = scandir($chunkDir) ?: [];
        foreach ($files as $entry) {
            if (str_ends_with($entry, '.part')) {
                $idx = (int) pathinfo($entry, PATHINFO_FILENAME);
                $receivedChunks[] = $idx;
            }
        }
        sort($receivedChunks);

        return $this->response->setJSON([
            'status'          => 'success',
            'file_uuid'       => $uuid,
            'chunk_index'     => $chunkIndex,
            'total_chunks'    => $totalChunks,
            'received_count'  => count($receivedChunks),
            'received_chunks' => $receivedChunks,
        ]);
    }

    /**
     * Assemble all received chunk parts into the final file on the target disk.
     */
    public function assemble(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];

        $uuid             = (string) ($this->request->getPost('file_uuid') ?? $json['file_uuid'] ?? '');
        $totalChunks      = (int) ($this->request->getPost('total_chunks') ?? $json['total_chunks'] ?? 0);
        $rawFilename      = (string) ($this->request->getPost('filename') ?? $json['filename'] ?? 'file_' . time());
        $targetDisk       = (string) ($this->request->getPost('disk') ?? $json['disk'] ?? 'public');
        $folder           = trim((string) ($this->request->getPost('folder') ?? $json['folder'] ?? 'uploads'), '/');
        $expectedChecksum = (string) ($this->request->getPost('expected_checksum') ?? $json['expected_checksum'] ?? '');

        if ($uuid === '' || $totalChunks < 1) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'file_uuid and total_chunks parameters are required for assembly.',
            ])->setStatusCode(400);
        }

        $chunkDir = $this->getChunkDir($uuid);
        if (! is_dir($chunkDir)) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'Chunk upload session not found or already assembled.',
            ])->setStatusCode(404);
        }

        // Verify all chunks exist
        $missingChunks = [];
        for ($i = 0; $i < $totalChunks; $i++) {
            $part = $chunkDir . '/' . $i . '.part';
            if (! is_file($part)) {
                $missingChunks[] = $i;
            }
        }

        if (! empty($missingChunks)) {
            return $this->response->setJSON([
                'status'         => 'error',
                'message'        => 'Cannot assemble file: missing chunks.',
                'missing_chunks' => $missingChunks,
            ])->setStatusCode(400);
        }

        // Create a temporary stream to concatenate all chunks
        $tempMerged = tempnam(sys_get_temp_dir(), 'jengo_chunk_');
        $outStream = fopen($tempMerged, 'wb');

        if ($outStream === false) {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'Failed to open temporary stream for assembly.',
            ])->setStatusCode(500);
        }

        for ($i = 0; $i < $totalChunks; $i++) {
            $partPath = $chunkDir . '/' . $i . '.part';
            $inStream = fopen($partPath, 'rb');
            if ($inStream !== false) {
                stream_copy_to_stream($inStream, $outStream);
                fclose($inStream);
            }
        }

        fclose($outStream);

        // Verify final file checksum if provided
        if ($expectedChecksum !== '') {
            $actualHash = hash_file('sha256', $tempMerged);
            if (! hash_equals($expectedChecksum, (string) $actualHash)) {
                @unlink($tempMerged);
                return $this->response->setJSON([
                    'status'  => 'error',
                    'message' => 'Assembled file checksum verification failed.',
                ])->setStatusCode(422);
            }
        }

        // Sanitize filename and construct destination path
        $safeName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($rawFilename));
        $uniquePrefix = time() . '_' . substr(md5(uniqid('', true)), 0, 8);
        $destination = ($folder !== '' ? $folder . '/' : '') . $uniquePrefix . '_' . $safeName;

        try {
            $disk = Storage::disk($targetDisk);
            $readStream = fopen($tempMerged, 'rb');

            if ($readStream === false) {
                @unlink($tempMerged);
                return $this->response->setJSON([
                    'status'  => 'error',
                    'message' => 'Failed to read assembled file.',
                ])->setStatusCode(500);
            }

            $disk->writeStream($destination, $readStream);
            if (is_resource($readStream)) {
                fclose($readStream);
            }

            @unlink($tempMerged);

            // Clean up chunk parts directory
            $this->removeDirectory($chunkDir);

            $fileSize = $disk->size($destination);
            $mimeType = $disk->mimeType($destination) ?: 'application/octet-stream';
            $checksum = $disk->checksum($destination, ['algo' => 'sha256']);

            $fileUrl = $targetDisk === 'public'
                ? $disk->url($destination)
                : $disk->temporaryUrl($destination, time() + 3600, ['disk' => $targetDisk]);

            return $this->response->setJSON([
                'status'    => 'success',
                'disk'      => $targetDisk,
                'path'      => $destination,
                'filename'  => $safeName,
                'url'       => $fileUrl,
                'size'      => $fileSize,
                'mime'      => $mimeType,
                'checksum'  => $checksum,
            ]);
        } catch (Throwable $e) {
            @unlink($tempMerged);
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'Failed to write assembled file to storage: ' . $e->getMessage(),
            ])->setStatusCode(500);
        }
    }

    /**
     * Abort and clean up an in-progress chunk upload session.
     */
    public function abort(): ResponseInterface
    {
        $json = $this->request->getJSON(true) ?? [];
        $uuid = (string) ($this->request->getPost('file_uuid') ?? $json['file_uuid'] ?? '');

        if ($uuid === '') {
            return $this->response->setJSON([
                'status'  => 'error',
                'message' => 'file_uuid parameter required.',
            ])->setStatusCode(400);
        }

        $chunkDir = $this->getChunkDir($uuid);
        if (is_dir($chunkDir)) {
            $this->removeDirectory($chunkDir);
        }

        return $this->response->setJSON([
            'status'  => 'success',
            'message' => "Upload session [{$uuid}] aborted and cleaned up.",
        ]);
    }

    /**
     * Helper to recursively delete a directory and its contents.
     */
    protected function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
