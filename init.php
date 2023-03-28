<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

require_once __DIR__ . '/vendor/autoload.php';

session_start();

//TODO: Add logger here
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

//create a log channel
$log = new Logger('main');
$log->pushHandler(new StreamHandler('applogs/everything.log', Logger::DEBUG));
$log->pushHandler(new StreamHandler('applogs/errors.log', Logger::ERROR));

$log->pushProcessor(function ($record) {
    $record['extra']['ip'] = $_SERVER['REMOTE_ADDR'];
    return $record;
});

DB::$dbName = 'tourism';
DB::$user = 'tourism';
DB::$password = 'r2Qzy67!n[r*ds-9';
DB::$host = 'localhost';

// Create Container
$container = new Container();
AppFactory::setContainer($container);

// Set view in Container
$container->set('view', function () {
    // return Twig::create('path/to/templates', ['cache' => 'path/to/cache']);
    return Twig::create(__DIR__ . '/templates', ['cache' => __DIR__ . '/tmplcache', 'debug' => true]);
});

// Create App
$app = AppFactory::create();

// Add Twig-View Middleware
$app->add(TwigMiddleware::createFromContainer($app));

$errorMiddleware = $app->addErrorMiddleware(true, true, true);