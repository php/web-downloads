<?php

use App\IndexHandler;
use App\PeclHandler;
use App\PhpHandler;
use App\WinlibsHandler;
use App\Router;

$router = new Router();
$router->registerRoute('/', 'GET', IndexHandler::class);
$router->registerRoute('/pecl', 'POST', PeclHandler::class, true);
$router->registerRoute('/winlibs', 'POST', WinlibsHandler::class, true);
$router->registerRoute('/php', 'POST', PhpHandler::class, true);
$router->handleRequest();
