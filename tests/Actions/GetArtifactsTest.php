<?php

namespace Actions;

use App\Actions\GetArtifacts;
use App\Helpers\Helpers;
use PHPUnit\Framework\TestCase;

class MockGetArtifacts extends GetArtifacts {

    public function handle($workflow_run_id, $token): void
    {
        $data = [
            'artifacts' => [
                [
                    'name' => 'test1',
                    'archive_download_url' => 'https://example1.com'
                ],
                [
                    'name' => 'test2',
                    'archive_download_url' => 'https://example2.com'
                ],
            ]
        ];

        $workflowRunDirectory = getenv('BUILDS_DIRECTORY') . "/winlibs/" . $workflow_run_id;
        if (is_dir($workflowRunDirectory)) {
            (new Helpers)->rmdirr($workflowRunDirectory);
        }
        mkdir($workflowRunDirectory, 0755, true);
        foreach ($data['artifacts'] as $artifact) {
            $filepath = $workflowRunDirectory . "/" . $artifact['name'] . ".zip";
            (new MockFetchArtifact)->handle($artifact['archive_download_url'], $filepath, $token);
        }
    }
}

class GetArtifactsTest extends TestCase {

    public function setUp(): void
    {
        $temp_dir = sys_get_temp_dir();
        putenv("BUILDS_DIRECTORY=$temp_dir");
    }

    public function testHandleWithValidData(): void
    {
        $workflow_run_id = 123456;
        $token = "test_token";
        $getArtifacts = new MockGetArtifacts();
        $getArtifacts->handle($workflow_run_id, $token);
        $this->assertDirectoryExists(getenv('BUILDS_DIRECTORY') . "/winlibs/" . $workflow_run_id);
        $this->assertFileExists(getenv('BUILDS_DIRECTORY') . "/winlibs/" . $workflow_run_id . "/test1.zip");
        $this->assertFileExists(getenv('BUILDS_DIRECTORY') . "/winlibs/" . $workflow_run_id . "/test2.zip");
    }

    public function tearDown(): void
    {
        $workflowRunDirectory = getenv('BUILDS_DIRECTORY') . "/winlibs/123456";
        if (is_dir($workflowRunDirectory)) {
            (new Helpers)->rmdirr($workflowRunDirectory);
        }
    }
}
