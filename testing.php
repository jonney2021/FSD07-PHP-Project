<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

require_once 'init.php';

// just for testing
$app->get('/hello/{name}', function (Request $request, Response $response, array $args) {
    $name = $args['name'];
    $response->getBody()->write("Hello, $name");
    return $response;
});

$app->get('/hello/{name}/{age}', function (Request $request, Response $response, array $args) {
    $name = $args['name'];
    $age = $args['age'];
    // $response->getBody()->write("Hello, $name,you are $age y/o");
    // return $response;
    return $this->get('view')->render($response, 'hello.html.twig', ['nameVal' => $name, 'ageVal' => $age]);
});
