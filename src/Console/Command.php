<?php
declare(strict_types=1);

namespace App\Console;

abstract class Command
{
    public const int SUCCESS = 0;
    public const int FAILURE = 1;
    public const int INVALID = 2;

    public string $name {
        get => explode(' ', $this->signature)[0];
    }

    public string $signature = '';
    public string $description = '';
    public array $arguments = [];
    public array $options = [];

    public array $cliArguments {
        set {
            $this->parse(count($value), $value);
        }
    }

    public function __construct() {
        //
    }

    abstract public function handle(): int;

    private function parse(int $argc, array $argv): void {
        $pattern = '/\{(\w+)}|\{--(\w+)}/';
        $signatureParts = [];
        if (preg_match_all($pattern, $this->signature, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $signatureParts[] = $match[1] ?: $match[2];
            }
        }

        $argCount = 0;
        for ($i = 1; $i < $argc; $i++) {
            if (preg_match('/^--([^=]+)=(.*)$/', (string) $argv[$i], $matches)) {
                $this->options[$matches[1]] = $matches[2];
            } elseif (preg_match('/^--([A-Za-z][A-Za-z0-9-]*)$/', (string) $argv[$i], $matches)) {
                $this->options[$matches[1]] = true;
            } else {
                if (isset($signatureParts[$argCount])) {
                    $this->arguments[$signatureParts[$argCount]] = $argv[$i];
                } else {
                    $this->arguments[$argCount] = $argv[$i];
                }
                $argCount++;
            }
        }
    }
}
