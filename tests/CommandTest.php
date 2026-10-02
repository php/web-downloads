<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use App\Console\Command;

class TestCommand extends Command {
    public string $signature = "test {arg} {--option=} {--delete}";

    public function handle(): int {
        return Command::SUCCESS;
    }
}

class CommandTest extends TestCase {
    public function testParseArgumentsAndOptions() {
        $argv = ["script.php", "value", "--option=optValue", "--delete"];
        $command = new TestCommand();
        $command->cliArguments = $argv;

        $this->assertEquals("value", $command->arguments["arg"] ?? null, "Argument parsing failed.");
        $this->assertEquals("optValue", $command->options["option"] ?? null, "Option parsing failed.");
        $this->assertTrue($command->options['delete'] ?? false, 'Boolean option parsing failed.');

        $command->options = ['option' => "newOptValue"];
        $this->assertEquals("newOptValue", $command->options["option"] ?? null, "Option setting failed.");

        $this->assertEquals("", $command->description);
        $this->assertEquals("test", $command->name);
    }
}
