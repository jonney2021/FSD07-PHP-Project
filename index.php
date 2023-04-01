<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;


require_once 'init.php';

//URL Handlers go below

require_once 'user.php';

require_once 'testing.php';

require_once 'admin.php';



// homepage
$app->get('/', function (Request $request, Response $response, $args) {
    return $this->get('view')->render($response, 'home.html.twig', ['session' => $_SESSION]);
});

// DO NOT FORGET APP->RUN() !
$app->run();
