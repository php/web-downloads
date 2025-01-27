<?php
use PHPUnit\Framework\TestCase;
use App\Console\Command;

class TestCommand extends Command {
    protected string $signature = "test {arg} {--option=}";

    public function handle(): int {
        return Command::SUCCESS;
    }
}

class CommandTest extends TestCase {
    public function testParseArgumentsAndOptions() {
        $argv = ["script.php", "value", "--option=optValue"];
        $command = new TestCommand(count($argv), $argv);

        $this->assertEquals("value", $command->getArgument("arg"), "Argument parsing failed.");
        $this->assertEquals("optValue", $command->getOption("option"), "Option parsing failed.");

        $command->setOption("option", "newOptValue");
        $this->assertEquals("newOptValue", $command->getOption("option"), "Option setting failed.");

        $this->assertEquals("", $command->getDescription());
        $this->assertEquals("test", $command->getSignature());
    }
}
