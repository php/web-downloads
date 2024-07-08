<?php

namespace App;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class PeclHandler
{

    protected string $script = 'pecl.sh';

    public function handle(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if($this->validate($data)) {
            $this->execute($data);
        }
    }

    private function validate(mixed $data): bool
    {
        $validator = new Validator([
            'url' => 'required|url',
            'extension' => 'required|string',
            'ref' => 'required|string',
        ]);

        $validator->validate($data);

        $valid = $validator->isValid();

        if(!$valid) {
            http_response_code(400);
            echo 'Invalid request: ' . $validator;
        }

        return $valid;
    }

    private function execute(array $data): void
    {
        extract($data);
        $SCRIPTS_USER = $_ENV['SCRIPTS_USER'];
        $this->script = __DIR__ . "/scripts/$this->script";
        $process = new Process(['sudo', '-u', $SCRIPTS_USER, 'bash', $this->script, $extension, $ref, $url, $token ?? '']);

        try {
            $process->mustRun(function ($type, $buffer): void {
                echo $buffer;
            });
        } catch (ProcessFailedException $exception) {
            http_response_code(500);
            echo 'Failed to add extension: ' . $exception->getMessage();
        }
    }
}