<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Slim\Flash\Messages;

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

// $log->debug("This is a test message");

//DATABASE SETUP
if ($_SERVER['SERVER_NAME'] == 'tourism.org') {
    DB::$dbName = 'tourism';
    DB::$user = 'tourism';
    DB::$password = 'r2Qzy67!n[r*ds-9';
    DB::$host = 'localhost';
} else { //hosted on external server
    DB::$dbName = 'cp5065_yeming';
    DB::$user = 'cp5065_yeming';
    DB::$password = 'mr{Pf-3_QYa0';
}

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

// All templates will be given userSession variable
$container->get('view')->getEnvironment()->addGlobal('userSession', $_SESSION['user'] ?? null);
$container->get('view')->getEnvironment()->addGlobal('flashMessage', getAndClearFlashMessage());
$container->get('view')->getEnvironment()->addGlobal('adminSession', $_SESSION['admin'] ?? null);


// LOGIN / LOGOUT USING FLASH MESSAGES TO CONFIRM THE ACTION

function setFlashMessage($message)
{
    $_SESSION['flashMessage'] = $message;
}

// returns empty string if no message, otherwise returns string with message and clears is
function getAndClearFlashMessage()
{
    if (isset($_SESSION['flashMessage'])) {
        $message = $_SESSION['flashMessage'];
        unset($_SESSION['flashMessage']);
        return $message;
    }
    return "";
}
