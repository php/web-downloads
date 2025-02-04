<?php

namespace App\Console\Command;

use App\Actions\GetListing;
use App\Actions\UpdateReleasesJson;
use App\Console\Command;
use Exception;

class GenerateListingCommand extends Command
{
    protected string $signature = 'php:generate-listing --directory=';
    protected string $description = 'Generate Listing for PHP builds in a directory';

    public function __construct(
        protected GetListing $generateListing,
        protected UpdateReleasesJson $updateReleasesJson,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $directory = $this->getOption('directory');
            if (!$directory) {
                throw new Exception('Directory is required');
            }

            $releases = $this->generateListing->handle($directory);
            $this->updateReleasesJson->handle($releases, $directory);
            return Command::SUCCESS;
        } catch (Exception $e) {
            echo $e->getMessage();
            return Command::FAILURE;
        }
    }
}