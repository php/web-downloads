<?php
declare(strict_types=1);

$source = (string) getenv('RANGE_SERVER_SOURCE');
$requestLog = (string) getenv('RANGE_SERVER_LOG');
$range = $_SERVER['HTTP_RANGE'] ?? '';
$size = filesize($source);

if ($size === false || !preg_match('/^bytes=(\d+)-(\d+)$/', $range, $matches)) {
    http_response_code(416);
    return;
}

$start = (int) $matches[1];
$end = min((int) $matches[2], $size - 1);

if ($start > $end || $start >= $size) {
    http_response_code(416);
    return;
}

file_put_contents($requestLog, $range . PHP_EOL, FILE_APPEND | LOCK_EX);

http_response_code(206);
header('Accept-Ranges: bytes');
header('Content-Type: application/zip');
header(sprintf('Content-Range: bytes %d-%d/%d', $start, $end, $size));
header('Content-Length: ' . ($end - $start + 1));

$stream = fopen($source, 'rb');
if ($stream === false || fseek($stream, $start) !== 0) {
    http_response_code(500);
    return;
}

$remaining = $end - $start + 1;

while ($remaining > 0 && !feof($stream)) {
    $data = fread($stream, min(1024 * 1024, $remaining));
    if ($data === false) {
        break;
    }

    echo $data;
    $remaining -= strlen($data);
}

fclose($stream);
