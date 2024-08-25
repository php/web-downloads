<?php

namespace App;

class GetArtifacts
{
    public static function handle($workflow_run_id, $token): null|array
    {
        $ch = curl_init();

        $base_url = "https://api.github.com/repos/";

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
            return null;
        } else {
            $files = [];
            $artifacts = json_decode($response, true);
            foreach ($artifacts['artifacts'] as $artifact) {
                $filepath = "/tmp/" . $artifact['name'] . ".zip";
                $files[] = $filepath;
                FetchArtifact::handle($artifact['archive_download_url'], $filepath, $token);
            }
            return $files;
        }
    }

}