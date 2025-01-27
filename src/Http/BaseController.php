<?php

namespace App\Http;

use JsonException;

abstract class BaseController implements ControllerInterface
{
    public function __construct(protected string $inputPath = "php://input") {
        //
    }

    /**
     * @throws JsonException
     */
    public function handle(): void
    {
        $data = json_decode(file_get_contents($this->inputPath), true, 512, JSON_THROW_ON_ERROR);
        if ($this->validate($data)) {
            $this->execute($data);
        }
    }

    protected abstract function validate(array $data): bool;

    protected abstract function execute(array $data): void;
}