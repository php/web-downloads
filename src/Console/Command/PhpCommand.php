<?php
declare(strict_types=1);

namespace App\Console\Command;

use App\Actions\GetListing;
use App\Actions\UpdateReleasesJson;
use App\Console\Command;
use App\Helpers\Helpers;
use Exception;
use ZipArchive;

class PhpCommand extends Command
{
    protected string $signature = 'php:add --base-directory= --builds-directory=';
    protected string $description = 'Add php builds';

    protected ?string $baseDirectory = null;

    public function __construct(
        protected GetListing $generateListing,
        protected UpdateReleasesJson $updateReleasesJson,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $this->baseDirectory = $this->getOption('base-directory');
            if (!$this->baseDirectory) {
                throw new Exception('Base directory is required');
            }

            $buildsDirectory = $this->getOption('builds-directory');
            if (!$buildsDirectory) {
                throw new Exception('Build directory is required');
            }

            $zips_directory = $buildsDirectory . '/php';
            if(!is_dir($zips_directory)) {
                return Command::SUCCESS;
            }

            $files = glob($zips_directory . '/*.zip');

            // We lock the files we are working on
            // so that we don't process them again if the command is run again
            $filteredFiles = [];
            foreach ($files as $filepath) {
                $lockFile = $filepath . '.lock';
                if (!file_exists($lockFile)) {
                    touch($lockFile);
                    $filteredFiles[] = $filepath;
                }
            }

            foreach ($filteredFiles as $filepath) {
                $hash = hash('sha256', $filepath) . uniqid('', true);
                $tempDirectory = "/tmp/php-" . $hash;

                if (is_dir($tempDirectory)) {
                    Helpers::rmdirr($tempDirectory);
                }
                mkdir($tempDirectory, 0755, true);

                $zip = new ZipArchive();
                if ($zip->open($filepath) === TRUE) {
                    if ($zip->extractTo($tempDirectory) === FALSE) {
                        throw new Exception('Failed to extract the php build');
                    }
                    $zip->close();
                } else {
                    throw new Exception('Failed to extract the php build');
                }

                unlink($filepath);

                $destinationDirectory = $this->getDestinationDirectory($tempDirectory);
                $version = $this->getBuildVersion($tempDirectory);

                $this->moveBuild($tempDirectory, $destinationDirectory);

                $releases = $this->generateListing->handle($destinationDirectory);

                $this->updateReleasesJson->handle($releases, $destinationDirectory);
                if ($destinationDirectory === $this->baseDirectory . '/releases') {
                    $this->updateLatestBuilds($releases, $destinationDirectory);
                    $parts = explode('.', $version);
                    $this->promoteSeriesFiles($parts[0] . '.' . $parts[1]);
                }

                Helpers::rmdirr($tempDirectory);

                unlink($filepath . '.lock');
            }
            return Command::SUCCESS;
        } catch (Exception $e) {
            echo $e->getMessage();
            $tempDirectories = glob('/tmp/php-*');
            if($tempDirectories) {
                foreach ($tempDirectories as $tempDirectory) {
                    Helpers::rmdirr($tempDirectory);
                }
            }
            return Command::FAILURE;
        }
    }

    /**
     * @throws Exception
     */
    private function getDestinationDirectory(string $tempDirectory): string
    {
        $version = $this->getBuildVersion($tempDirectory);
        return $this->baseDirectory . (preg_match('/^\d+\.\d+\.\d+$/', $version) ? '/releases' : '/qa');
    }

    /**
     * @throws Exception
     */
    private function getBuildVersion(string $tempDirectory): string
    {
        $testPackFiles = glob($tempDirectory . '/php-test-pack-*.zip');
        if(empty($testPackFiles)) {
            throw new Exception('No test pack found in the artifact');
        }
        $testPackFile = basename($testPackFiles[0]);
        if (!preg_match('/^php-test-pack-(.+)\.zip$/', $testPackFile, $matches)) {
            throw new Exception('No test pack found in the artifact');
        }
        return $matches[1];
    }

    /**
     * @throws Exception
     */
    private function moveBuild(string $tempDirectory, string $destinationDirectory): void
    {
        $files = glob($tempDirectory . '/*');
        if ($files) {
            $version = $this->getFileVersion($files[0]);
            foreach ($files as $file) {
                $fileName = basename($file);
                $destination = $destinationDirectory . '/' . $fileName;
                rename($file, $destination);
            }
            Helpers::rmdirr($tempDirectory);
            $this->copyBuildsToArchive($destinationDirectory, $version);
        } else {
            throw new Exception('No builds found in the artifact');
        }
    }

    private function copyBuildsToArchive(string $directory, string $version): void
    {
        $version_short = substr($version, 0, 3);
        $files = glob($directory . '/php*' . $version_short . '*.zip');
        if(!is_dir($directory . '/archives')) {
            mkdir($directory . '/archives', 0755, true);
        }
        foreach ($files as $file) {
            $fileVersion = $this->getFileVersion($file);
            if ($fileVersion) {
                copy($directory . '/' . basename($file), $directory . '/archives/' . basename($file));
                if (version_compare($fileVersion, $version) < 0) {
                    unlink($file);
                }
            }
        }
    }

    private function getFileVersion(string $file): string
    {
        $file = basename($file);
        if(preg_match('/^php-((debug|devel|test)-pack-).*/', $file)) {
            $pattern = '/^php-((debug|devel|test)-pack-)?/';
        } else {
            $pattern = '/php-/';
        }
        $file = preg_replace($pattern, '', $file);
        $parts = explode('-', $file);
        return str_replace('.zip', '', $parts[0]);
    }

    /**
     * @throws Exception
     */
    private function promoteSeriesFiles(string $phpVersion): void
    {
        $vsVersions = json_decode(
            file_get_contents(dirname(__DIR__, 3) . '/config/vs.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        if (!isset($vsVersions[$phpVersion])) {
            throw new Exception('No VS version found for PHP ' . $phpVersion);
        }
        $vsVersion = $vsVersions[$phpVersion];

        $baseDirectory = $this->baseDirectory . "/php-sdk/deps/series";

        if (!is_dir($baseDirectory)) {
            mkdir($baseDirectory, 0755, true);
        }
        foreach(['x86', 'x64'] as $arch) {
            $sourceFile = $baseDirectory . '/packages-' . $phpVersion . '-' . $vsVersion . '-' . $arch . '-staging.txt';
            $destinationFile = $baseDirectory . '/packages-' . $phpVersion . '-' . $vsVersion . '-' . $arch . '-stable.txt';
            if(!file_exists($sourceFile)) {
                throw new Exception($sourceFile . ' does not exist');
            }
            copy($sourceFile, $destinationFile);
        }
    }

    private function updateLatestBuilds($releases, $directory): void
    {
        if(!is_dir($directory . '/latest')) {
            mkdir($directory . '/latest', 0755, true);
        }
        foreach ($releases as $versionShort => $release) {
            array_walk_recursive($release, function ($value, $key) use($directory, $versionShort, $release) {
                if ($key === 'path') {
                    $filePath = basename($value);
                    $latestFileName = str_replace($release['version'], $versionShort, $filePath);
                    $latestFileName = str_replace('.zip', '-latest.zip', $latestFileName);
                    copy($directory . '/' . $filePath, $directory . '/latest/' . $latestFileName);
                }
            });
        }
    }
}
