<?php

namespace App;

class WinlibsHandler extends BaseHandler
{
    protected function validate(array $data): bool
    {
        $validator = new Validator([
            'library' => 'required|string',
            'ref' => 'required|string',
            'workflow_run_id' => 'required|string',
            'php_versions' => 'required|string|regex:/^\d+\.\d+$}|^master$/',
            'vs_version' => 'required|string|regex:/^(v[c|s]\d{2})(,v[c|s]\d{2})*$/',
            'vs_version_targets' => 'required|string|regex:/^v[c|s]\d{2}$/',
            'stability' => 'required|string|regex:/^(stable|staging)(,(stable|staging))?$/',
            'token' => 'required|string',
        ]);

        $validator->validate($data);

        $valid = $validator->isValid();

        if(!$valid) {
            http_response_code(400);
            echo 'Invalid request: ' . $validator;
        }

        return $valid;
    }

    protected function execute(array $data): void
    {
        extract($data);
        $files = GetArtifacts::handle($workflow_run_id, $token);
        $files = $this->parseFiles($files);
        if($files) {
            $this->copyFiles($files, $library, $ref, $vs_version_targets);
            $this->updateSeriesFiles($files, $library, $ref, $php_versions, $vs_version_targets, $stability);
        }
    }

    private function parseFiles(array $files): array
    {
        $data = [];
        foreach ($files as $file) {
            $fileName = basename($file);
            $fileNameParts = explode('.', $fileName);
            $parsedFileNameParts = explode('-', $fileNameParts[0]);
            $data[] = [
                'file_path' => $file,
                'file_name' => $fileName,
                'extension' => $fileNameParts[1],
                'artifact_name' => $parsedFileNameParts[0],
                'vs_version' => $parsedFileNameParts[1],
                'arch' => $parsedFileNameParts[2],
            ];
        }
        return $data;
    }

    private function copyFiles(array $files, $library, $ref, $vs_version_targets): void
    {
        $baseDirectory = $_ENV['BUILDS_DIRECTORY'] . "/php-sdk/deps";
        if(!is_dir($baseDirectory)) {
            mkdir($baseDirectory, 0755, true);
        }
        $vs_version_targets = explode(',', $vs_version_targets);
        foreach($files as $file) {
            foreach ($vs_version_targets as $vs_version_target) {
                $destinationDirectory = $baseDirectory . '/' . $vs_version_target . '/' . $file['arch'];
                $destinationFileName = str_replace($file['artifact_name'], $library . '-' . $ref, $file['file_name']);
                copy($file['file_path'], $destinationDirectory . '/' . $destinationFileName);
            }
        }
    }

    private function updateSeriesFiles($files, $library, $ref, $php_versions, $vs_version_targets, $stability): void
    {
        $php_versions = explode(',', $php_versions);
        $vs_version_targets = explode(',', $vs_version_targets);
        $stability_values = explode(',', $stability);

        $baseDirectory = $_ENV['BUILDS_DIRECTORY'] . "/php-sdk/deps/series";

        foreach ($php_versions as $php_version) {
            foreach ($vs_version_targets as $vs_version_target) {
                foreach ($stability_values as $stability_value) {
                    foreach ($files as $file) {
                        $fileName = str_replace($file['artifact_name'], $library . '-' . $ref, $file['file_name']);
                        $arch = $file['arch'];
                        $seriesFile = $baseDirectory . "/packages-$php_version-$vs_version_target-$arch-$stability_value.txt";
                        $file_lines = file($seriesFile, FILE_IGNORE_NEW_LINES);
                        foreach($file_lines as $no => $line) {
                            if(strpos($line, $library) === 0) {
                                $file_lines[$no] = $fileName;
                            }
                        }
                        file_put_contents($seriesFile, implode("\n", $file_lines));
                    }
                }
            }
        }
    }
}