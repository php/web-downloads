<?php
declare(strict_types=1);

namespace App\Actions;

use CurlHandle;
use CurlMultiHandle;
use InvalidArgumentException;
use RuntimeException;
use SensitiveParameter;
use Throwable;

class FetchArtifact
{
    private const int MIN_RANGE_SIZE = 2 * 1024 * 1024;

    public function __construct(private readonly int $maxConnections = 1)
    {
        if ($this->maxConnections < 1) {
            throw new InvalidArgumentException('The number of connections must be positive');
        }
    }

    public function handle($url, $filepath, #[SensitiveParameter] $token = null): void
    {
        if ($this->maxConnections > 1) {
            $size = $this->getRangeDownloadSize($url, $token);
            $connections = $size === null
                ? 1
                : min($this->maxConnections, (int) ceil($size / self::MIN_RANGE_SIZE));

            if ($size !== null && $connections > 1) {
                $this->downloadInParallel($url, $filepath, $token, $size, $connections);
                return;
            }
        }

        $this->download($url, $filepath, $token);
    }

    private function download($url, $filepath, #[SensitiveParameter] $token): void
    {
        $ch = $this->createHandle($url, $token);
        $fp = fopen($filepath, 'wb');

        if ($fp === false) {
            throw new RuntimeException('Failed to open the artifact destination');
        }

        curl_setopt($ch, CURLOPT_FILE, $fp);
        $result = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        fclose($fp);
        curl_close($ch);

        if ($result === false) {
            @unlink($filepath);
            throw new RuntimeException('cURL error: ' . $error);
        }

        if ($httpCode >= 400) {
            @unlink($filepath);
            throw new RuntimeException('HTTP error: ' . $httpCode);
        }
    }

    private function getRangeDownloadSize($url, #[SensitiveParameter] $token): ?int
    {
        $size = null;
        $received = 0;
        $ch = $this->createHandle($url, $token);

        curl_setopt_array($ch, [
            CURLOPT_RANGE => '0-0',
            CURLOPT_MAXFILESIZE_LARGE => 1,
            CURLOPT_WRITEFUNCTION => static function (
                CurlHandle $handle,
                string $data,
            ) use (&$received): int {
                $received += strlen($data);

                return $received <= 1 ? strlen($data) : 0;
            },
            CURLOPT_HEADERFUNCTION => static function (
                CurlHandle $handle,
                string $header,
            ) use (&$size): int {
                if (preg_match('/^Content-Range:\s*bytes\s+0-0\/(\d+)\s*$/i', trim($header), $matches)) {
                    $size = (int) $matches[1];
                }

                return strlen($header);
            },
        ]);

        $result = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $result !== false && $httpCode === 206 && $size > 0 ? $size : null;
    }

    private function downloadInParallel(
        $url,
        $filepath,
        #[SensitiveParameter] $token,
        int $size,
        int $connections,
    ): void {
        $destination = fopen($filepath, 'w+b');

        if ($destination === false) {
            throw new RuntimeException('Failed to open the artifact destination');
        }

        try {
            $allocated = ftruncate($destination, $size);
        } finally {
            fclose($destination);
        }

        if (!$allocated) {
            @unlink($filepath);
            throw new RuntimeException('Failed to allocate the artifact destination');
        }

        $multiHandle = curl_multi_init();
        $downloads = [];
        $chunkSize = (int) ceil($size / $connections);
        $failure = null;

        try {
            for ($index = 0; $index < $connections; $index++) {
                $start = $index * $chunkSize;
                $end = min($size - 1, $start + $chunkSize - 1);
                $expectedSize = $end - $start + 1;
                $stream = fopen($filepath, 'r+b');

                if ($stream === false || fseek($stream, $start) !== 0) {
                    if ($stream !== false) {
                        fclose($stream);
                    }

                    throw new RuntimeException('Failed to open an artifact range');
                }

                try {
                    $handle = $this->createHandle($url, $token);
                } catch (Throwable $throwable) {
                    fclose($stream);
                    throw $throwable;
                }

                curl_setopt_array($handle, [
                    CURLOPT_RANGE => $start . '-' . $end,
                    CURLOPT_FILE => $stream,
                    CURLOPT_BUFFERSIZE => 1024 * 1024,
                    CURLOPT_MAXFILESIZE_LARGE => $expectedSize,
                ]);

                $result = curl_multi_add_handle($multiHandle, $handle);
                if ($result !== CURLM_OK) {
                    fclose($stream);
                    curl_close($handle);
                    throw new RuntimeException('Failed to start an artifact range');
                }

                $downloads[] = [
                    'handle' => $handle,
                    'stream' => $stream,
                    'size' => $expectedSize,
                ];
            }

            $this->executeDownloads($multiHandle);

            foreach ($downloads as $download) {
                /** @var CurlHandle $handle */
                $handle = $download['handle'];
                $error = curl_error($handle);
                $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
                $downloaded = (int) curl_getinfo($handle, CURLINFO_SIZE_DOWNLOAD_T);

                if ($error !== '') {
                    throw new RuntimeException('cURL error: ' . $error);
                }

                if ($httpCode !== 206) {
                    throw new RuntimeException('HTTP range error: ' . $httpCode);
                }

                if ($downloaded !== $download['size']) {
                    throw new RuntimeException('Incomplete artifact range');
                }
            }
        } catch (Throwable $throwable) {
            $failure = $throwable;
        } finally {
            foreach ($downloads as $download) {
                curl_multi_remove_handle($multiHandle, $download['handle']);
                curl_close($download['handle']);
                fclose($download['stream']);
            }

            curl_multi_close($multiHandle);
        }

        if ($failure !== null) {
            @unlink($filepath);
            throw $failure;
        }
    }

    private function executeDownloads(CurlMultiHandle $multiHandle): void
    {
        do {
            $result = curl_multi_exec($multiHandle, $running);
            if ($result !== CURLM_OK) {
                throw new RuntimeException('Failed to download artifact ranges');
            }

            if ($running > 0 && curl_multi_select($multiHandle, 1.0) === -1) {
                usleep(1_000);
            }
        } while ($running > 0);
    }

    private function createHandle($url, #[SensitiveParameter] $token): CurlHandle
    {
        $ch = curl_init();
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL');
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        if (strcasecmp((string) parse_url((string) $url, PHP_URL_HOST), 'api.github.com') === 0) {
            $headers = [
                'Accept: application/vnd.github+json',
                'X-GitHub-Api-Version: 2022-11-28',
                'User-Agent: PHP Web Downloads',
            ];

            if ($token) {
                $headers[] = 'Authorization: token ' . $token;
            }

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        return $ch;
    }
}
