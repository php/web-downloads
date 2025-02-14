<?php

namespace App\Actions;

use App\Helpers\Helpers;

class GetArtifacts
{
    public function handle($workflow_run_id, $token): void
    {
        $ch = curl_init();

        $base_url = "https://api.github.com/repos";

        $repo = "winlibs/winlib-builder";

        curl_setopt_array($ch, [
            CURLOPT_URL => "$base_url/$repo/actions/runs/$workflow_run_id/artifacts",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => [
                "Accept: application/vnd.github+json",
                "X-GitHub-Api-Version: 2022-11-28",
                "User-Agent: PHP Web Downloads",
            ],
        ]);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            echo "cURL Error #:" . $err;
        } else {
            $artifacts = json_decode($response, true);
            $workflowRunDirectory = getenv('BUILDS_DIRECTORY') . "/winlibs/" . $workflow_run_id;
            if (is_dir($workflowRunDirectory)) {
                (new Helpers)->rmdirr($workflowRunDirectory);
            }
            umask(0);
            mkdir($workflowRunDirectory, 0777, true);
            foreach ($artifacts['artifacts'] as $artifact) {
                $filepath = $workflowRunDirectory . "/" . $artifact['name'] . ".zip";
                (new FetchArtifact)->handle($artifact['archive_download_url'], $filepath, $token);
            }
        }
    }
}