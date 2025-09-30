<?php
declare(strict_types=1);

namespace App\Actions;

class FetchArtifact
{
    public function handle($url, $filepath, #[\SensitiveParameter] $token = null): void
    {
        $ch = curl_init();
        $fp = fopen($filepath, 'w');
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        if (str_contains($url, 'api.github.com')) {
            $headers = [
                'Accept: application/vnd.github+json',
                'User-Agent: PHP Web Downloads',
            ];

            if ($token) {
                $headers[] = 'Authorization: token ' . $token;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        curl_exec($ch);
        curl_close($ch);
        fclose($fp);
    }
}