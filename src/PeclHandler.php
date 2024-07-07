<?php

use App\Validator;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class PeclHandler
{

    protected string $script = 'pecl.sh';

    public function handle(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        if(!$this->validate($data)) {
            http_response_code(400);
            echo 'Invalid request';
        } else {
            $this->execute($data);
        }
    }

    private function validate(mixed $data): bool
    {
        return (new Validator)->validate($data, [
            'url' => 'required|url',
            'extension' => 'required|string',
            'ref' => 'required|string',
        ]);
    }

    private function execute(array $data): void
    {
        extract($data);

        $process = new Process(['sudo -u $SCRIPTS_USER bash', $this->script, $url, $extension, $ref, $token ?? '']);

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