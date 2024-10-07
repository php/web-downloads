<?php

namespace App;

use DateTimeImmutable;
use Exception;
use ZipArchive;

class PhpHandler extends BaseHandler
{
    protected function validate(array $data): bool
    {
        $validator = new Validator([
            'url' => 'required|url',
            'token' => 'required|string',
        ]);

        $validator->validate($data);

        $valid = $validator->isValid();

        if(!$valid) {
            http_response_code(400);
            echo 'Invalid request: ' . $validator;
        }

        return $valid;
    }

    protected function execute(array $data): void
    {
        try {
            extract($data);
            $this->fetchPhpBuild($url, $token);
        } catch (Exception $exception) {
            http_response_code(500);
            echo 'Error: ' . $exception->getMessage();
        }
    }

    /**
     * @throws Exception
     */
    private function fetchPhpBuild(string $url, string $token): void
    {
        $hash = hash('sha256', $url) . strtotime('now');

        $filepath = "/tmp/php-" . $hash . ".tar.gz";

        FetchArtifact::handle($url, $filepath, $token);

        if(!file_exists($filepath) || mime_content_type($filepath) !== 'application/zip') {
            throw new Exception('Failed to fetch the PHP build');
        }

        $tempDirectory = "/tmp/php-" . $hash;

        if(is_dir($tempDirectory)) {
            rmdir($tempDirectory);
        }
        mkdir($tempDirectory, 0755, true);

        $zip = new ZipArchive();

        if ($zip->open($filepath) === TRUE) {
            if($zip->extractTo($tempDirectory) === FALSE) {
                throw new Exception('Failed to extract the extension build');
            }
            $zip->close();
        } else {
            throw new Exception('Failed to extract the extension');
        }

        unlink($filepath);

        $destinationDirectory = $this->getDestinationDirectory($tempDirectory);

        $this->moveBuild($tempDirectory, $destinationDirectory);

        $this->generateListing($destinationDirectory);
    }

    private function getDestinationDirectory(string $tempDirectory): string
    {
        $testPackFile = basename(glob($tempDirectory . '/php-test-pack-*.zip')[0]);
        $testPackFileName = str_replace('.zip', '', $testPackFile);
        $version = explode('-', $testPackFileName)[3];
        return getenv('BUILDS_DIRECTORY') . (preg_match('/^\d+\.\d+\.\d+$/', $version) ? '/releases' : '/qa');
    }

    /**
     * @throws Exception
     */
    private function moveBuild(string $tempDirectory, string $destinationDirectory): void
    {
        $files = glob($tempDirectory . '/*');
        if($files) {
            $version = $this->getFileVersion($files[0]);
            foreach ($files as $file) {
                $fileName = basename($file);
                $destination = $destinationDirectory . '/' . $fileName;
                rename($file, $destination);
            }
            rmdir($tempDirectory);
            $this->copyBuildsToArchive($destinationDirectory, $version);
        } else {
            throw new Exception('No builds found in the artifact');
        }
    }

    private function copyBuildsToArchive(string $directory, string $version): void
    {
        $version_short = substr($version, 0, 3);
        $files = glob($directory . '/php*-' . $version_short . '-*.zip');
        foreach ($files as $file) {
            $fileVersion = $this->getFileVersion($file);
            if($fileVersion) {
                copy($directory . '/' . basename($file), $directory . '/archive/' . $file);
                if(version_compare($fileVersion, $version) < 0) {
                    unlink($file);
                }
            }
        }
    }

    private function getFileVersion(string $file): string
    {
        $file = preg_replace('/^php-((debug|devel|test)-pack)?/', '', $file);
        return explode('-', $file)[0];
    }

    /**
     * @throws Exception
     */
    private function generateListing(string $directory): void
    {
        $builds = glob($directory . '/php-[678].*[0-9]-latest.zip');
        if (empty($builds)) {
            $builds = glob($directory . '/php-[678].*[0-9].zip');
        }

        $releases = [];
        $sha256sums = $this->getSha256Sums($directory);
        foreach ($builds as $file) {
            $file_ori = $file;
            $mtime = date('Y-M-d H:i:s', filemtime($file));

            $parts = $this->parseFileName(basename($file));
            $key = ($parts['nts'] ? 'nts-' : 'ts-') . $parts['vc'] . '-' . $parts['arch'];
            $version_short = $parts['version_short'];
            if (!isset($releases['version'])) {
                $releases[$version_short]['version'] = $parts['version'];
            }
            $releases[$version_short][$key]['mtime'] = $mtime;
            $releases[$version_short][$key]['zip'] = [
                'path' => $file_ori,
                'size' => $this->bytes2string(filesize($file_ori)),
                'sha256' => $sha256sums[strtolower($file_ori)]
            ];
            $namingPattern = $parts['version'] . ($parts['nts'] ? '-' . $parts['nts'] : '') . '-Win32-' . $parts['vc'] . '-' . $parts['arch'] . ($parts['ts'] ? '-' . $parts['ts'] : '');
            $build_types = [
                'source' => 'php-' . $parts['version'] . '-src.zip',
                'debug_pack' => 'php-debug-pack-' . $namingPattern . '.zip',
                'devel_pack' => 'php-devel-pack-' . $namingPattern . '.zip',
                'installer' => 'php-' . $namingPattern . '.msi',
                'test_pack' => 'php-test-pack-' . $parts['version'] . '.zip',
            ];
            foreach($build_types as $type => $fileName) {
                $filePath = $directory . '/' . $fileName;
                if (file_exists($filePath)) {
                    $releases[$version_short][$type] = [
                        'path' => $fileName,
                        'size' => $this->bytes2string(filesize($filePath))
                    ];
                }
            }
        }

        $this->updateReleasesJson($releases, $directory);
        if($directory === getenv('BUILDS_DIRECTORY') . '/releases') {
            $this->updateLatestBuilds($releases, $directory);
        }
    }

    /**
     * @throws Exception
     */
    private function updateReleasesJson(array $releases, string $directory): void
    {
        foreach ($releases as &$release) {
            foreach ($release as &$build_type) {
                if (! is_array($build_type) || ! isset($build_type['mtime'])) {
                    continue;
                }

                try {
                    $date = new DateTimeImmutable($build_type['mtime']);
                    $build_type['mtime'] = $date->format('c');
                } catch (Exception $exception) {
                    throw new Exception('Failed to generate releases.json: ' . $exception->getMessage());
                }
            }
            unset($build_type);
        }
        unset($release);

        file_put_contents(
            $directory . '/releases.json',
            json_encode($releases, JSON_PRETTY_PRINT)
        );
    }

    private function updateLatestBuilds($releases, $directory): void
    {
        foreach ($releases as $versionShort => $release) {
            $latestFileName = str_replace($release['version'], $versionShort, $release['path']);
            $latestFileName = str_replace('.zip', '-latest.zip', $latestFileName);
            copy($directory . '/' . $release['path'], $directory . '/latest/' . $latestFileName);
        }
    }

    private function getSha256Sums($directory): array
    {
        $result = [];
        $sha_file = fopen("$directory/sha256sum.txt", 'w');
        foreach (scandir($directory) as $filename) {
            if (pathinfo($filename, PATHINFO_EXTENSION) !== 'zip') {
                continue;
            }
            $sha256 = hash_file('sha256', "$directory/$filename");
            fwrite($sha_file, "$sha256 *$filename\n");
            $result[strtolower(basename($filename))] = $sha256;
        }
        fclose($sha_file);
        return $result;
    }

    private function bytes2string(int $size): float
    {
        $sizes = ['YB', 'ZB', 'EB', 'PB', 'TB', 'GB', 'MB', 'kB', 'B'];

        $total = count($sizes);

        while($total-- && $size > 1024) $size /= 1024;

        return round($size, 2) . $sizes[$total];
    }

    private function parseFileName($fileName): array
    {
        $fileName = str_replace(['-Win32', '.zip'], ['', ''], $fileName);

        $parts = explode('-', $fileName);
        if (is_numeric($parts[2]) || $parts[2] == 'dev') {
            $version = $parts[1] . '-' . $parts[2];
            $nts = $parts[3] == 'nts' ? 'nts' : false;
            if ($nts) {
                $vc = $parts[4];
                $arch = $parts[5];
            } else {
                $vc = $parts[3];
                $arch = $parts[4];
            }
        } elseif ($parts[2] == 'nts') {
            $nts = 'nts';
            $version = $parts[1];
            $vc = $parts[3];
            $arch = $parts[4];
        } else {
            $nts = false;
            $version = $parts[1];
            $vc = $parts[2];
            $arch = $parts[3];
        }
        if (is_numeric($vc)) {
            $vc = 'VC6';
            $arch = 'x86';
        }
        $t = count($parts) - 1;
        $ts = is_numeric($parts[$t]) ? $parts[$t] : false;

        return [
            'version'  => $version,
            'version_short'  => substr($version, 0, 3),
            'nts'      => $nts,
            'vc'       => $vc,
            'arch'     => $arch,
            'ts'       => $ts
        ];
    }
}