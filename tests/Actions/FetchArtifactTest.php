<?php
declare(strict_types=1);

namespace Actions;

use App\Actions\FetchArtifact;
use Override;
use PHPUnit\Framework\TestCase;

class MockFetchArtifact extends FetchArtifact {

    #[Override]
    public function handle($url, $filepath, $token = null): void
    {
        file_put_contents($filepath, $url . $token);
    }
}

class FetchArtifactTest extends TestCase {
    public function testHandleWithValidData() {
        $url = "https://example.com";
        $filepath = "test.txt";
        $token = "test_token";
        $fetchArtifact = new MockFetchArtifact();
        $fetchArtifact->handle($url, $filepath, $token);
        $this->assertFileExists($filepath);
        $this->assertEquals($url . $token, file_get_contents($filepath));
        unlink($filepath);
    }

    public function testParallelDownloadCombinesByteRanges(): void
    {
        $directory = sys_get_temp_dir() . '/fetch-artifact-' . uniqid();
        mkdir($directory, 0755, true);

        $source = $directory . '/source.zip';
        $destination = $directory . '/destination.zip';
        $requestLog = $directory . '/requests.log';
        $contents = str_repeat(hash('sha256', __METHOD__, true), 262145);
        file_put_contents($source, $contents);

        [$process, $pipes, $url] = $this->startRangeServer($source, $requestLog);

        try {
            (new FetchArtifact(4))->handle($url, $destination);

            $this->assertFileEquals($source, $destination);

            $requests = file($requestLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $this->assertIsArray($requests);
            $this->assertSame('bytes=0-0', $requests[0]);
            $this->assertCount(5, $requests);
        } finally {
            proc_terminate($process);

            foreach ($pipes as $pipe) {
                fclose($pipe);
            }

            proc_close($process);
            @unlink($source);
            @unlink($destination);
            @unlink($requestLog);
            @rmdir($directory);
        }
    }

    private function startRangeServer(string $source, string $requestLog): array
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        $this->assertIsResource($socket, $errorMessage);

        $address = stream_socket_get_name($socket, false);
        $this->assertIsString($address);
        fclose($socket);

        $port = (int) substr($address, strrpos($address, ':') + 1);
        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, __DIR__ . '/../Fixtures/range-server.php'],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            null,
            array_merge(getenv(), [
                'RANGE_SERVER_SOURCE' => $source,
                'RANGE_SERVER_LOG' => $requestLog,
            ]),
        );
        $this->assertIsResource($process);

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $connection = @fsockopen('127.0.0.1', $port);
            if ($connection !== false) {
                fclose($connection);
                return [$process, $pipes, 'http://127.0.0.1:' . $port . '/artifact.zip'];
            }

            usleep(10_000);
        }

        proc_terminate($process);
        $this->fail('Failed to start the byte-range test server');
    }
}
