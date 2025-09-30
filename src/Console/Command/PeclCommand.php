<?php
declare(strict_types=1);

namespace App\Console\Command;

use App\Console\Command;
use Exception;
use ZipArchive;

class PeclCommand extends Command
{
    protected string $signature = 'pecl:add --base-directory= --builds-directory=';

    protected string $description = 'Add pecl extensions';

    public function handle(): int
    {
        try {
            $baseDirectory = $this->getOption('base-directory');
            if (!$baseDirectory) {
                throw new Exception('Base directory is required');
            }

            $buildsDirectory = $this->getOption('builds-directory');
            if (!$buildsDirectory) {
                throw new Exception('Build directory is required');
            }

            $zips_directory = $buildsDirectory . '/pecl';
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

                $destinationDirectory = $baseDirectory . "/pecl/releases";

                if (!is_dir($destinationDirectory)) {
                    mkdir($destinationDirectory, 0755, true);
                }

                $zip = new ZipArchive();

                if ($zip->open($filepath) === TRUE) {
                    if ($zip->extractTo($destinationDirectory) === FALSE) {
                        throw new Exception('Failed to extract the extension build');
                    }
                    $zip->close();
                } else {
                    throw new Exception('Failed to extract the extension');
                }

                unlink($filepath);

                unlink($filepath . '.lock');
            }

            return Command::SUCCESS;
        } catch (Exception $e) {
            echo $e->getMessage();
            return Command::FAILURE;
        }
    }
}