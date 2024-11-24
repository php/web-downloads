#!/usr/bin/env php
<?php

use App\Console\Command;

require_once __DIR__ . '/autoloader.php';

$consoleDirectory = __DIR__ . '/src/Console/Command';

function getClassname($directory, $file): string
{
    $relativePath = str_replace($directory, '', $file->getPathname());
    $relativePath = str_replace('/', '\\', $relativePath);
    $relativePath = trim($relativePath, '\\');
    $className = 'App\\Console\\Command\\' . $relativePath;
    return str_replace('.php', '', $className);
}

function discoverCommands(string $directory, $argc, $argv): array
{
    $commands = [];
    foreach (
        new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        ) as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $className = getClassName($directory, $file);
            if (is_subclass_of($className, Command::class)) {
                $instance = new $className($argc, $argv);
                $commands[$instance->getSignature()] = $instance;
            }
        }
    }
    return $commands;
}

function listCommands(array $commands): void
{
    echo "Available commands:\n";
    /** @var Command $command */
    foreach ($commands as $signature => $command) {
        echo $signature . " - " . $command->getDescription() . "\n";
    }
}

$commands = discoverCommands($consoleDirectory, $argc, $argv);

$commandInput = $argv[1] ?? 'help';

if (in_array($commandInput, ['help', '--help', '-h', '?'], true)) {
    listCommands($commands);
    exit(Command::SUCCESS);
}

if (isset($commands[$commandInput])) {
    $status = $commands[$commandInput]->handle($argc, $argv);
    exit($status);
} else {
    echo "Command not found\n";
    exit(Command::INVALID);
}
