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
    if ($result !== TRUE) {
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
        setFlashMessage("Register successful");
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
$app->post('/login', function ($request, $response, $args) use ($log) {
    // extract values submitted
    $data = $request->getParsedBody();
    $username = $data['username'];
    $password = $data['password'];

    $userRecord = DB::queryFirstRow("SELECT * FROM users where username=%s", $username);
    $loginSuccessful = ($userRecord != null) && ($userRecord['password'] == $password);

    if (!$loginSuccessful) { //STATE2: login failed
        $log->info(sprintf("Login failed for username %s from %s", $username, $_SERVER['REMOTE_ADDR']));
        $error = "Invalid username or password!";
        return $this->get('view')->render($response, 'login.html.twig', ['error' => $error]);
    } else { //STATE3: login successful
        // Clear session password variable
        unset($userRecord['password']);
        // User is authenticated, set session variable
        $_SESSION['user'] = $userRecord;
        $log->debug(sprintf("Login successful for username %s, uid=%d, from %s", $username, $userRecord['id'], $_SERVER['REMOTE_ADDR']));

        setFlashMessage("Login successful");
        //redirect to home page     
        return $response->withHeader('Location', '/')->withStatus(302);
    }
});

//logout
$app->get('/logout', function (Request $request, Response $response) {
    // Clear session variable and redirect to login page
    unset($_SESSION['user']);
    setFlashMessage("Logout successful");
    return $response->withHeader('Location', '/')->withStatus(302);
});


// view profile
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
    if (!empty($password)) {
        $result = verifyPasswordQuality($password, $passwordrepeat);
        if ($result !== TRUE) {
            $errorList[] = $result;
        }
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
        setFlashMessage("Profile updated");
        //redirect to home page     
        return $response->withHeader('Location', '/')->withStatus(302);
        // return $this->get('view')->render($response, 'profile_edit_success.html.twig');
    }
});


// function return TRUE on success and String describing an issue on failure
function verifyPasswordQuality($password, $passwordrepeat)
{
    if ($passwordrepeat != $password) {
        return "Passwords do not match.";
    } else {
        if (
            (strlen($password) < 6) || (strlen($password) > 100)
            || (preg_match("/[A-Z]/", $password) == FALSE)
            || (preg_match("/[a-z]/", $password) == FALSE)
            || (preg_match("/[0-9]/", $password) == FALSE)
        ) {
            return "Password must be 6-100 characters long and contain at least one "
                . "uppercase letter, one lowercase, and one number.";
        }
    }
    return TRUE;
}

// show tour package list
$app->get('/packages', function ($request, $response, $args) {
    $packages = DB::query("SELECT * FROM tourpackages");
    $images = array();
    foreach ($packages as $package) {
        $imagesRecord = DB::query("SELECT * FROM images WHERE tourPackageId = %i", $package['id']);
        $images[$package['id']] = $imagesRecord;
        // print_r($images[$package['id']]);
    }
    return $this->get('view')->render($response, 'packages_list.html.twig', ['packages' => $packages, 'images' => $images]);
});

//User book a package
// STATE 1: first display of the form
$app->get('/packages/{id:[0-9]+}/book', function ($request, $response, $args) {
    if (!isset($_SESSION['user'])) { //refuse if user not logged in
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
    $id = $args['id'];
    $package = DB::queryFirstRow("SELECT * FROM tourpackages WHERE id=%i", $id);
    if (empty($package)) {
        $response = $response->withStatus(404);
        return $this->get('view')->render($response, 'not_found.html.twig');
    }

    $images = DB::query("SELECT * FROM images WHERE tourPackageId = %i", $package['id']);


    $userid = $_SESSION['user']['id'];
    $user = DB::queryFirstRow("SELECT * FROM users where id=%i", $userid);
    return $this->get('view')->render($response, 'booking.html.twig', ['package' => $package, 'v' => $user, 'images' => $images]);
});

// SATE 2&3: receiving a submission
$app->post('/packages/{id:[0-9]+}/book', function ($request, $response, $args) {
    $id = $args['id'];
    // extract values submitted
    $data = $request->getParsedBody();
    $name = $data["name"];
    $email = $data["email"];
    $phone = $data["phone"];

    // validate
    $errorList = [];

    $package = DB::queryFirstRow("SELECT * FROM tourpackages WHERE id=%i", $id);
    // check if id is provided
    if (isset($id) && !empty($package)) {
        //validate name
        if (strlen($name) < 2 || strlen($name) > 100) {
            $errorList[] = "Name must be 2-100 characters long.";
            $biddersname = "";
        }
        if (preg_match('/^[a-zA-Z0-9 .,-_]*$/', $name) != 1) {
            $errorList[] = "Name only accept letters (upper/lower-case), space, dash, dot, comma and numbers allowed";
            $name = "";
        }
        // validate email
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errorList[] = "Invalid email";
            $email = "";
        }

        //validate phone number
        if (!preg_match("/^(\+\d{1,2}\s?)?1?\-?\.?\s?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}$/", $phone)) {
            $errorList[] = "Invalid phone number.";
            $phone = "";
        }
    } else {
        $response = $response->withStatus(404);
        return $this->get('view')->render($response, 'not_found.html.twig');
    }

    if ($errorList) { // STATE 2: errors
        $valuesList = ['name' => $name, 'email' => $email, 'phone' => $phone];
        return $this->get('view')->render($response, 'booking.html.twig', ['errorList' => $errorList, 'v' => $valuesList]);
    } else { // STATE 3: sucess
        // save the booking details to the database
        DB::insert('orders', [
            'userId' => $_SESSION['user']['id'], 'total' => $package['price'], 'tourPackageId' => $id
        ]);
        // fetch the booking id
        $bookingRecord = DB::queryFirstRow("SELECT * FROM orders ORDER BY id DESC");
        $bookingId = $bookingRecord['id'];
        $bookingTotal = $bookingRecord['total'];
        setFlashMessage("Booking completed");
        // render the booking confirmation template
        return $this->get('view')->render($response, 'booking_confirmation.html.twig', ['bookingId' => $bookingId, 'bookingTotal' => $bookingTotal]);
    }
});

// user view my booking list
$app->get('/booking', function ($request, $response, $args) {
    if (!isset($_SESSION['user'])) { //refuse if user not logged in
        return $response->withHeader('Location', '/login')->withStatus(302);
    }

    // Retrieve user information from the database
    $id = $_SESSION['user']['id'];
    $user = DB::queryFirstRow("SELECT * FROM users WHERE id = %i", $id);
    // Retrieve booking information for the user, along with the associated package information
    $bookings = DB::query("SELECT o.id as orderId, t.*, o.* FROM  orders o inner join tourpackages t on o.tourPackageId = t.id where o.userid =%i", $id);
    return $this->get('view')->render($response, 'view_mybooking.html.twig', ['user' => $user, 'bookings' => $bookings]);
});


// user delete booking
// STATE 1: first display
$app->get('/booking/delete/{id:[0-9]+}', function ($request, $response, $args) {
    if (!isset($_SESSION['user'])) { //refuse if user not logged in
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
    $bookingId = $args['id'];
    // $id = $_SESSION['user']['id'];
    // $deleteUrl = "/booking/delete/$bookingId";
    $order = DB::queryFirstRow("SELECT * FROM orders where id=%i", $bookingId);
    if (!$order) {
        $response = $response->withStatus(404);
        return $this->get('view')->render($response, 'not_found.html.twig');
    }
    $package = DB::queryFirstRow("SELECT * FROM tourpackages t inner join orders o on o.tourPackageId = t.id where o.id =%i", $bookingId);

    return $this->get('view')->render($response, 'view_mybooking.html.twig', ['order' => $order, 'package' => $package]);
});

$app->post('/booking/delete/{id:[0-9]+}', function ($request, $response, $args) {
    DB::delete('orders', "id=%i", $args['id']);
    setFlashMessage("Booking deleted");
    return $response->withHeader('Location', '/booking')->withStatus(302);
});

// user can search package
// Define the search route
$app->post('/', function ($request, $response, $args) {
    // Retrieve the search criteria from the form
    $data = $request->getParsedBody();
    $location = isset($data["location"]) ? $data["location"] : null;
    if ($location) {
        // Construct the MeekroDB query
        $query = "SELECT * FROM tourpackages WHERE location LIKE %s";
        $results = DB::query($query, "%{$location}%");
        $images = array();
        //fetch image
        foreach ($results as $result) {
            $imagesRecord = DB::query("SELECT * FROM images WHERE tourPackageId = %i", $result['id']);
            $images[$result['id']] = $imagesRecord;
        }
        // Pass the search results to a Twig template
        return $this->get('view')->render($response, 'search_results.html.twig', ['results' => $results, 'images' => $images]);
    } else {
        // Render the search form again
        return $this->get('view')->render($response, 'home.html.twig');
    }
});
