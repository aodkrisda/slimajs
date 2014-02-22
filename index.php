<?php
////Up Session
if(!isset($_SESSION)):
	session_cache_limiter(false);
	session_start();
endif;

////Load ORM, PHPMailer & Slim libs
require 'RedBean/rb.php';
require 'Slim/Slim.php';

use \Slim\Slim;
use \Slim\Extras\Middleware\CsrfGuard;

//CFG & HELPERS
$config = array(
	'log.enable' => false,
	'debug' => true,

	'salt' => 'XXX',
	'db' => 'eip3_XX',
	'pass' => 'eip3XX',
	'host' => 'XXX',
	'user' => 'eip3_XX'
);

function flash ($msg, $cls = 'info') {
	setcookie('flashCookie', '["'.$msg.'", "'.$cls.'"]', time()+3600);
}

////Database Init
R::setup('mysql:host='.$config['host'].';dbname='.$config['db'], $config['user'], $config['pass']);


Slim::registerAutoloader();
$app = new Slim();

$app->config($config);
//$app->add(new CsrfGuard());

////-----------------------------------------------------------------------------------------------
////RUN ROUTE
//INDEX
$app->get('/', function () use ($app){
	$path = 'templates';
	$app->render('app.html');
});

//AUTH
//token
$app->get('/api/token', function () use ($app) {
	$app->contentType('application/json');
	echo json_encode(array(0=>$_SESSION['csrf_token']));
});

//login
$app->post('/api/login', function () use ($app) {
	$request = json_decode($app->request()->getBody());
	@$email = htmlspecialchars($request->email);
	@$password = htmlspecialchars($request->password);

	if ($email && $password) :
		$user = R::findOne('users', 'email = :email AND password = :password', array(':email'=>$email, ':password'=>md5($app->config('salt').$password)));
		if (!empty($user)) :
			$_SESSION['user'] = array(
				'email'=>$email,
				'id'=>$user->id,
				'role'=>$user->role,
				'name'=>$user->name,
				'surname'=>$user->surname,
				'description'=>$user->description
			);
			$app->contentType('application/json');
			echo json_encode(array(0=>'You are now logged as '.$email.'.'));
		else :
			$app->contentType('application/json');
			$app->halt(500, json_encode(array(0=>'Wrong email or password')));
		endif;
	else :
		$app->contentType('application/json');
		$app->halt(500, json_encode(array(0=>'Fill form properly')));
	endif;
});

//logout
$app->get('/api/logout', function () use ($app) {
	unset($_SESSION['user']);
	session_destroy();
	$app->contentType('application/json');
	echo json_encode(array(0=>'Logged out.'));
});

//register
$app->post('/api/register', function () use ($app) {
	$app->contentType('application/json');
	$errors = false;
	$request = json_decode($app->request()->getBody());
	@$email = htmlspecialchars($request->email);
	@$password = htmlspecialchars($request->password);
	@$name = htmlspecialchars($request->name);
	@$surname = htmlspecialchars($request->surname);
	if ( !$email || !filter_var($email, FILTER_VALIDATE_EMAIL) ) $errors .= 'email, ';
	if ( !$password || strlen($password) < 5 || strlen($password) > 60 ) $errors .= 'password, ';
	if ( !$name || !ctype_alpha($name) || strlen($name) < 2 || strlen($name) > 60 ) $errors .= 'name, ';
	if ( !$surname || !ctype_alpha($surname) || strlen($surname) < 2 || strlen($surname) > 60 ) $errors .= 'surname, ';
	if (!$errors) :
		//check if email already exists
		$userExist = R::findOne('users', 'email = ?', array($email));
		if (!empty($userExist)) : $app->halt(500, json_encode(array(0=>'This email is already taken.')));endif;

		$password = md5($app->config('salt').$password);
		$user = R::dispense('users');
		$user->email = $email;
		$user->password = $password;
		$user->name = $name;
		$user->surname = $surname;

		if (R::store($user)) :
			echo json_encode(array(0=>'You are now registered and can login!'));
		else :
			$app->halt(500, json_encode(array(0=>'Something is wrong with our database, its not your fault.')));
		endif;
	else :
		$errors = substr($errors, 0, -2);
		$app->halt(500, json_encode(array(0=>'Fill form properly ('.$errors.').')));
	endif;
});

//reset step 1
$app->post('/api/reset-password', function () use ($app) {
	$request = json_decode($app->request()->getBody());
	@$email = htmlspecialchars($request->email);
	$token = $_SESSION['csrf_token'];

	$user = R::findOne('users', 'email = ?', array($email));
	if (!empty($user)) :
		$user->token = $token;
		R::store($user);
		unset($_SESSION['user']);
		unset($_SESSION['token']);
		session_destroy();
		$link = '/api/reset-password?token='.$user->token.'&email='.$user->email;
		$msg = '<p>To reset password click <a href="'.$link.'">here</a>.</p>';
		//TODO:: sendMail z linkiem
		echo $link;;
	else:
		unset($_SESSION['user']);
		unset($_SESSION['token']);
		session_destroy();
		$app->contentType('application/json');
		$app->halt(500, json_encode(array(0=>'Cant find your email.')));
	endif;
});

//reset step 2
$app->get('/api/reset-password', function () use ($app) {
	$request = $app->request()->params();
	@$email = htmlspecialchars($request['email']);
	@$token = htmlspecialchars($request['token']);
	$user = R::findOne('users', 'email = :email AND token = :token', array(':email'=>$email, ':token'=>$token));
	if (!empty($user)) :
		unset($_SESSION['user']);
		unset($_SESSION['token']);
		session_destroy();
		session_start();
		$_SESSION['user'] = array('email'=>$user->email, 'id'=>$user->id, 'role'=>$user->role);
		flash('You have been login, CHANGE YOUR PASSWORD NOW!', 'danger');
		$app->redirect('/');
	else:
		die($user);
		//flash('Cant find valid user or link expired.', 'danger');
		//$app->redirect('/');
	endif;
});

//me
$app->get('/api/me', function () use ($app) {
	$app->contentType('application/json');
	if (isset($_SESSION['user'])) :
		echo json_encode($_SESSION['user']);
	else :
		$app->halt(401, json_encode(array(0=>'No one is logged in.')));
	endif;
});

//USER
//edit profile
$app->post('/api/profile', function () use ($app) {
	$app->contentType('application/json');
	if (isset($_SESSION['user'])) :
		$user = R::findOne('users', 'id = :id AND role = :role', array(':id'=>$_SESSION['user']['id'], ':role'=>'user'));
		if (!empty($user)) :
			$errors = false;
			$request = json_decode($app->request()->getBody());
			@$name = htmlspecialchars($request->name);
			@$surname = htmlspecialchars($request->surname);
			@$description = htmlspecialchars($request->description);
			
			if ( !$name || !ctype_alpha($name) || strlen($name) < 2 || strlen($name) > 60 ) $errors .= 'name, ';
			if ( !$surname || !ctype_alpha($surname) || strlen($surname) < 2 || strlen($surname) > 60 ) $errors .= 'surname, ';
			if ( !$description || strlen($description) > 6000 ) $errors .= 'description, ';

			if (!$errors) :
				$user->name = $name;
				$user->surname = $surname;
				$user->description = $description;
				$_SESSION['user']['name'] = $user->name;
				$_SESSION['user']['surname'] = $user->surname;
				$_SESSION['user']['description'] = $user->description;
				if (R::store($user)) :
					echo json_encode(array(0=>'Saved!'));
				else :
					$app->halt(500, json_encode(array(0=>'Something is wrong with our database, its not your fault.')));
				endif;
			else :
				$errors = substr($errors, 0, -2);
				$app->halt(500, json_encode(array(0=>'Fill form properly ('.$errors.').')));
			endif;
		else :
			$app->halt(500, json_encode(array(0=>'No such user found.')));
		endif;
	else:
		$app->halt(401, json_encode(array(0=>'No one is logged in.')));
	endif;
});
//get users
$app->get('/api/users', function () use ($app) {
	$app->contentType('application/json');
	$users = R::getAll('SELECT id, name, surname, description FROM users WHERE role = :role LIMIT 200', array(':role'=>'user'));
	if (!empty($users)) : 
		echo json_encode($users);
	else :
		$app->halt(204);//204 can't speak
	endif;
});
$app->get('/api/users/:id', function ($id) use ($app) {
	$app->contentType('application/json');
	$id = (int) htmlspecialchars($id);
	if (is_int($id)) : 
		$users = R::getAll('SELECT id, name, surname, description FROM users WHERE role = :role LIMIT :li', array(':role'=>'user', ':li'=>$id));
		if (!empty($users)) : 
			echo json_encode($users);
		else :
			$app->halt(204);//204 can't speak
		endif;
	else:
		$app->halt(500, json_encode(array(0=>'Wrong params.')));
	endif;
});
//get user
$app->get('/api/user/:id', function ($id) use ($app) {
	$app->contentType('application/json');
	$id = htmlspecialchars($id);
	$user = R::findOne('users', 'id = :id AND role = :role', array(':id'=>$id, ':role'=>'user'));
	if (!empty($user)) : 
		echo json_encode(array(
			'id' => $user->id,
			'name' => $user->name,
			'surname' => $user->surname,
			'description' => $user->description
		));
	else :
		$app->halt(204);//204 can't speak
	endif;
});


//STATIC PAGES
$app->get('/api/static/:id', function($slug) use ($app) {
	$app->contentType('application/json');
	$id = htmlspecialchars($slug);
	$article = R::find('pages', 'slug = ?', array($id));
	if (empty($article)) :
		$app->halt(404, json_encode(array(0=>'Page not found.')));
	else :
		echo json_encode(R::exportAll($article));
	endif;
});

$app->get('/api/static', function () use ($app) {
	$pages = R::findAll('pages');
});


$app->run();