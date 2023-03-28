<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

require_once 'init.php';

// URL handlers related to user, such as:
// login, register, logout, profile view/editing

//register
// STATE 1: first display of the form
$app->get('/register', function ($request, $response, $args) {
    return $this->get('view')->render($response, 'register.html.twig');
});

// STATE 2&3: receiving a submission
$app->post('/register', function ($request, $response, $args) {
    // extract values submitted
    $data = $request->getParsedBody();
    $username = $data["username"];
    $email = $data["email"];
    $password = $data["password"];
    $phoneno = $data["phoneno"];
    $errorList = [];


    // validate username
    if (empty($username) || empty($email) || empty($password) || empty($phoneno)) {
        $errorList[] = "Please fill in all the content.";
    }

    if (strlen($username) < 2 || strlen($username) > 100) {
        $errorList[] = "Username must be 2-100 characters long.";
    }

    if (!preg_match("/^[a-zA-Z0-9 .,-_]*$/", $username)) {
        $errorList[] = "Username only accept letters (upper/lower-case), space, dash, dot, comma and numbers allowed.";
        $username = "";
    } else {
        // make sure user name does not already exist in the database
        $existingUser = DB::queryFirstRow("SELECT * FROM users where username=%s", $username);
        if ($existingUser) {
            $errorList[] = "Username $username already exists in the database";
            $username = "";
        }
    }

    // validate password
    if (
        strlen($password) < 6 || strlen($password) > 100
        || (preg_match("/[A-Z]/", $password) !== 1)
        || (preg_match("/[a-z]/", $password) !== 1)
        || (preg_match("/[0-9]/", $password) !== 1)
    ) {
        $errorList[] = "Password must be at least 6 characters long and must contain at least one uppercase letter, 
    one lower case letter, and one number.";
    }

    // validate email
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errorList[] = "Invalid email";
        $email = "";
    }

    //validate phone number
    if (!preg_match("/^(\+\d{1,2}\s?)?1?\-?\.?\s?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}$/", $phoneno)) {
        $errorList[] = "Invalid phone number.";
        $phoneno = "";
    }

    if ($errorList) { // STATE 2: errors
        $valuesList = ['username' => $username, 'email' => $email, 'phoneno' => $phoneno];
        return $this->get('view')->render($response, 'register.html.twig', ['errorList' => $errorList, 'v' => $valuesList]);
    } else { // STATE 3: sucess
        DB::insert('users', [
            'username' => $username, 'password' => $password, 'email' => $email, 'phoneNo' => $phoneno
        ]);
        return $this->get('view')->render($response, 'register_success.html.twig');
    }
});

//isuserexist
$app->get('/isuserexist/{name}', function ($request, $response, $args) {
    $username = $args['name'];
    $existuser = DB::queryFirstRow("SELECT * FROM users WHERE username=%s", $username);
    if ($existuser) {
        $response->getBody()->write("This name is already registered!");
    }
    return $response;
});


//login
// STATE 1: first display of the form
$app->get('/login', function ($request, $response, $args) {
    return $this->get('view')->render($response, 'login.html.twig');
});

// STATE 2&3: receiving a submission
$app->post('/login', function ($request, $response, $args) {
    // extract values submitted
    $data = $request->getParsedBody();
    $username = $data['username'];
    $password = $data['password'];

    $userRecord = DB::queryFirstRow("SELECT * FROM users where username=%s", $username);
    $loginSuccessful = ($userRecord != null) && ($userRecord['password'] == $password);

    if (!$loginSuccessful) { //STATE2: login failed
        $error = "Invalid username or password!";
        return $this->get('view')->render($response, 'login.html.twig', ['error' => $error]);
    } else { //STATE3: login successful
        // Clear session password variable
        unset($userRecord['password']);
        // User is authenticated, set session variable
        $_SESSION['user'] = $userRecord;
        //redirect to home page
        return $response->withHeader('Location', '/')->withStatus(302);
    }
});

//logout
$app->get('/logout', function (Request $request, Response $response) {
    // Clear session variable and redirect to login page
    unset($_SESSION['user']);
    return $response->withHeader('Location', '/login')->withStatus(302);
});
