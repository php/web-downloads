<?php

use App\Http\Controllers\IndexController;
use App\Http\Controllers\PeclController;
use App\Http\Controllers\PhpController;
use App\Http\Controllers\WinlibsController;
use App\Router;

$router = new Router();
$router->registerRoute('/', 'GET', IndexController::class);
$router->registerRoute('/pecl', 'POST', PeclController::class, true);
$router->registerRoute('/winlibs', 'POST', WinlibsController::class, true);
$router->registerRoute('/php', 'POST', PhpController::class, true);
$router->handleRequest();
