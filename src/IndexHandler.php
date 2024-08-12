<?php

namespace App;
class IndexHandler extends BaseHandler
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