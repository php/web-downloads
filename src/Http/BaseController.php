<?php

namespace App\Http;

abstract class BaseController implements ControllerInterface
{
    public function handle(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if ($this->validate($data)) {
            $this->execute($data);
        }
    }

    protected abstract function validate(array $data): bool;

    protected abstract function execute(array $data): void;
}