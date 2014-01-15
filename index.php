<?php
$config = array(
	'salt' => 'typeSomeFancyCodeHere',
	'db' => 'slimajs',
	'pass' => '',
	'host' => 'localhost',
	'user' => 'root'
);

////Up Session
session_cache_limiter(false);
session_start();

////Load ORM & Slim libs
require 'RedBean/rb.php';
require 'Slim/Slim.php';

////Go Slim!
use \Slim\Slim;
use \Slim\Extras\Middleware\CsrfGuard;

Slim::registerAutoloader();

$app = new Slim();
$app->config($config);
$app->add(new CsrfGuard());

////Database Init
R::setup('mysql:host='.$config['host'].';dbname='.$config['db'], $config['user'], $config['pass']);


////-----------------------------------------------------------------------------------------------
////RUN ROUTE
$app->get('/*', function() use ($app){
	$path = 'templates';
	$app->render('app.html');
});

////AUTH
//token
$app->get('/token', function() use ($app) {
	$app->contentType('application/json');
	echo $_SESSION['csrf_token'];
});

//login
$app->post('/login', function() use ($app) {
	$request = json_decode($app->request()->getBody());
	@$email = $request->email;
	@$password = $request->password;

	if($email && $password) :
		$user = R::findOne('users', 'email = :email AND password = :password', array(':email'=>$email, ':password'=>md5($app->config['salt'].$password)));
		if(!empty($user)) :
			$_SESSION['user'] = array('email'=>$email, 'id'=>$user->id);
			$app->contentType('application/json');
			echo 'You are now logged as '.$email.'.';
		else :
			$app->contentType('application/json');
			$app->halt(500, 'Wrong email or password');
		endif;
	else :
		$app->contentType('application/json');
		$app->halt(500, 'Fill form properly');
	endif;
});

//logout
$app->get('/logout', function() use ($app) {
	unset($_SESSION['user']);
	session_destroy();
	$app->redirect('/');
});

//register
$app->post('/register', function() use ($app) {
	$request = json_decode($app->request()->getBody());
	@$email = $request->email;
	@$password = $request->password;

	if($email && $password) :
		$password = md5($app->config['salt'].$password);
		$user = R::dispense('users');
		$user->email = $email;
		$user->password = $password;

		if(R::store($user)) :
			$app->contentType('application/json');
			echo 'You are now registered and can login!';
		else :
			do500($app, 'Something is wrong with our database, its not your fault.');
		endif;
	else :
		do500($app, 'Fill form properly');
	endif;
});

//me
$app->get('/me', function() use ($app) {
	$app->contentType('application/json');
	echo isset($_SESSION['user']) ? json_encode($_SESSION['user']) : json_encode(array());
});


////ARTICLES
//all
$app->get('/articles', function() use ($app) {
	$articles = R::find('pages');
	$app->contentType('application/json');
	echo json_encode(R::exportAll($articles));
});

//index
$app->get('/article/:id', function($slug) use ($app) {
	$id = htmlspecialchars($slug);
	$article = R::find('pages', 'slug = ?', array($id));
	$app->contentType('application/json');
	if(empty($article)) :
		$app->halt(404, 'Page not found');
	else :
		echo json_encode(R::exportAll($article));
	endif;
});

$app->run();