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
    $passwordrepeat = $data["passwordrepeat"];
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
    $result = verifyPasswordQuality($password, $passwordrepeat);
    if ($result != TRUE) {
        $errorList[] = $result;
    }

    // validate email
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errorList[] = "Invalid email";
        $email = "";
    } else {
        //is email already in use?
        $record = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
        if ($record) {
            $errorList[] = "This email is already registered";
            $email = "";
        }
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

//isuserexist used via AJAX
$app->get('/isuserexist/{name}', function ($request, $response, $args) {
    $username = $args['name'];
    $existuser = DB::queryFirstRow("SELECT * FROM users WHERE username=%s", $username);
    if ($existuser) {
        $response->getBody()->write("This name is already registered!");
    }
    return $response;
});

//isemailexist used via AJAX
$app->get('/isemailexist/{email}', function ($request, $response, $args) {
    $email = $args['email'];
    $record = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
    if ($record) {
        $response->getBody()->write("This email is already registered!");
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


// view profile
// $app->get('/profile', function ($request, $response, $args) use ($log) {
$app->get('/profile', function ($request, $response, $args) {
    if (!isset($_SESSION['user'])) { //refuse if user not logged in
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
    $id = $_SESSION['user']['id'];
    $user = DB::queryFirstRow("SELECT * FROM users where id=%i", $id);
    // $log->debug("Viewing profile");
    return $this->get('view')->render($response, 'profile.html.twig', ['user' => $user]);
});

// edit profile
// STATE 1: first display
$app->get('/profile/edit', function ($request, $response, $args) {
    if (!isset($_SESSION['user'])) { //refuse if user not logged in
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
    $id = $_SESSION['user']['id'];
    $user = DB::queryFirstRow("SELECT * FROM users where id=%i", $id);
    return $this->get('view')->render($response, 'profile_edit.html.twig', ['v' => $user]);
});

// STATE 2: receiving submission
$app->post('/profile/edit', function ($request, $response, $args) {
    $id = $_SESSION['user']['id'];
    // extract values submitted
    $data = $request->getParsedBody();
    $username = $data["username"];
    $email = $data["email"];
    $password = $data["password"];
    $passwordrepeat = $data["passwordrepeat"];
    $phoneno = $data["phoneno"];
    $errorList = [];

    // validate username
    if (empty($username) || empty($email) || empty($phoneno)) {
        $errorList[] = "Please fill in all the content.";
    }

    if (strlen($username) < 2 || strlen($username) > 100) {
        $errorList[] = "Username must be 2-100 characters long.";
    }

    if (!preg_match("/^[a-zA-Z0-9 .,-_]*$/", $username)) {
        $errorList[] = "Username only accept letters (upper/lower-case), space, dash, dot, comma and numbers allowed.";
        $username = "";
    } else {
        // make sure this name is not used by another user
        $existingUser = DB::queryFirstRow("SELECT * FROM users where username=%s AND id !=%i", $username, $id);
        if ($existingUser) {
            $errorList[] = "Username $username already registered";
            $username = "";
        }
    }

    // validate password
    $result = verifyPasswordQuality($password, $passwordrepeat);
    if ($result != TRUE) {
        $errorList[] = $result;
    }

    // validate email
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errorList[] = "Invalid email";
        $email = "";
    } else {
        //is email already in used by another user
        $record = DB::queryFirstRow("SELECT * FROM users WHERE email=%s AND id !=%i", $email, $id);
        if ($record) {
            $errorList[] = "This email is already registered";
            $email = "";
        }
    }

    //validate phone number
    if (!preg_match("/^(\+\d{1,2}\s?)?1?\-?\.?\s?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}$/", $phoneno)) {
        $errorList[] = "Invalid phone number.";
        $phoneno = "";
    }

    if ($errorList) { // STATE 2: errors
        $valuesList = ['username' => $username, 'email' => $email, 'phoneno' => $phoneno];
        return $this->get('view')->render($response, 'profile_edit.html.twig', ['errorList' => $errorList, 'v' => $valuesList]);
    } else { // STATE 3: sucess
        $data = [
            'username' => $username, 'email' => $email, 'phoneNo' => $phoneno
        ];
        if (!empty($password)) { // only update password if it was provided
            $data['password'] = $password;
        }
        DB::update('users', $data, "id=%i", $id);
        return $this->get('view')->render($response, 'profile_edit_success.html.twig');
    }
});


// function return TRUE on success and String describing an issue on failure
function verifyPasswordQuality($password, $passwordrepeat)
{
    if ($passwordrepeat !== $password) {
        $errorList[] = "Passwords do not match.";
    } else {
        if (
            strlen($password) < 6 || strlen($password) > 100
            || (preg_match("/[A-Z]/", $password) !== 1)
            || (preg_match("/[a-z]/", $password) !== 1)
            || (preg_match("/[0-9]/", $password) !== 1)
        ) {
            return "Password must be 6-100 characters long and contain at least one "
                . "uppercase letter, one lowercase, and one number.";
        }
    }
    return TRUE;
}
