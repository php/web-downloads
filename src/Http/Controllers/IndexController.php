<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\BaseController;

class IndexController extends BaseController
{
    public function handle(): void
    {
        echo 'Welcome!';
    }

    public function validate(array $data): bool
    {
        return true;
    }

    public function execute(array $data): void
    {
        //
    }
}