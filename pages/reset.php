<?php
require_once ('system/csrfmagic/csrf-magic.php');
require_once("models/config.php");
if(isUserLoggedIn()) {
	echo '<meta http-equiv="refresh" content="0; URL=index.php?page=account">';
	die(); 
}
$errors = array();
$success_message = "";
if(!empty($_GET["confirm"]))
{
	$token = trim($_GET["confirm"]);

	if($token == "" || !validateActivationToken($token,TRUE))
	{
		$errors[] = lang("FORGOTPASS_INVALID_TOKEN");
	}
	else
	{
		$rand_pass = getUniqueCode(15);
		$secure_pass = generateHash($rand_pass);

		$userdetails = fetchUserDetails(NULL,$token);

		$mail = new userCakeMail();
		$hooks = array(
				"searchStrs" => array("#GENERATED-PASS#","#USERNAME#"),
				"subjectStrs" => array($rand_pass,$userdetails["Username"])
		);

		if(!$mail->newTemplateMsg("your-lost-password.txt",$hooks))
		{
			$errors[] = lang("MAIL_TEMPLATE_BUILD_ERROR");
		}
		else
		{	
			if(!$mail->sendMail($userdetails["Email"],"Your new password"))
			{
					$errors[] = lang("MAIL_ERROR");
			}
			else
			{
					if(!updatePasswordFromToken($secure_pass,$token))
					{
						$errors[] = lang("SQL_ERROR");
					}
					else
					{	
						//Might be wise if this had a time delay to prevent a flood of requests.
						flagLostPasswordRequest($userdetails["Username_Clean"],0);

						$success_message  = lang("FORGOTPASS_NEW_PASS_EMAIL");
					}
			}
		}

	}
}

//----------------------------------------------------------------------------------------------

//User has denied this request
//----------------------------------------------------------------------------------------------
if(!empty($_GET["deny"]))
{
	$token = trim($_GET["deny"]);

	if($token == "" || !validateActivationToken($token,TRUE))
	{
		$errors[] = lang("FORGOTPASS_INVALID_TOKEN");
	}
	else
	{

		$userdetails = fetchUserDetails(NULL,$token);

		flagLostPasswordRequest($userdetails['Username_Clean'],0);

		$success_message = lang("FORGOTPASS_REQUEST_CANNED");
	}
}

if(!empty($_POST))
{
	if($_SESSION["Reset_Attempts"] > 4)
	{
		$uagent = mysql_real_escape_string(getuseragent()); //get user agent
		$ip = mysql_real_escape_string(getIP()); //get user ip
		$account = mysql_real_escape_string("Guest/Not Logged In");
		$date = mysql_real_escape_string(gettime());
		$sql = @mysql_query("INSERT INTO access_violations (username, ip, user_agent, time) VALUES ('$account', '$ip', '$uagent', '$date');");
		$errors[] = "Access Denied!";
	}
	$captcha = md5($_POST["captcha"]);

	if ($captcha != $_SESSION['captcha'])
	{
		$errors[] = lang("CAPTCHA_FAIL");
	}
	$email = $_POST["email"];
	$username = $_POST["username"];



	if(trim($email) == "")
	{
		$errors[] = lang("ACCOUNT_SPECIFY_EMAIL");
	}
	//Check to ensure email is in the correct format / in the db
	else if(!isValidEmail($email) || !emailExists($email))
	{
		$errors[] = lang("ACCOUNT_INVALID_EMAIL");
	}

	if(trim($username) == "")
	{
		$errors[] = lang("ACCOUNT_SPECIFY_USERNAME");
	}
	else if(!usernameExists($username))
	{
		$errors[] = lang("ACCOUNT_INVALID_USERNAME");
	}


	if(count($errors) == 0)
	{

		//Check that the username / email are associated to the same account
		if(!emailUsernameLinked($email,$username))
		{
			$errors[] =  lang("ACCOUNT_USER_OR_EMAIL_INVALID");
		}else{
			$userdetails = fetchUserDetails($username);
			if($userdetails["LostPasswordRequest"] == 1)
			{
				$errors[] = lang("FORGOTPASS_REQUEST_EXISTS");
			}else{
				$mail = new userCakeMail();
				$confirm_url = lang("CONFIRM")."\nhttps://www.milancoin.com/index.php?page=reset&confirm=".$userdetails["ActivationToken"];
				$deny_url = ("DENY")."\nhttps://www.milancoin.com/index.php?page=reset&deny=".$userdetails["ActivationToken"];
				$hooks = array(
					"searchStrs" => array("#CONFIRM-URL#","#DENY-URL#","#USERNAME#"),
					"subjectStrs" => array($confirm_url,$deny_url,$userdetails["Username"])
				);
				if(!$mail->newTemplateMsg("lost-password-request.txt",$hooks))
				{
					$errors[] = lang("MAIL_TEMPLATE_BUILD_ERROR");
				}else{
					if(!$mail->sendMail($userdetails["Email"],"Lost password request"))
					{
						$errors[] = lang("MAIL_ERROR");
					}else{
						flagLostPasswordRequest($username,1);
						$success_message = lang("FORGOTPASS_REQUEST_SUCCESS");
					}
				}
			}
		}
	}
}	
//----------------------------------------------------------------------------------------------	
?>
<h1>Forgot Password</h1>
        
		<?php
        if(!empty($_POST) || !empty($_GET))
        {
            if(count($errors) > 0)
            {
			if(!isset($_SESSION["Reset_Attempts"]))
				{ $_SESSION["Reset_Attempts"] = 1; }else{ $_SESSION["Reset_Attempts"]++; }
				echo '<ul class="nobullets">';
				foreach($errors as $key => $value) { echo '<li>'.$value.'</li>'; }
				echo '</ul>';
            }else{
				echo '<div id="success"><p>'.$success_message.'</p></div>';
			}
        }
        ?>  
<link rel="stylesheet" type="text/css" href="assets/css/register.css" />		
<form method="POST" action="index.php?page=reset" autocomplete="off" onsubmit="document.getElementById('#resetbutton').disabled = 1;">
	<table>
		<tr>
			<td>
				<input name="email" type="text" placeholder="Enter Your Email Address" class="field">
			</td>
		</tr>
		<tr>
			<td>
				<input name="username" type="text" placeholder="Enter Your Username" class="field">
			</td>
		</tr>
		<tr>
			<td>
				<center><img src="pages/docs/captcha.php" class="captcha"></center>
			</td>
		</tr>
		<tr>
			<td>
				<input name="captcha" type="text" placeholder="Enter Security Code" class="field">
			</td>
		</tr>
		<tr>
			<td>
				<input type="submit" id="reset" class="blues"/>
			</td>
		</tr>
		</table>
</form>


