<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Psr\Http\Message\UploadedFileInterface;

require_once 'init.php';

$container->set('upload_directory', __DIR__ . '/uploads');

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

    $adminRecord = DB::queryFirstRow("SELECT * FROM users where username=%s and role='admin'", $username);
    $loginSuccessful = ($adminRecord != null) && ($adminRecord['password'] == $password);

    if (!$loginSuccessful) { //STATE2: login failed
        $error = "Invalid username or password!";
        return $this->get('view')->render($response, 'admin/admin_login.html.twig', ['error' => $error]);
    } else { //STATE3: login successful
        // Clear session password variable
        unset($adminRecord['password']);
        // Admin is authenticated, set session variable
        $_SESSION['admin'] = $adminRecord;
        setFlashMessage("Login successful");
        //redirect to home page
        return $response->withHeader('Location', '/admin')->withStatus(302);
    }
});

// admin logout
$app->get('/admin/logout', function (Request $request, Response $response) {
    // Clear session variable and redirect to login page
    unset($_SESSION['admin']);
    setFlashMessage("Logout successful");
    return $response->withHeader('Location', '/admin')->withStatus(302);
});

//admin dashboard
$app->get('/admin', function ($request, $response, $args) {
    return $this->get('view')->render($response, 'admin/index.html.twig', ['session' => $_SESSION]);
});

// users CRUD operations handling
// show userlist
$app->get('/admin/users/list', function ($request, $response, $args) {
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
    if (($op == 'add' && !empty($args['id'])) || ($op == 'edit' && empty($args['id']))) {
        $response = $response->withStatus(404);
        return $this->get('view')->render($response, 'admin/not_found.html.twig');
    }
    // extract values submitted
    $data = $request->getParsedBody();
    // print_r($data);
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
        } else { //add
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
                (strlen($password) < 6) || (strlen($password) > 100)
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
        $valuesList = ['username' => $username, 'email' => $email, 'phoneno' => $phoneno, 'role' => $role];
        return $this->get('view')->render($response, 'admin/users_addedit.html.twig', ['errorList' => $errorList, 'v' => $valuesList]);
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
            setFlashMessage("Operation successful");
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
    setFlashMessage("Delete user successful");
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

// Package CRUD operations handling
// Add tour package
// STATE 1: first display of the form
$app->get('/admin/packages/add', function ($request, $response, $args) {
    return $this->get('view')->render($response, 'admin/package_add.html.twig');
});

// STATE 2&3: receiving a submission
$app->post('/admin/packages/add', function ($request, $response, $args) {
    // extract values submitted
    $data = $request->getParsedBody();
    $name = $data["name"];
    $type = $data["type"];
    $location = $data["location"];
    $price = $data["price"];
    $details = $data["details"];
    $errorList = [];

    // validate username
    if (empty($name) || empty($type) || empty($location) || empty($price) || empty($details)) {
        $errorList[] = "Please fill in all the content.";
    }

    if (strlen($name) < 2 || strlen($name) > 100) {
        $errorList[] = "Package name must be 2-100 characters long.";
        $name = "";
    }

    if (!preg_match("/^[a-zA-Z0-9 .,-_]*$/", $name)) {
        $errorList[] = "Package name only accept letters (upper/lower-case), space, dash, dot, comma and numbers allowed.";
        $name = "";
    } else {
        // make sure package name does not already exist in the database
        $existingRecord = DB::queryFirstRow("SELECT * FROM tourpackages where name=%s", $name);
        if ($existingRecord) {
            $errorList[] = "Package name $name already exists in the database";
            $name = "";
        }
    }

    //validate type
    if (strlen($type) < 2 || strlen($type) > 100) {
        $errorList[] = "Type must be 2-100 characters long.";
        $type = "";
    }

    //validate location
    if (strlen($location) < 2 || strlen($location) > 100) {
        $errorList[] = "Location must be 2-100 characters long.";
        $location = "";
    }

    //validate price
    if (!is_numeric($price) || $price < 0) {
        $errorList[] = "Invalid price";
        $price = "";
    }

    //validation details
    if (strlen($details) < 2 || strlen($details) > 1000) {
        $errorList[] = "Package description must be 2-1000 characters long.";
    }

    //file upload
    $directory = $this->get('upload_directory');
    $uploadedFiles = $request->getUploadedFiles();
    // print_r($uploadedFiles['images']);
    // print_r($_FILES['images']['name']);

    //validate image
    if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
        $errorList[] = "Please provide images.";
    } else {
        foreach ($_FILES['images']['name'] as $key => $value) {
            $file_type = $_FILES['images']['type'][$key];
            if (!in_array($file_type, array('image/jpeg', 'image/gif', 'image/png', 'image/bmp'))) {
                $errorList[] = "Only JPG, GIF, PNG, and BMP file types are accepted";
            }
        }
    }

    if ($errorList) { // STATE 2: errors
        $valuesList = ['name' => $name, 'type' => $type, 'location' => $location, 'price' => $price, 'details' => $details];
        return $this->get('view')->render($response, 'admin/package_add.html.twig', ['errorList' => $errorList, 'v' => $valuesList]);
    } else { // STATE 3: sucess

        $directory = $this->get('upload_directory');
        $uploadedFiles = $request->getUploadedFiles();

        DB::insert('tourpackages', [
            'name' => $name, 'type' => $type, 'location' => $location, 'price' => $price, 'details' => $details
        ]);

        //fetch the last record information
        $record = DB::queryFirstRow("SELECT * FROM tourpackages ORDER BY id DESC");
        $insertedId = $record['id'];

        foreach ($uploadedFiles['images'] as $uploadedFile) {
            if ($uploadedFile->getError() === UPLOAD_ERR_OK) {
                $filename = moveUploadedFile($directory, $uploadedFile);
                DB::insert('images', ['tourPackageId' => $insertedId, 'imageUrl' => "uploads/" . $filename]);
            }
        }
        setFlashMessage("New Package Added");
        return $this->get('view')->render($response, 'admin/package_add_success.html.twig');
    }
});


//function for file move
function moveUploadedFile(string $directory, UploadedFileInterface $uploadedFile)
{
    $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
    $basename = bin2hex(random_bytes(8));
    // avoid repeat name
    $filename = sprintf('%s.%0.8s', str_replace("." . $extension, "", $uploadedFile->getClientFilename()) . $basename, $extension);
    $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);
    return $filename;
}

// Show tour packages list
$app->get('/admin/packages/list', function ($request, $response, $args) {
    $packagesList = DB::query("SELECT * FROM tourpackages");
    return $this->get('view')->render($response, 'admin/packages_list.html.twig', ['packages' => $packagesList]);
});

// edit tour package
// STATE 1: first display
$app->get('/admin/packages/edit/{id:[0-9]+}', function ($request, $response, $args) {
    $package = DB::queryFirstRow("SELECT * FROM tourpackages where id=%i", $args['id']);
    if (!$package) {
        $response = $response->withStatus(404);
        return $this->get('view')->render($response, 'admin/not_found.html.twig');
    }
    return $this->get('view')->render($response, 'admin/package_edit.html.twig', ['v' => $package]);
});

// STATE 2: receiving submission
$app->post('/admin/packages/edit/{id:[0-9]+}', function ($request, $response, $args) {
    // extract values submitted
    $data = $request->getParsedBody();
    $name = $data["name"];
    $type = $data["type"];
    $location = $data["location"];
    $price = $data["price"];
    $details = $data["details"];
    $errorList = [];

    // validate
    if (empty($name)) {
        $errorList[] = "Name can't be empty.";
    }
    if (empty($type)) {
        $errorList[] = "Type can't be empty.";
    }
    if (empty($location)) {
        $errorList[] = "Location can't be empty.";
    }
    if (empty($price)) {
        $errorList[] = "price can't be empty.";
    }
    if (empty($details)) {
        $errorList[] = "Details can't be empty.";
    }

    if (strlen($name) < 2 || strlen($name) > 100) {
        $errorList[] = "Package name must be 2-100 characters long.";
        $name = "";
    }

    if (!preg_match("/^[a-zA-Z0-9 .,-_]*$/", $name)) {
        $errorList[] = "Username only accept letters (upper/lower-case), space, dash, dot, comma and numbers allowed.";
        $name = "";
    } else {
        // make sure package name does not already exist in the database
        $existingRecord = DB::queryFirstRow("SELECT * FROM tourpackages where name=%s AND id !=%i", $name, $args['id']);
        if ($existingRecord) {
            $errorList[] = "Package name $name already exists in the database";
            $name = "";
        }
    }

    //validate type
    if (strlen($type) < 2 || strlen($type) > 100) {
        $errorList[] = "Type must be 2-100 characters long.";
        $type = "";
    }

    //validate location
    if (strlen($location) < 2 || strlen($location) > 100) {
        $errorList[] = "Location must be 2-100 characters long.";
        $location = "";
    }

    //validate price
    if (!is_numeric($price) || $price < 0) {
        $errorList[] = "Invalid price";
        $price = "";
    }

    //validation details
    if (strlen($details) < 2 || strlen($details) > 1000) {
        $errorList[] = "Package description must be 2-1000 characters long.";
    }

    if ($errorList) { // STATE 2: errors
        $valuesList = ['name' => $name, 'type' => $type, 'location' => $location, 'price' => $price, 'details' => $details];
        return $this->get('view')->render($response, 'admin/package_edit.html.twig', ['errorList' => $errorList, 'v' => $valuesList]);
    } else { // STATE 3: success

        $data = [
            'name' => $name, 'type' => $type, 'location' => $location, 'price' => $price, 'details' => $details
        ];
        DB::update('tourpackages', $data, "id=%i", $args['id']);
        setFlashMessage("Package information updated");
        return $this->get('view')->render($response, 'admin/package_edit_success.html.twig');
    }
});

// delete package
//first display
$app->get('/admin/packages/delete/{id:[0-9]+}', function ($request, $response, $args) {
    // fetch the package
    $package = DB::queryFirstRow("SELECT * FROM tourpackages where id=%i", $args['id']);
    if (!$package) {
        $response = $response->withStatus(404);
        return $this->get('view')->render($response, 'admin/not_found.html.twig');
    }
    return $this->get('view')->render($response, 'admin/package_delete.html.twig', ['v' => $package]);
});

$app->post('/admin/packages/delete/{id:[0-9]+}', function ($request, $response, $args) {
    DB::delete('images', 'tourPackageId=%i', $args['id']);
    DB::delete('tourpackages', "id=%i", $args['id']);
    setFlashMessage("Package deleted");
    return $this->get('view')->render($response, 'admin/package_delete_success.html.twig');
});

// Show booking list
$app->get('/admin/booking/list', function ($request, $response, $args) {
    $results = DB::query("
    SELECT o.id as bookingId, o.total as totalPrice, o.placedTS as bookingTime, u.username as username, u.email as email, t.name as packageName 
    FROM orders o
    left JOIN users u ON o.userId = u.id
    left JOIN tourpackages t ON t.id = o.tourPackageId;
");
    return $this->get('view')->render($response, 'admin/booking_list.html.twig', ['results' => $results]);
});
