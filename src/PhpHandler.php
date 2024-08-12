<?php

namespace App;

class PhpHandler extends BaseHandler
{

    public function handle(): void
    {
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