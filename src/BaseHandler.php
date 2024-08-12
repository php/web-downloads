<?php

namespace App;

abstract class BaseHandler implements HandlerInterface
{
    public function handle(): void {
        $data = json_decode(file_get_contents('php://input'), true);

        if($this->validate($data)) {
            $this->execute($data);
        }
    }
    protected abstract function validate(array $data): bool;
    protected abstract function execute(array $data): void;
}