<?php
declare(strict_types=1);

namespace App\Console;

abstract class Command
{
    public const SUCCESS = 0;
    public const FAILURE = 1;
    public const INVALID = 2;

    protected string $signature = '';
    protected string $description = '';
    protected array $arguments = [];
    protected array $options = [];

    public function __construct() {
        //
    }

    public function setCliArguments(int $argc, array $argv): void {
        $this->parse($argc, $argv);
    }

    abstract public function handle(): int;

    private function parse($argc, $argv): void {
        $pattern = '/\{(\w+)}|\{--(\w+)}/';
        $signatureParts = [];
        if (preg_match_all($pattern, $this->signature, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $signatureParts[] = $match[1] ?: $match[2];
            }
        }

        $argCount = 0;
        for ($i = 1; $i < $argc; $i++) {
            if (preg_match('/^--([^=]+)=(.*)$/', $argv[$i], $matches)) {
                $this->options[$matches[1]] = $matches[2];
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

    public function getSignature(): string {
        return explode(' ', $this->signature)[0];
    }

    public function getDescription(): string {
        return $this->description;
    }

    public function getArgument($index): mixed
    {
        return $this->arguments[$index] ?? null;
    }

    public function getOption($name): mixed
    {
        return $this->options[$name] ?? null;
    }

    public function setOption($name, $value): void
    {
        $this->options[$name] = $value;
    }
}