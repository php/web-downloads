<?php

namespace App\Actions;

class GetListing
{
    public function handle(string $directory): array
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
                'path' => basename($file_ori),
                'size' => $this->bytes2string(filesize($file_ori)),
                'sha256' => $sha256sums[strtolower(basename($file_ori))]
            ];
            $namingPattern = $parts['version'] . ($parts['nts'] ? '-' . $parts['nts'] : '') . '-Win32-' . $parts['vc'] . '-' . $parts['arch'] . ($parts['ts'] ? '-' . $parts['ts'] : '');
            $build_types = [
                'source' => 'php-' . $parts['version'] . '-src.zip',
                'debug_pack' => 'php-debug-pack-' . $namingPattern . '.zip',
                'devel_pack' => 'php-devel-pack-' . $namingPattern . '.zip',
                'installer' => 'php-' . $namingPattern . '.msi',
                'test_pack' => 'php-test-pack-' . $parts['version'] . '.zip',
            ];
            foreach ($build_types as $type => $fileName) {
                $filePath = $directory . '/' . $fileName;
                if (file_exists($filePath)) {
                    if(in_array($type, ['test_pack', 'source'])) {
                        $releases[$version_short][$type] = [
                            'path' => $fileName,
                            'size' => $this->bytes2string(filesize($filePath)),
                            'sha256' => $sha256sums[strtolower(basename($file_ori))]
                        ];
                    } else {
                        $releases[$version_short][$key][$type] = [
                            'path' => $fileName,
                            'size' => $this->bytes2string(filesize($filePath)),
                            'sha256' => $sha256sums[strtolower(basename($file_ori))]
                        ];
                    }
                }
            }
        }
        return $releases;
    }

    public function getSha256Sums($directory): array
    {
        $result = [];
        if(!file_exists("$directory/sha256sum.txt")) {
            file_put_contents("$directory/sha256sum.txt", '');
        }
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

    public function bytes2string(int $size): string
    {
        $sizes = ['YB', 'ZB', 'EB', 'PB', 'TB', 'GB', 'MB', 'kB', 'B'];

        $total = count($sizes);

        while ($total-- && $size > 1024) $size /= 1024;

        return round($size, 2) . $sizes[$total];
    }

    public function parseFileName($fileName): array
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
            'version' => $version,
            'version_short' => substr($version, 0, 3),
            'nts' => $nts,
            'vc' => $vc,
            'arch' => $arch,
            'ts' => $ts
        ];
    }
}