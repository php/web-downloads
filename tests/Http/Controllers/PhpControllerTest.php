<?php
declare(strict_types=1);

namespace Http\Controllers;

use App\Http\DeferredCallbacks;
use App\Http\Controllers\PhpController;
use JsonException;
use Override;
use PHPUnit\Framework\TestCase;
use SensitiveParameter;
use ZipArchive;

class MockPhpController extends PhpController {
    #[Override]
    protected function validate(array $data): bool {
        return isset($data['key']);
    }

    #[Override]
    protected function execute(array $data): void {
        echo "Executed";
    }

    #[Override]
    public function handle(): void
    {
        $data = json_decode(file_get_contents($this->inputPath), true);

        if ($this->validate($data)) {
            $this->execute($data);
        }
    }
}

class DeferredPhpController extends PhpController
{
    #[Override]
    protected function fetchPhpBuild(
        string $url,
        #[SensitiveParameter] string $token,
        string $filepath,
    ): void {
        echo "Downloaded:$url:$token:" . basename($filepath);
    }
}

class InspectablePhpController extends PhpController
{
    public function download(string $url, string $token, string $filepath): void
    {
        parent::fetchPhpBuild($url, $token, $filepath);
    }
}

class PhpControllerTest extends TestCase {
    protected function tearDown(): void
    {
        DeferredCallbacks::clear();
        header_remove();
        parent::tearDown();
    }

    /**
     * @throws JsonException
     */
    public function testHandleWithValidData() {
        $data = json_encode(["key" => "value"]);
        $tempFile = tempnam(sys_get_temp_dir(), 'phpunit');
        file_put_contents($tempFile, $data);
        $controller = new MockPhpController($tempFile);
        $this->expectOutputString("Executed");
        $controller->handle();
        unlink($tempFile);
    }

    public function testDownloadIsDeferredUntilCallbacksAreInvoked(): void
    {
        $buildsDirectory = sys_get_temp_dir() . '/php-controller-' . uniqid();
        $previousBuildsDirectory = getenv('BUILDS_DIRECTORY');
        putenv('BUILDS_DIRECTORY=' . $buildsDirectory);

        $data = json_encode([
            'url' => 'https://example.com/php.zip',
            'token' => 'test-token',
        ]);
        $tempFile = tempnam(sys_get_temp_dir(), 'phpunit');
        file_put_contents($tempFile, $data);
        $initialOutputBufferLevel = ob_get_level();

        try {
            ob_start();

            (new DeferredPhpController($tempFile))->handle();

            $this->assertSame('', ob_get_contents());
            $this->assertTrue(DeferredCallbacks::hasCallbacks());

            DeferredCallbacks::invoke();

            $output = ob_get_clean();
            $this->assertIsString($output);
            $this->assertStringStartsWith(
                'Downloaded:https://example.com/php.zip:test-token:php-',
                $output,
            );
            $this->assertStringEndsWith('.zip', $output);
            $this->assertFalse(DeferredCallbacks::hasCallbacks());
        } finally {
            while (ob_get_level() > $initialOutputBufferLevel) {
                ob_end_clean();
            }

            unlink($tempFile);
            @rmdir($buildsDirectory . '/php');
            @rmdir($buildsDirectory);

            if ($previousBuildsDirectory === false) {
                putenv('BUILDS_DIRECTORY');
            } else {
                putenv('BUILDS_DIRECTORY=' . $previousBuildsDirectory);
            }
        }
    }

    public function testDownloadIsPublishedOnlyAfterZipValidation(): void
    {
        $directory = sys_get_temp_dir() . '/php-download-' . uniqid();
        mkdir($directory, 0755, true);

        $source = $directory . '/source.zip';
        $destination = $directory . '/published.zip';

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($source, ZipArchive::CREATE));
        $this->assertTrue($zip->addFromString('build.txt', 'complete'));
        $this->assertTrue($zip->close());

        try {
            (new InspectablePhpController())->download(
                'file://' . $source,
                'unused-token',
                $destination,
            );

            $this->assertFileExists($destination);
            $this->assertFileDoesNotExist($destination . '.part');

            $published = new ZipArchive();
            $this->assertTrue($published->open($destination));
            $this->assertSame('complete', $published->getFromName('build.txt'));
            $published->close();
        } finally {
            @unlink($source);
            @unlink($destination);
            @unlink($destination . '.part');
            @rmdir($directory);
        }
    }
}
