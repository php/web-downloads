<?php

namespace Actions;

use App\Actions\FetchArtifact;
use PHPUnit\Framework\TestCase;

class MockFetchArtifact extends FetchArtifact {

    public function handle($url, $filepath, $token = null): void
    {
        file_put_contents($filepath, $url . $token);
    }
}

class FetchArtifactTest extends TestCase {
    public function testHandleWithValidData() {
        $url = "https://example.com";
        $filepath = "test.txt";
        $token = "test_token";
        $fetchArtifact = new MockFetchArtifact();
        $fetchArtifact->handle($url, $filepath, $token);
        $this->assertFileExists($filepath);
        $this->assertEquals($url . $token, file_get_contents($filepath));
        unlink($filepath);
    }
}
