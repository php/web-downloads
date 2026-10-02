<?php
declare(strict_types=1);

namespace App\Actions;

use Exception;

class DeleteWinlibsBuild
{
    public function __construct(
        private readonly string $baseDirectory
    ) {
    }

    public function handle(string $type, string $filename): void
    {
        if (!in_array($type, ['php', 'pecl'], true)
            || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\.zip\z/', $filename) !== 1) {
            throw new Exception('Invalid Winlibs deletion job');
        }

        if ($type === 'php') {
            $this->deletePhpBuild(rtrim($this->baseDirectory, '/'), $filename);
        } else {
            $this->deletePeclBuild(rtrim($this->baseDirectory, '/'), $filename);
        }
    }

    private function deletePhpBuild(string $baseDirectory, string $filename): void
    {
        $depsDirectory = $baseDirectory . '/php-sdk/deps';
        $seriesDirectory = $depsDirectory . '/series';

        $seriesFiles = glob($seriesDirectory . '/packages-*.txt');
        if ($seriesFiles === false) {
            throw new Exception("Unable to list package indexes: $seriesDirectory");
        }
        foreach ($seriesFiles as $seriesFile) {
            $this->removeIndexEntry($seriesFile, $filename, true);
        }

        $vsDirectories = glob($depsDirectory . '/v[cs][0-9][0-9]', GLOB_ONLYDIR);
        if ($vsDirectories === false) {
            throw new Exception("Unable to list VS directories: $depsDirectory");
        }
        foreach ($vsDirectories as $vsDirectory) {
            if (is_link($vsDirectory)) {
                throw new Exception("Invalid VS directory: $vsDirectory");
            }
            foreach (['x86', 'x64', 'arm64'] as $arch) {
                if (is_link($vsDirectory . '/' . $arch)) {
                    throw new Exception("Invalid architecture directory: $vsDirectory/$arch");
                }
                $this->deleteFile($vsDirectory . '/' . $arch . '/' . $filename);
            }
        }
    }

    private function deletePeclBuild(string $baseDirectory, string $filename): void
    {
        $depsDirectory = $baseDirectory . '/pecl/deps';
        $this->removeIndexEntry($depsDirectory . '/packages.txt', $filename, false);
        $this->deleteFile($depsDirectory . '/' . $filename);
    }

    private function removeIndexEntry(string $path, string $filename, bool $removeEmptyFile): void
    {
        if (!file_exists($path)) {
            return;
        }
        if (!is_file($path) || is_link($path)) {
            throw new Exception("Invalid package index: $path");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new Exception("Unable to read package index: $path");
        }

        $newline = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $trailingNewline = str_ends_with($contents, "\n");
        $lines = $contents === '' ? [] : preg_split('/\r\n|\n|\r/', $contents);
        if ($trailingNewline) {
            array_pop($lines);
        }

        $remaining = array_values(array_filter($lines, static fn (string $line): bool => $line !== $filename));
        if (count($remaining) === count($lines)) {
            return;
        }

        if ($remaining === [] && $removeEmptyFile) {
            if (!unlink($path)) {
                throw new Exception("Unable to remove package index: $path");
            }
            return;
        }

        $updated = implode($newline, $remaining);
        if ($trailingNewline && $remaining !== []) {
            $updated .= $newline;
        }

        $temporary = @tempnam(dirname($path), '.packages-');
        if ($temporary === false || realpath(dirname($temporary)) !== realpath(dirname($path))) {
            if ($temporary !== false) {
                unlink($temporary);
            }
            throw new Exception("Unable to create temporary index: $path");
        }
        try {
            $permissions = fileperms($path);
            if ($permissions === false || file_put_contents($temporary, $updated, LOCK_EX) === false
                || !chmod($temporary, $permissions & 0777) || !rename($temporary, $path)) {
                throw new Exception("Unable to update package index: $path");
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    private function deleteFile(string $path): void
    {
        if (!file_exists($path) && !is_link($path)) {
            return;
        }
        if (!is_file($path) || is_link($path) || !unlink($path)) {
            throw new Exception("Unable to delete Winlibs file: $path");
        }
    }
}
