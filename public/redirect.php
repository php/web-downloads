<?php
if (preg_match('/Win32-vc/', $_SERVER['REQUEST_URI'])) {
    $fixed = str_replace( 'Win32-vc', 'Win32-VC', $_SERVER['REQUEST_URI'] );
    header("Location: $fixed", true, 301);
    exit();
}

header('Location: /', true, 404);
