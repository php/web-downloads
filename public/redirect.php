<?php
if (preg_match('/Win32-vc/', $_SERVER['REQUEST_URI'])) {
    $fixed = str_replace( 'Win32-vc', 'Win32-VC', $_SERVER['REQUEST_URI'] );
    header("Location: $fixed", true, 301);
    exit();
} else if(preg_match('/\/pecl\/.*\/(\d+.*(rc|RC).*\/)php_[^\-]+-([^\-]+-)/', $_SERVER['REQUEST_URI'])) {
    preg_match_all('/\/pecl\/.*\/(\d+.*(rc|RC).*\/)php_[^\-]+-([^\-]+-)/', $_SERVER['REQUEST_URI'], $matches);
    $fixed = str_replace($matches[1][0], strtoupper($matches[1][0]), $_SERVER['REQUEST_URI']);
    $fixed = str_replace($matches[2][0], strtolower($matches[2][0]), $fixed);
    header("Location: $fixed", true, 301);
    exit();
}

header('Location: /', true, 404);
