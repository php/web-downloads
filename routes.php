<?php
declare(strict_types=1);

use App\Http\Controllers\IndexController;
use App\Http\Controllers\ListBuildsController;
use App\Http\Controllers\PeclController;
use App\Http\Controllers\PhpController;
use App\Http\Controllers\SeriesDeleteController;
use App\Http\Controllers\SeriesInitController;
use App\Http\Controllers\SeriesStabilityController;
use App\Http\Controllers\WinlibsController;
use App\Router;

$router = new Router();
$router->registerRoute('/api', 'GET', IndexController::class);
$router->registerRoute('/api/list-builds', 'GET', ListBuildsController::class, true);
$router->registerRoute('/api/pecl', 'POST', PeclController::class, true);
$router->registerRoute('/api/winlibs', 'POST', WinlibsController::class, true);
$router->registerRoute('/api/php', 'POST', PhpController::class, true);
$router->registerRoute('/api/series-init', 'POST', SeriesInitController::class, true);
$router->registerRoute('/api/series-delete', 'POST', SeriesDeleteController::class, true);
$router->registerRoute('/api/series-stability', 'POST', SeriesStabilityController::class, true);
$router->handleRequest();
