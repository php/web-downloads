<?php

namespace App\Helpers;

class Helpers
{
    public function rmdirr($node): bool
    {
        if (!file_exists($node)) {
            return false;
        }
        if (is_file($node) || is_link($node)) {
            return unlink($node);
        }
        $dir = dir($node);
        while (false !== $leaf = $dir->read()) {
            if ($leaf == '.' || $leaf == '..') {
                continue;
            }
            $this->rmdirr($node . DIRECTORY_SEPARATOR . $leaf);
        }
        $dir->close();
        return rmdir($node);
    }
}