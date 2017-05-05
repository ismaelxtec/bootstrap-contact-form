<?php
	/**
         *	BootstrapBay, 2016
	 *		https://github.com/bootstrapbay/contact-form/
	 *	Ismael, 01/03/2017
	 *		https://github.com/ismaelxtec/bootstrap-contact-form/
	 *		Added more security tricks to control XSS, CSRF, dynamic CAPTCHA, ...	 
	 */

	//error_reporting(0); 	// Desactivar toda notificación de error
	error_reporting(E_ALL); // Notificar todos los errores de PHP (ver el registro de cambios)
        ini_set("display_errors", 1);

	// Acceso a los objetos de sesión:
	session_start();
	session_regenerate_id(); //Poner más dificil 'Cross-site scripting' (XSS) a partir del ID de sesión.

	function my_print_POST($data)
	{
	  if (!empty($_POST[$data])) echo htmlspecialchars($_POST[$data]);		
	}//my_print

	function xss_clean($data)
	{
		if (!empty($data)) {
			// Fix &entity\n;
			$data = str_replace(array('&amp;','&lt;','&gt;'), array('&amp;amp;','&amp;lt;','&amp;gt;'), $data);
			$data = preg_replace('/(&#*\w+)[\x00-\x20]+;/u', '$1;', $data);
			$data = preg_replace('/(&#x*[0-9A-F]+);*/iu', '$1;', $data);
			$data = html_entity_decode($data, ENT_COMPAT, 'UTF-8');

			// Remove any attribute starting with "on" or xmlns
			$data = preg_replace('#(<[^>]+?[\x00-\x20"\'])(?:on|xmlns)[^>]*+>#iu', '$1>', $data);

			// Remove javascript: and vbscript: protocols
			$data = preg_replace('#([a-z]*)[\x00-\x20]*=[\x00-\x20]*([`\'"]*)[\x00-\x20]*j[\x00-\x20]*a[\x00-\x20]*v[\x00-\x20]*a[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2nojavascript...', $data);
			$data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*v[\x00-\x20]*b[\x00-\x20]*s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:#iu', '$1=$2novbscript...', $data);
			$data = preg_replace('#([a-z]*)[\x00-\x20]*=([\'"]*)[\x00-\x20]*-moz-binding[\x00-\x20]*:#u', '$1=$2nomozbinding...', $data);

			// Only works in IE: <span style="width: expression(alert('Ping!'));"></span>
			$data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?expression[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
			$data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?behaviour[\x00-\x20]*\([^>]*+>#i', '$1>', $data);
			$data = preg_replace('#(<[^>]+?)style[\x00-\x20]*=[\x00-\x20]*[`\'"]*.*?s[\x00-\x20]*c[\x00-\x20]*r[\x00-\x20]*i[\x00-\x20]*p[\x00-\x20]*t[\x00-\x20]*:*[^>]*+>#iu', '$1>', $data);

			// Remove namespaced elements (we do not need them)
			$data = preg_replace('#</*\w+:\w[^>]*+>#i', '', $data);

			do
			{
			    // Remove really unwanted tags
			    $old_data = $data;
			    $data = preg_replace('#</*(?:applet|b(?:ase|gsound|link)|embed|frame(?:set)?|i(?:frame|layer)|l(?:ayer|ink)|meta|object|s(?:cript|tyle)|title|xml)[^>]*+>#i', '', $data);
			}
			while ($old_data !== $data);

			// we are done...
			$data = filter_var($data, FILTER_SANITIZE_STRING);
		}//!empty
		return $data;
	}//xss_clean

        $actual_link = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
	$errName = $errEmail = $errMessage = $errHuman = $errSPAM = $result = '';

	if (isset($_POST["submit"])) {

echo "<PRE>";print_r($_POST);echo "</PRE>";
echo "<PRE>";print_r($_SESSION);echo "</PRE>";

		$ip = $_POST['ip'];
		$httpref = $_POST['httpref'];
		$httpagent = $_POST['httpagent'];

		$name = strip_tags(htmlspecialchars($_POST['name']));
		$email = strip_tags(htmlspecialchars($_POST['email']));
		$message = xss_clean($_POST['message']); //$_POST['message'];
		$human = $_POST['human'];
		$token = $_POST['token'];//Safeguard your forms against Cross Site Request Forgery (CSRF) attacks.

		$to = 'contact@yourdomain.com';

		$subject = 'Message from Contact Form ';
		
		$body ="From: $name\n E-Mail: $email\n Message:\n $message \n\n
			Additional Info : IP = $ip \n
			Browser Info: $httpagent \n
			Referral : $httpref \n";

		// Always set content-type when sending HTML email
		$headers = "MIME-Version: 1.0" . "\r\n";
		$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n"; 
		$headers .= 'To: Contact Manager <contact@yourdomain.com>' . "\r\n";
		$headers .= "From: noreply@yourdomain.com\r\n"; // This is the email address the generated message will be from. We recommend using something like noreply@yourdomain.com.
		$headers .= "Reply-To: $email";	

		// Check if name has been entered
		if (empty($_POST['name'])) {
			$errName = 'Please enter your name';
		}
		
		// Check if email has been entered and is valid
		if (empty($_POST['email']) || !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
			$errEmail = 'Please enter a valid email address';
		}
		
		//Check if message has been entered
		if (empty($_POST['message'])) {
			$errMessage = 'Please enter your message';
		}
		
		if (empty($human) || $human != $_SESSION['CAPTCHA']) {
			$errHuman = 'Your anti-spam answer is incorrect';
		}
		
		//if ($_SESSION['MY_HTTP_REFERER'] != $actual_link) {
		if (empty($token) || $_SESSION['TOKEN'] != $token) {
			$errSPAM = 'SPAM is not allowed!';
		}

		// If there are no errors, send the email
		if (empty($errName) && empty($errEmail) && empty($errMessage) && empty($errHuman) && empty($errSPAM)) {
			if (mail ($to, $subject, $body, $headers)) {
				$result='<div class="alert alert-success">Thank You! I will be in touch</div>';
			} else {
				$result='<div class="alert alert-danger">Sorry there was an error sending your message. Please try again later.</div>';
			}
		}
	}//if (isset($_POST["submit"]))
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Bootstrap Contact Form With PHP Example including security tricks.">
    <title>Bootstrap Contact Form With PHP Example including security tricks</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/css/bootstrap.min.css">
  </head>
  <body>
  	<div class="container">
  		<div class="row">
  			<div class="col-md-6 col-md-offset-3">
  				<h1 class="page-header text-center">Contact Form Example with CAPTCHA</h1>
				<form class="form-horizontal" role="form" method="post" action="contact.php">

					<?php
					  $ipi = getenv("REMOTE_ADDR");  					  
					  $httprefi = getenv("HTTP_REFERER");
					  $_SESSION['TOKEN'] = microtime();//Token to Safeguard your forms against Cross Site Request Forgery (CSRF) attacks.
					  $httpagenti = getenv("HTTP_USER_AGENT");
					?>

					<input type="hidden" name="ip" value="<?php echo $ipi ?>" />
					<input type="hidden" name="httpref" value="<?php echo $httprefi ?>" />
					<input type="hidden" name="httpagent" value="<?php echo $httpagenti ?>" />
					<input type="hidden" name="token" value="<?php echo $_SESSION['TOKEN'] ?>" />

					<div class="form-group<?php echo (isset($errName) ? (' has-error') : null); ?>">
						<label for="name" class="col-sm-2 control-label">Name</label>
						<div class="col-sm-10">
							<input type="text" class="form-control" id="name" name="name" placeholder="First & Last Name" value="<?php my_print_POST('name'); ?>">
							<?php if (isset($errName)) echo "<p class='text-danger'>$errName</p>";?>
						</div>
					</div>
					<div class="form-group<?php echo (isset($errEmail) ? (' has-error') : null); ?>">
						<label for="email" class="col-sm-2 control-label">Email</label>
						<div class="col-sm-10">
							<input type="email" class="form-control" id="email" name="email" placeholder="example@domain.com" value="<?php my_print_POST('email'); ?>">
							<?php if (isset($errEmail)) echo "<p class='text-danger'>$errEmail</p>";?>
						</div>
					</div>
					<div class="form-group<?php echo (isset($errMessage) ? (' has-error') : null); ?>">
						<label for="message" class="col-sm-2 control-label">Message</label>
						<div class="col-sm-10">
							<textarea class="form-control" rows="4" name="message"><?php my_print_POST('message');?></textarea>
							<?php if (isset($errMessage)) echo "<p class='text-danger'>$errMessage</p>";?>
						</div>
					</div>
					<div class="form-group<?php echo (isset($errHuman) || isset($errSPAM)  ? (' has-error') : null); ?>">
						<label for="human" class="col-sm-2 control-label"> <img id='captcha_img' src="captcha.php" alt="Captcha anti-spam" title="Captcha anti-spam - Click to refresh">  </label>

						<div class="col-sm-10">
							<input type="text" class="form-control" id="human" name="human" placeholder="Your 'anti-spam' Answer"> &nbsp; &nbsp;
							<?php if (isset($errHuman)) echo "<p class='text-danger'>$errHuman</p>";?>
							<?php if (isset($errSPAM)) echo "<p class='text-danger'>$errSPAM</p>";?>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-10 col-sm-offset-2">
							<input id="submit" name="submit" type="submit" value="Send" class="btn btn-primary">
							<button id="refresh" title="refresh Captcha" type="button" class="btn btn-default">
			 	 	 	           <span title="Refresh Captcha" class="glyphicon glyphicon-refresh" aria-hidden="true"></span>
							</button>
						</div>
					</div>
					<div class="form-group">
						<div class="col-sm-10 col-sm-offset-2">
							<?php if (isset($result)) echo $result; ?>	
						</div>
					</div>
				</form> 
			</div>
		</div>
	</div>   
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.0/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.1/js/bootstrap.min.js"></script>
    <script>
		// we have both a image and a refresh image, used for captcha
		$('#refresh,#captcha_img').click(function() {
		       //alert($('#captcha_img').attr('src'));
		       src = $('#captcha_img').attr('src');
		   	// check for existing ? and remove if found
		       queryPos = src.indexOf('?');
		       if(queryPos != -1) {
			  src = src.substring(0, queryPos);
		       }    
		       $('#captcha_img').attr('src', src + '?' + Math.random());
		       return false;
		});
    </script>

  </body>
</html>
