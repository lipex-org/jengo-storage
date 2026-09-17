<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Storage\Config\Storage as StorageConfig;
use Jengo\Storage\Controllers\ChunkUploadController;
use Jengo\Storage\Storage;

class ChunkUploadTest extends CIUnitTestCase
{
    protected string $fileUuid = 'test-uuid-upload-999';

    protected function setUp(): void
    {
        parent::setUp();
        $config = new StorageConfig();
        $config->default = 'memory';
        Services::injectMock('storage', new \Jengo\Storage\FilesystemManager($config));
    }

    protected function tearDown(): void
    {
        $dir = WRITEPATH . 'storage/temp/chunks/' . $this->fileUuid;
        if (is_dir($dir)) {
            $files = scandir($dir) ?: [];
            foreach ($files as $f) {
                if ($f !== '.' && $f !== '..') {
                    @unlink($dir . '/' . $f);
                }
            }
            @rmdir($dir);
        }

        Storage::clearResolvedInstances();
        Services::reset();
        parent::tearDown();
    }

    public function test_uploads_chunks_and_assembles_file_successfully(): void
    {
        $fakeDisk = Storage::fake('memory');

        $chunk1 = 'First chunk content. ';
        $chunk2 = 'Second chunk content.';
        $fullContent = $chunk1 . $chunk2;
        $expectedChecksum = hash('sha256', $fullContent);

        // 1. Upload Chunk 0
        $req0 = $this->createRequest([
            'file_uuid'    => $this->fileUuid,
            'chunk_index'  => 0,
            'total_chunks' => 2,
        ], $chunk1);

        $controller = new ChunkUploadController();
        $controller->initController($req0, new Response(new \Config\App()), Services::logger());
        $res0 = $controller->upload();

        $this->assertSame(200, $res0->getStatusCode());
        $data0 = json_decode($res0->getBody(), true);
        $this->assertSame('success', $data0['status']);
        $this->assertSame([0], $data0['received_chunks']);

        // 2. Upload Chunk 1
        $req1 = $this->createRequest([
            'file_uuid'    => $this->fileUuid,
            'chunk_index'  => 1,
            'total_chunks' => 2,
        ], $chunk2);

        $controller->initController($req1, new Response(new \Config\App()), Services::logger());
        $res1 = $controller->upload();

        $this->assertSame(200, $res1->getStatusCode());
        $data1 = json_decode($res1->getBody(), true);
        $this->assertSame([0, 1], $data1['received_chunks']);

        // 3. Assemble chunks
        $reqAssemble = $this->createRequest([
            'file_uuid'         => $this->fileUuid,
            'total_chunks'      => 2,
            'filename'          => 'large_document.txt',
            'disk'              => 'memory',
            'folder'            => 'assembled',
            'expected_checksum' => $expectedChecksum,
        ]);

        $controller->initController($reqAssemble, new Response(new \Config\App()), Services::logger());
        $resAssemble = $controller->assemble();

        $this->assertSame(200, $resAssemble->getStatusCode());
        $assembleData = json_decode($resAssemble->getBody(), true);
        $this->assertSame('success', $assembleData['status']);
        $this->assertSame(strlen($fullContent), $assembleData['size']);
        $this->assertSame($expectedChecksum, $assembleData['checksum']);

        // Verify stored file on disk
        $this->assertTrue($fakeDisk->exists($assembleData['path']));
        $this->assertSame($fullContent, $fakeDisk->get($assembleData['path']));

        // Verify temporary staging directory was cleaned up
        $this->assertDirectoryDoesNotExist(WRITEPATH . 'storage/temp/chunks/' . $this->fileUuid);
    }

    public function test_assembly_fails_when_chunks_are_missing(): void
    {
        Storage::fake('memory');

        // Only upload chunk 0
        $req0 = $this->createRequest([
            'file_uuid'    => $this->fileUuid,
            'chunk_index'  => 0,
            'total_chunks' => 2,
        ], 'Partial data');

        $controller = new ChunkUploadController();
        $controller->initController($req0, new Response(new \Config\App()), Services::logger());
        $controller->upload();

        // Attempt assembly expecting 2 chunks
        $reqAssemble = $this->createRequest([
            'file_uuid'    => $this->fileUuid,
            'total_chunks' => 2,
            'filename'     => 'test.txt',
            'disk'         => 'memory',
        ]);

        $controller->initController($reqAssemble, new Response(new \Config\App()), Services::logger());
        $res = $controller->assemble();

        $this->assertSame(400, $res->getStatusCode());
        $data = json_decode($res->getBody(), true);
        $this->assertSame([1], $data['missing_chunks']);
    }

    public function test_abort_cleans_up_staged_chunks(): void
    {
        $req0 = $this->createRequest([
            'file_uuid'    => $this->fileUuid,
            'chunk_index'  => 0,
            'total_chunks' => 2,
        ], 'Abort data');

        $controller = new ChunkUploadController();
        $controller->initController($req0, new Response(new \Config\App()), Services::logger());
        $controller->upload();

        $chunkDir = WRITEPATH . 'storage/temp/chunks/' . $this->fileUuid;
        $this->assertDirectoryExists($chunkDir);

        $reqAbort = $this->createRequest([
            'file_uuid' => $this->fileUuid,
        ]);

        $controller->initController($reqAbort, new Response(new \Config\App()), Services::logger());
        $res = $controller->abort();

        $this->assertSame(200, $res->getStatusCode());
        $this->assertDirectoryDoesNotExist($chunkDir);
    }

    protected function createRequest(array $postData, ?string $body = null): IncomingRequest
    {
        $request = new IncomingRequest(
            new \Config\App(),
            new URI('http://localhost/storage/chunks/upload'),
            $body,
            new UserAgent()
        );

        $request->setMethod('POST');
        $request->setGlobal('post', $postData);

        return $request;
    }
}
