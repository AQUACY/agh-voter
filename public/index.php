<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
| Shared hosting (Hostinger): public files live in /public_html and the
| Laravel app lives in /public_html/backend. Locally the app is one level up.
*/
$basePath = is_dir(__DIR__.'/backend')
    ? __DIR__.'/backend'
    : dirname(__DIR__);

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $basePath.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $basePath.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $basePath.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
