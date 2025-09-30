<?php
declare(strict_types=1);

namespace Console\Command;

use App\Actions\GetListing;
use App\Actions\UpdateReleasesJson;
use App\Console\Command as BaseCommand;
use App\Console\Command\GenerateListingCommand;
use Exception;
use PHPUnit\Framework\MockObject\Exception as MockObjectException;
use PHPUnit\Framework\TestCase;

class GenerateListingCommandTest extends TestCase
{
    /**
     * @throws MockObjectException
     */
    public function testHandleWithoutDirectory(): void
    {
        $getListing = $this->createMock(GetListing::class);
        $updateReleasesJson = $this->createMock(UpdateReleasesJson::class);
        $command = new GenerateListingCommand($getListing, $updateReleasesJson);
        $argv = ['script.php', 'php:add'];
        $argc = count($argv);
        $command->setCliArguments($argc, $argv);

        ob_start();
        $result = $command->handle();
        $output = ob_get_clean();

        $this->assertStringContainsString('Directory is required', $output);
        $this->assertEquals(BaseCommand::FAILURE, $result);
    }

    /**
     * @throws MockObjectException
     */
    public function testHandleSuccess(): void
    {
        $directory = '/some/directory';
        $dummyReleases = ['dummy' => 'value'];

        $getListing = $this->createMock(GetListing::class);
        $updateReleasesJson = $this->createMock(UpdateReleasesJson::class);

        $getListing->expects($this->once())
            ->method('handle')
            ->with($directory)
            ->willReturn($dummyReleases);

        $updateReleasesJson->expects($this->once())
            ->method('handle')
            ->with($dummyReleases, $directory);

        $command = new GenerateListingCommand($getListing, $updateReleasesJson);

        $argv = ['script.php', 'php:add', '--directory=' . $directory];
        $argc = count($argv);
        $command->setCliArguments($argc, $argv);

        $result = $command->handle();

        $this->assertEquals(BaseCommand::SUCCESS, $result);
    }

    /**
     * @throws MockObjectException
     */
    public function testHandleWhenExceptionThrown(): void
    {
        $directory = '/some/directory';

        $getListing = $this->createMock(GetListing::class);
        $updateReleasesJson = $this->createMock(UpdateReleasesJson::class);

        $getListing->expects($this->once())
            ->method('handle')
            ->with($directory)
            ->will($this->throwException(new Exception("Test exception")));

        $updateReleasesJson->expects($this->never())
            ->method('handle');

        $command = new GenerateListingCommand($getListing, $updateReleasesJson);
        $argv = ['script.php', 'php:add', '--directory=' . $directory];
        $argc = count($argv);
        $command->setCliArguments($argc, $argv);

        ob_start();
        $result = $command->handle();
        $output = ob_get_clean();

        $this->assertStringContainsString("Test exception", $output);
        $this->assertEquals(BaseCommand::FAILURE, $result);
    }
}
