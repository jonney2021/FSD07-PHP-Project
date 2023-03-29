<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

require_once 'init.php';

// admin login
// STATE 1: first display of the form
$app->get('/admin/login', function ($request, $response, $args) {
    return $this->get('view')->render($response, 'admin/admin_login.html.twig');
});

// STATE 2&3: receiving a submission
$app->post('/admin/login', function ($request, $response, $args) {
    // extract values submitted
    $data = $request->getParsedBody();
    $username = $data['username'];
    $password = $data['password'];

    $adminRecord = DB::queryFirstRow("SELECT * FROM users where username=%s", $username);
    $loginSuccessful = ($adminRecord != null) && ($adminRecord['password'] == $password);

    if (!$loginSuccessful) { //STATE2: login failed
        $error = "Invalid username or password!";
        return $this->get('view')->render($response, 'admin/admin_login.html.twig', ['error' => $error]);
    } else { //STATE3: login successful
        // Clear session password variable
        unset($adminRecord['password']);
        // Admin is authenticated, set session variable
        $_SESSION['admin'] = $adminRecord;
        //redirect to home page
        return $response->withHeader('Location', '/admin')->withStatus(302);
    }
});

// admin logout
$app->get('/admin/logout', function (Request $request, Response $response) {
    // Clear session variable and redirect to login page
    unset($_SESSION['admin']);
    return $response->withHeader('Location', '/admin/login')->withStatus(302);
});

//admin dashboard
$app->get('/admin', function ($request, $response, $args) {
    return $this->get('view')->render($response, 'admin/master.html.twig', ['session' => $_SESSION]);
});

// users CRUD operations handling
// show userlist
$app->get('/admin/users/list', function ($request, $response, $args) {
    // if (!isset($_SESSION['admin'])) {
    //     $response = $response->withStatus(403);
    //     return $this->get('view')->render($response, 'admin/error_access_denied.html.twig');
    // }
    $usersList = DB::query("SELECT * FROM users");
    return $this->get('view')->render($response, 'admin/users_list.html.twig', ['usersList' => $usersList]);
});

// update/add user
// STATE 1: first display
$app->get('/admin/users/{op:edit|add}[/{id:[0-9]+}]', function ($request, $response, $args) {
    // either op is add and id is not given or op is edit and id must be given 
    if (($args['op'] == 'add' && !empty($args['id'])) || ($args['op'] == 'edit' && empty($args['id']))) {
        $response = $response->withStatus(404);
        return $this->get('view')->render($response, 'admin/not_found.html.twig');
    }
    if ($args['op'] == 'edit') {
        $user = DB::queryFirstRow("SELECT * FROM users where id=%i", $args['id']);
        if (!$user) {
            $response = $response->withStatus(404);
            return $this->get('view')->render($response, 'admin/not_found.html.twig');
        }
    } else {
        $user = [];
    }

    return $this->get('view')->render($response, 'admin/users_addedit.html.twig', ['v' => $user, 'op' => $args['op']]);
});

// STATE 2: receiving submission
$app->post('/admin/users/{op:edit|add}[/{id:[0-9]+}]', function ($request, $response, $args) {
    $op = $args['op'];
    // either op is add and id is not given or op is edit and id must be given 
    if (($op == 'add' && !empty($args['id'])) || ($op == 'edit' && empty($op))) {
        $response = $response->withStatus(404);
        return $this->get('view')->render($response, 'admin/not_found.html.twig');
    }
    // extract values submitted
    $data = $request->getParsedBody();
    $username = $data["username"];
    $email = $data["email"];
    $password = $data["password"];
    $passwordrepeat = $data["passwordrepeat"];
    $phoneno = $data["phoneno"];
    $role = $data["role"];
    $errorList = [];

    // validate
    if (empty($username)) {
        $errorList[] = "Email can't be empty.";
    }
    if (empty($email)) {
        $errorList[] = "Username can't be empty.";
    }
    if (empty($phoneno)) {
        $errorList[] = "Phone number can't be empty.";
    }

    // validate username
    if (strlen($username) < 2 || strlen($username) > 100) {
        $errorList[] = "Username must be 2-100 characters long.";
    }

    if (!preg_match("/^[a-zA-Z0-9 .,-_]*$/", $username)) {
        $errorList[] = "Username only accept letters (upper/lower-case), space, dash, dot, comma and numbers allowed.";
        $username = "";
    } else {
        // make sure this name is not used by another user
        if ($op == 'edit') { //update
            $existingUser = DB::queryFirstRow("SELECT * FROM users where username=%s AND id !=%i", $username, $args['id']);
        } else {
            $existingUser = DB::queryFirstRow("SELECT * FROM users where username=%s", $username);
        }
        if ($existingUser) {
            $errorList[] = "Username $username already registered";
            $username = "";
        }
    }

    // validate password always on add, and on update only if password was given
    if ($op == 'add' || !empty($password)) {
        if ($passwordrepeat !== $password) {
            $errorList[] = "Passwords do not match.";
        } else {
            if (
                strlen($password) < 6 || strlen($password) > 100
                || (preg_match("/[A-Z]/", $password) !== 1)
                || (preg_match("/[a-z]/", $password) !== 1)
                || (preg_match("/[0-9]/", $password) !== 1)
            ) {
                $errorList[] = "Password must be 6-100 characters long and contain at least one "
                    . "uppercase letter, one lowercase, and one number.";
            }
        }
    }

    // validate email
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errorList[] = "Invalid email";
        $email = "";
    } else {
        //is email already in used by another user
        if ($op == 'edit') { //update
            $record = DB::queryFirstRow("SELECT * FROM users WHERE email=%s AND id !=%i", $email, $args['id']);
        } else { // add has no id yet
            $record = DB::queryFirstRow("SELECT * FROM users WHERE email=%s", $email);
        }

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
        if ($op == 'add') {
            DB::insert('users', [
                'username' => $username, 'password' => $password, 'email' => $email, 'phoneNo' => $phoneno, 'role' => $role
            ]);
            return $this->get('view')->render($response, 'admin/users_addedit_success.html.twig', ['op' => $op]);
        } else {
            $data = [
                'username' => $username, 'email' => $email, 'phoneNo' => $phoneno, 'role' => $role
            ];
            if (!empty($password)) { // only update password if it was provided
                $data['password'] = $password;
            }
            DB::update('users', $data, "id=%i", $args['id']);
            return $this->get('view')->render($response, 'admin/users_addedit_success.html.twig', ['op' => $op]);
        }
    }
});

// delete user
//first display
$app->get('/admin/users/delete/{id:[0-9]+}', function ($request, $response, $args) {
    // fetch the user
    $user = DB::queryFirstRow("SELECT * FROM users where id=%i", $args['id']);
    if (!$user) {
        $response = $response->withStatus(404);
        return $this->get('view')->render($response, 'admin/not_found.html.twig');
    }
    return $this->get('view')->render($response, 'admin/users_delete.html.twig', ['v' => $user]);
});

$app->post('/admin/users/delete/{id:[0-9]+}', function ($request, $response, $args) {

    DB::delete('users', "id=%i", $args['id']);
    return $this->get('view')->render($response, 'admin/users_delete_success.html.twig');
});


//Function to check string starting
//with given substring
function startsWith($string, $startString)
{
    $len = strlen($startString);
    return (substr($string, 0, $len) === $startString);
}
// Attach middleware that verifies only admin can access /admin... URLs
// $app->add(function ($request, $handler) {
//     $url = $request->getUri()->getPath();
//     if (startsWith($url, "/admin")) {
//         if (!isset($_SESSION['admin'])) {
//             $response = $handler->handle($request);
//             return $this->get('view')->render($response, 'admin/error_access_denied.html.twig');
//         }
//     }
//     return $handler->handle($request);
// });


$app->add(function ($request, $handler) {
    $url = $request->getUri()->getPath();
    if (startsWith($url, "/admin")) {
        if ($url !== "/admin/login" && !isset($_SESSION['admin'])) {
            // If user is not an admin, return a 403 Forbidden response with custom error page
            $response = new \Slim\Psr7\Response();
            $response = $response->withStatus(403);
            $response->getBody()->write($this->get('view')->fetch('admin/error_access_denied.html.twig'));
            return $response;
        }
    }
    return $handler->handle($request);
});
