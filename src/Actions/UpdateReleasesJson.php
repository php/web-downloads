<?php
declare(strict_types=1);

namespace App\Actions;

use DateTimeImmutable;
use Exception;

class UpdateReleasesJson
{
    /**
     * @throws Exception
     */
    public function handle(array $releases, string $directory): void
    {
        try {
            foreach ($releases as &$release) {
                foreach ($release as &$build_type) {
                    if (!is_array($build_type) || !isset($build_type['mtime'])) {
                        continue;
                    }

                    $date = new DateTimeImmutable($build_type['mtime']);
                    $build_type['mtime'] = $date->format('c');
                }
                unset($build_type);
            }
            unset($release);
            file_put_contents(
                $directory . '/releases.json',
                json_encode($releases, JSON_PRETTY_PRINT)
            );
        } catch (Exception $exception) {
            throw new Exception('Failed to generate releases.json: ' . $exception->getMessage());
        }
    }
}