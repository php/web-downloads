<?php

use App\Http\Controllers\IndexController;
use App\Http\Controllers\PeclController;
use App\Http\Controllers\PhpController;
use App\Http\Controllers\SeriesInitController;
use App\Http\Controllers\WinlibsController;
use App\Router;

$router = new Router();
$router->registerRoute('/api', 'GET', IndexController::class);
$router->registerRoute('/api/pecl', 'POST', PeclController::class, true);
$router->registerRoute('/api/winlibs', 'POST', WinlibsController::class, true);
$router->registerRoute('/api/php', 'POST', PhpController::class, true);
$router->registerRoute('/api/series-init', 'POST', SeriesInitController::class, true);
$router->handleRequest();
