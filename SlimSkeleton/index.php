<?php

/******************************* LOADING & INITIALIZING BASE APPLICATION ****************************************/
error_reporting(E_ALL);
ini_set("display_errors", 1);
ini_set('max_execution_time', 300);
date_default_timezone_set('Asia/Seoul');
session_cache_limiter(false);
session_start();

//#################################################### REQUIRED LIBS ####################################################
//Autoloading all vendors
require "/vendor/autoload.php";

//Manually import library & utility
require "/app/util/Util.php";
require "/app/util/MyTwigCustomExtension.php";
require "/app/util/twitteroauth.php";
require "/app/util/captcha.php";

require "/app/config/constants.php";

//#################################################### THE SLIM ####################################################

//Set the environtment to development, make sure to switch to 'production' when it's ready to be deployed
$app = new \Slim\Slim(array(
    "mode" => "development"
));

//#################################################### THE HELPERS ####################################################
$util = new \app\util\Util();

//#################################################### THE DATABASE ####################################################

$app->container->singleton("db", function () {
	$db			= "SlimSkeleton";
	$servername = "localhost";
	$username	= "root";
	$password	= "ewide1234";
	
	try {
		$conn = new PDO("mysql:host=$servername;dbname=$db", $username, $password, 
					array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
				);
		
		$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}catch(PDOException $e){
		echo "DATABASE IS NOT PROPERLY CONFIGURED : ".$e;
		exit();
	}	
	return $conn;
});

//#################################################### THE VIEW ####################################################
$app->view = new \Slim\Views\Twig();
$app->view->setTemplatesDirectory("app/view");

//Cache view, make sure directory permission is 777
$app->view->parserOptions = array(
		"debug" => true,
		"cache" => "app/cache"
	);

//Load custom extenstin, like slugify() for using in view
$app->view->parserExtensions = array(
		new \Slim\Views\TwigExtension(),
		new \app\util\MyTwigCustomExtension()
);	

// CONSTANTS FOR TWIG 
$twig = $app->view->getEnvironment();

//Try to guess the browser, if it's mobile browser, adjust some bootstrap css accordingly
$twig->addGlobal("isMobile"		, $util->isProbablyMobile());
$twig->addGlobal("WEB_ROOT"		, "/public");
$twig->addGlobal("IMG_ERROR"	, "/img/noimage.jpg");
$twig->addGlobal("FACEBOOK_ID"	, FACEBOOK_ID);


//#################################################### THE MAILER ####################################################
//Swift_MailTransport
//->setUsername("")->setPassword("");

$transport 	= Swift_SmtpTransport::newInstance("", 25);		  
$mailer 	= Swift_Mailer::newInstance($transport);

//#################################################### APP VARIABLE ####################################################
$app->GLOBAL 	= "global value";

/******************************************* THE CONFIGS *******************************************************/

$app->configureMode("development", function () use ($app) {
    $app->config(array(
        "debug" => true
    ));
});

$app->configureMode("production", function () use ($app) {
    $app->config(array(
        "debug" => false
    ));
});


/******************************************** THE HOOKS ********************************************************/

$app->hook("slim.before.router", function () use ($app, $twig, $util) {
	
	//Initial attempt to determine what menu is being requested, can be overwritten in each router
	$menu = explode("/", $app->request->getResourceUri())[1];
	$twig->addGlobal("menu", $menu);
	
	//Intercept session and tell view whether the user is logged in or not
	if (isset($_SESSION["user"])) {
		$twig->addGlobal("userSession", $_SESSION["user"]);
	}else{
		$twig->addGlobal("userSession", "");		
	}
	
	//Define the page/router that begins with these segment to not initialize all gnb menu list
	$rawPages = array("ajax", "user", "social");
	
	//Determine whether the request is an ajax request or the normal one
	$isXHR = $app->request->isAjax();
	
	//If the request is an ajax type request, or the request does not need to initialize gnb (like processor page)
	//then no need to initialize gnb
	
	if(!$isXHR && !in_array($menu, $rawPages)){
		
	
	}else{
		//Ajax request, do nothing
	}
}, 1);

$app->applyHook("slim.before.router");


/************************************ THE ROUTES / CONTROLLERS *************************************************/

//#################################################### HOME ####################################################

$app->get("/", function () use ($app, $util) {
	
	$data				= array(
							"pageTitle"			=> "Home Page"
						); 
	
	$app->render("front/index.twig", $data);
	
});

$app->get("/social/facebook-share", function () use ($app, $util) {
	$data				= array(
			"pageTitle"			=> "Social Page - Facebook"
	);

	$app->render("front/social/facebookShare.twig", $data);

});
	
//#################################################### FORM ####################################################
	
$app->get("/form", function () use ($app, $util) {
	$app->render("front/form/form.twig");
});

$app->post("/form", function () use ($app, $util) {
	$storage = new \Upload\Storage\FileSystem("D:\Git\SlimSkeleton\SlimSkeleton\upload");
	
	for($i=0; $i<=5; $i++){
		$field = "file".$i;
		
		if($_FILES[$field]["size"]>0){
			$files[$i] = new \Upload\File($field, $storage);
			//exit();
			
			$newName = uniqid();
			$files[$i]->setName($newName);
			
			try {
				$files[$i]->upload();
			} catch (\Exception $e) {
				$errors = $files[$i]->getErrors();
			}
		}
		
	}
	
	var_dump($files);	
	
});

//#################################################### USER REGISTER/LOGIN ####################################################

$app->get("/auth", function () use ($app, $util, $twig) {
	
	$req 	= $app->request();
	
	if (isset($_SESSION["user"])) {
		$url = urldecode($url);
		$data	= array(
				"menu"		=> "login",
				"pageTitle" => "Login",
				"backUrl"	=> $url
		);
	
		$app->render("front/user/login.twig", $data);
	}
});

//#################################################### PROCESSOR ####################################################
	
$app->get("/user/logout", function () use ($app, $util) {
	unset($_SESSION["user"]);
	$app->redirect("/".$app->request->getRootUri());
});

$app->get("/user/login/twitter/proc/backurl/:url", function ($url) use ($app, $util) {
	
	$twitteroauth = new TwitterOAuth("KEY", "SECRET");
	
	// Requesting authentication tokens, the parameter is the URL we will be redirected to
	$request_token = $twitteroauth->getRequestToken("CALLBACKURL?url=".urlencode($url));
	
	// Saving them into the session	
	$_SESSION["oauth_token"] 		= $request_token["oauth_token"];
	$_SESSION["oauth_token_secret"] = $request_token["oauth_token_secret"];
	
	// If everything goes well..
	if ($twitteroauth->http_code == 200) {
		// Let's generate the URL and redirect
		$turl = $twitteroauth->getAuthorizeURL($request_token["oauth_token"]);
		//var_dump($request_token, $twitteroauth, $turl);
		//exit();
		$app->redirect($turl);
	} else {
		// It's a bad idea to kill the script, but we've got to know when there's an error.
		die("Something wrong happened.");
		//$app->render("front/error/500.twig");
	}	
});

$app->get("/user/social/login/callback/twitter", function () use ($app, $util) {

	if (!empty($_GET['oauth_verifier']) && !empty($_SESSION['oauth_token']) && !empty($_SESSION['oauth_token_secret'])) {
		
		$twitteroauth = new TwitterOAuth("KEY", "SECRET", $_SESSION['oauth_token'], $_SESSION['oauth_token_secret']);
		$access_token = $twitteroauth->getAccessToken($_GET['oauth_verifier']);
		$_SESSION['access_token'] = $access_token;
		$user_info = $twitteroauth->get('account/verify_credentials');
		
		//var_dump($user_info);
		
		if (isset($user_info->error)) {
			var_dump($_SESSION);
			exit();
			
		} else {
			echo "Success!, now i don't know what to do with this?";
			var_dump($_GET["url"]);
			var_dump($_SESSION);
			exit();
		}
	} else {
		
		//Very wrong, go home 
		$app->redirect($app->request->getRootUri());
	}
});

$app->get("/user/social/login/callback/facebook", function () use ($app, $util) {
	$app->render("front/social/fbpopup.twig");
});

$app->post("/user/login/facebook/proc", function () use ($app, $util) {
	$app->contentType("application/json");	
	echo json_encode($_POST);
});

$app->post("/user/login/proc", function () use ($app, $util, $twig) {
	$username	= $_POST["username"];
	$password	= $_POST["password"];
	$backUrl	= $_POST["back_url"];

	if(false == false){
		$app->redirect("http://".$_SERVER["HTTP_HOST"].$app->request->getRootUri()."/auth?backurl=".urlencode(urlencode($backUrl)));
		exit();
	}else{
		$_SESSION["user"] = $results;	
		$app->redirect($backUrl);
	}
});

$app->get("/user/register", function () use ($app, $util, $mailer) {
	
	$name		= "John Doe";
	$email		= "john@email.com";
	$phone		= "01014486555";
	
	//Save to database
	$sql = "INSERT INTO tbUser(fullName, email, phone,
			regIp, regId, regDate, delYn)
			VALUES(
				 :fullName
				,:email
				,:phone
				,:regIp
				, 'User'
				, now()
				, 'N'
			)";
	
	try {
		$statement	= $app->db->prepare($sql);
		$statement->bindValue(':fullName'	, $name);
		$statement->bindValue(':email'		, $email);
		$statement->bindValue(':phone'		, $phone);
		$statement->bindValue(':regIp'		, $_SERVER['REMOTE_ADDR']);
		$statement->execute();
	}catch(PDOException $e){
		var_dump($e);
		exit();
	}
	
	//Send email
	/*
	$msgString	= "Anda menerima pesan dari <strong>$name</strong> <br/>".
				  "dengan detail sebagai berikut: <br/><br/>".
				  "Nama : <strong>$name</strong><br/>".
				  "Email : <strong><a href='mailto:$email'>$email</a></strong><br/>".
				  "Phone : <strong>$phone</strong><br/>".
				  "Kategori : <strong>$kategori</strong><br/>".
				  "Pesan : $message<br/><br/>".		  
				  "Terima Kasih<br/><br/>".
				  "Team<br/>";
		
	$message = Swift_Message::newInstance($kategori." dari ".$name)
				->setFrom(array($email => $name))
				->setTo(array("" 	=> ""))
				->setBody($msgString, "UTF-8")
				->setContentType("text/html");
	
	$results = $mailer->send($message);
	if($results){
		$app->redirect("/".$app->request->getRootUri());
	}else{
		$app->render("front/error/500.twig");
	}
	*/
	
});

$app->get("/user/social/login/twitter/callback", function () use ($app, $util) {
	$app->render("front/error/500.twig");
});

$app->get("/user/captcha", function () use ($app, $util) {
	$captcha = new SimpleCaptcha();
	$captcha->wordsFile = 'words/en.php';
	$captcha->session_var = 'secretword';
	$captcha->imageFormat = 'png';
	$captcha->lineWidth = 3;
	$captcha->scale = 3; 
	$captcha->blur = true;
	$captcha->resourcesPath = "../app/util/captcha";	
	
	$app->contentType("image/png");
	$im = $captcha->CreateImage();
	echo $im;
});

//#################################################### ERROR ####################################################

$app->notFound(function () use ($app){
	$app->render("front/error/404.twig");
});

$app->error(function (\Exception $e) use ($app) {
	$app->render("front/error/500.twig");
});

/******************************************* RUN THE APP *******************************************************/

$app->run();

