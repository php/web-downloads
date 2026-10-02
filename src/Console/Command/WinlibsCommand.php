<?php
declare(strict_types=1);

namespace App\Console\Command;

use App\Console\Command;
use App\Helpers\Helpers;
use Exception;
use JsonException;
use Throwable;

class WinlibsCommand extends Command
{
    public string $signature = 'winlibs:add --base-directory= --builds-directory=';
    public string $description = 'Add winlibs dependencies';

    protected ?string $baseDirectory = null;

    public function handle(): int
    {
        try {
            if (array_diff(array_keys($this->options), ['base-directory', 'builds-directory']) !== []) {
                throw new Exception('Unsupported option for winlibs:add');
            }

            $this->baseDirectory = $this->options['base-directory'] ?? null;
            if (!$this->baseDirectory) {
                throw new Exception('Base directory is required');
            }

            $buildsDirectory = $this->options['builds-directory'] ?? null;
            if (!$buildsDirectory) {
                throw new Exception('Build directory is required');
            }

            $buildDirectories = glob($buildsDirectory . '/winlibs/*', GLOB_ONLYDIR);
            if ($buildDirectories === false) {
                throw new Exception('Unable to list Winlibs jobs');
            }

            $errors = [];

            foreach ($buildDirectories as $directoryPath) {
                if (str_starts_with(basename($directoryPath), 'delete-')) {
                    continue;
                }

                $lockFile = $directoryPath . '.lock';
                if (file_exists($lockFile)) {
                    continue;
                }
                if (!touch($lockFile)) {
                    $errors[] = 'Unable to lock Winlibs job: ' . basename($directoryPath);
                    continue;
                }

                try {
                    $data = json_decode((string) file_get_contents($directoryPath . '/data.json'), true, 512, JSON_THROW_ON_ERROR);
                    $files = glob($directoryPath . '/*.zip');
                    $files = $this->parseFiles($files);
                    if (empty($files)) {
                        throw new Exception('No valid files found in ' . basename($directoryPath));
                    }
                    if ($data['type'] === 'php') {
                        $this->copyPhpFiles($files, $data['library'], $data['vs_version_targets']);
                        $updateSeries = $data['update_series'] ?? 'true';
                        if ($updateSeries === 'true') {
                            $this->updatePhpSeriesFiles(
                                $files,
                                $data['library'],
                                $data['php_versions'],
                                $data['vs_version_targets'],
                                $data['stability']
                            );
                        }
                    } else {
                        $this->copyPeclFiles($files, $data['library']);
                        $this->updatePackagesFile();
                    }

                    if (!Helpers::rmdirr($directoryPath)) {
                        throw new Exception('Unable to remove Winlibs job: ' . basename($directoryPath));
                    }
                    unlink($lockFile);
                } catch (Throwable $error) {
                    $errors[] = $error->getMessage();
                }
            }

            if ($errors !== []) {
                throw new Exception(implode("\n", $errors));
            }
            return Command::SUCCESS;
        } catch (Throwable $e) {
            echo $e->getMessage();
            return Command::FAILURE;
        }
    }

    public function parseFiles(array $files): array
    {
        $data = [];
        foreach ($files as $file) {
            $fileName = basename((string) $file);
            $pattern = '/^(?P<artifact>.+?)-(?P<version>\d.*)-(?P<vs>v[c|s]\d+)-(?P<arch>[^.]+)\.zip$/';
            if (!preg_match($pattern, $fileName, $matches)) {
                continue;
            }
            $data[] = [
                'file_path'     => $file,
                'file_name'     => $fileName,
                'extension'     => 'zip',
                'artifact_name' => $matches['artifact'],
                'vs_version'    => $matches['vs'],
                'arch'          => $matches['arch'],
            ];
        }
        return $data;
    }

    private function copyPhpFiles(array $files, string $library, string $vs_version_targets): void
    {
        $baseDirectory = $this->baseDirectory . "/php-sdk/deps";
        if (!is_dir($baseDirectory)) {
            mkdir($baseDirectory, 0755, true);
        }
        $vs_version_targets = explode(',', $vs_version_targets);
        foreach ($files as $file) {
            foreach ($vs_version_targets as $vs_version_target) {
                $destinationDirectory = $baseDirectory . '/' . $vs_version_target . '/' . $file['arch'];
                if (!is_dir($destinationDirectory)) {
                    mkdir($destinationDirectory, 0755, true);
                }
                $destinationFileName = str_replace($file['artifact_name'], $library, $file['file_name']);
                copy($file['file_path'], $destinationDirectory . '/' . $destinationFileName);
            }
        }
    }

    private function copyPeclFiles(array $files, string $library): void
    {
        $baseDirectory = $this->baseDirectory . "/pecl/deps";
        if (!is_dir($baseDirectory)) {
            mkdir($baseDirectory, 0755, true);
        }
        foreach ($files as $file) {
            $destinationFileName = str_replace($file['artifact_name'], $library, $file['file_name']);
            copy($file['file_path'], $baseDirectory . '/' . $destinationFileName);
        }
    }

    /**
     * @throws JsonException
     */
    private function updatePhpSeriesFiles(
        array  $files,
        string $library,
        string $php_versions,
        string $vs_version_targets,
        string $stability
    ): void
    {
        $php_versions = explode(',', $php_versions);
        $vs_version_targets = explode(',', $vs_version_targets);
        $stability_values = explode(',', $stability);
        $vsConfig = json_decode(
            file_get_contents(dirname(__DIR__, 3) . '/config/vs.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $devVersions = $vsConfig['dev'] ?? [];

        $baseDirectory = $this->baseDirectory . "/php-sdk/deps/series";

        if (!is_dir($baseDirectory)) {
            mkdir($baseDirectory, 0755, true);
        }

        foreach ($php_versions as $php_version) {
            $stabilityValues = $stability_values;
            if (in_array($php_version, $devVersions, true)) {
                $stabilityValues = ['stable', 'staging'];
            }
            foreach ($vs_version_targets as $vs_version_target) {
                foreach ($stabilityValues as $stability_value) {
                    foreach ($files as $file) {
                        $fileName = str_replace($file['artifact_name'], $library, $file['file_name']);
                        $arch = $file['arch'];
                        $seriesFile = $baseDirectory . "/packages-$php_version-$vs_version_target-$arch-$stability_value.txt";
                        if (!file_exists($seriesFile) && $arch === 'arm64') {
                            $this->seedArm64SeriesFile($baseDirectory, $php_version, $vs_version_target, $stability_value);
                        }

                        if (!file_exists($seriesFile)) {
                            $file_lines = [$fileName];
                        } else {
                            $file_lines = file($seriesFile, FILE_IGNORE_NEW_LINES);
                        }

                        $found = false;
                        foreach ($file_lines as $no => $line) {
                            if (str_starts_with($line, $library)) {
                                $file_lines[$no] = $fileName;
                                $found = true;
                            }
                        }
                        if (!$found) {
                            $file_lines[] = $fileName;
                        }

                        sort($file_lines, SORT_STRING);
                        file_put_contents($seriesFile, implode("\n", $file_lines));
                    }
                }
            }
        }
    }

    private function seedArm64SeriesFile(
        string $baseDirectory,
        string $phpVersion,
        string $vsVersionTarget,
        string $stability
    ): void {
        $arm64SeriesFile = $baseDirectory . "/packages-$phpVersion-$vsVersionTarget-arm64-$stability.txt";
        if (file_exists($arm64SeriesFile)) {
            return;
        }

        $x64SeriesFile = $baseDirectory . "/packages-$phpVersion-$vsVersionTarget-x64-$stability.txt";
        if (file_exists($x64SeriesFile)) {
            copy($x64SeriesFile, $arm64SeriesFile);
        }
    }

    private function updatePackagesFile(): void
    {
        $baseDirectory = $this->baseDirectory . "/pecl/deps";
        $packagesFile = $baseDirectory . "/packages.txt";

        if (!is_dir($baseDirectory)) {
            mkdir($baseDirectory, 0755, true);
        }

        $artifacts = glob($baseDirectory . '/*.zip');
        sort($artifacts);

        $fileLines = array_map(basename(...), $artifacts);

        file_put_contents($packagesFile, implode("\n", $fileLines));
    }
}
