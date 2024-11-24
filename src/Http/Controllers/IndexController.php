<?php

namespace App\Http\Controllers;
use App\Http\BaseController;

class IndexController extends BaseController
{
    public function handle(): void
    {
        echo 'Welcome!';
    }

    protected function validate(array $data): bool
    {
        return true;
    }

    protected function execute(array $data): void
    {
        //
    }
}