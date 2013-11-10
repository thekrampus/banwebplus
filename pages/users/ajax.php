<?php

global $o_access_object;
require_once(dirname(__FILE__).'/../../resources/db_query.php');
require_once(dirname(__FILE__).'/../../resources/globals.php');
require_once(dirname(__FILE__).'/user_funcs.php');
require_once(dirname(__FILE__)."/../login/access_object.php");

/**
 * Resets the password for the user, if they applied for the forgot password script and have the correct key.
 * The key is verified against the key in the `access_log`.`reset_key`.
 * @param  string $s_username The username of the user to reset the password for.
 * @param  string $s_key      The key to verify against `access_log`.`reset_key`.
 * @param  string $s_password The password to use for the user.
 * @param  boolean $b_force   If TRUE, does not check the key or time.
 * @return string             An array with either TRUE/FALSE, and one of 'Your password has been set. You can now login with the username [login].', 'The username [username] can't be found.', 'Invalid credentials', 'The reset has timed out. Please resubmit the request to reset your password.'
 */
function reset_password($s_username, $s_key, $s_password, $b_force = FALSE) {
	
	global $o_access_object;
	
	// check that the user exists
	$s_username_exists = user_ajax::username_status($s_username);
	if ($s_username_exists != "taken")
		return array(FALSE, "The username {$s_username} can't be found.");
	
	// get some variables
	$i_now = time();
	$i_reset_expiration = $o_access_object->get_reset_expiration($s_username, FALSE);
	$s_reset_key = $o_access_object->get_reset_key($s_username, FALSE);
	
	// check the key and time
	if ($s_reset_key != $s_key && !$b_force)
		return array(FALSE, "Invalid credentials");
	if ($i_reset_expiration > $i_now && !$b_force)
		return array(FALSE, "The reset has timed out. Please resubmit the request to reset your password.");
	
	// reset the password
	db_query("UPDATE `students` SET `pass`=AES_ENCRYPT('[username]','[password]') WHERE `username`='[username]'", array("username"=>$s_username, "password"=>$s_password), TRUE);
	return array(TRUE, "Your password has been set. You can now login with the username {$s_username}.");
}

class user_ajax {
	/**
	 * Checks that a username doesn't exist, yet
	 * @$s_username string The username to be checking for
	 * @return      string One of "blank", "taken", or "available"
	 */
	public static function username_status($s_username) {
		global $maindb;
		if (strlen($s_username) == 0)
				return 'blank';
		$a_usernames = db_query("SELECT `id` FROM `[maindb]`.`students` WHERE `username`='[username]'",
								array('maindb'=>$maindb, 'username'=>$s_username));
		if (count($a_usernames) > 0)
				return 'taken';
		else
				return 'available';
	}

	public static function check_username() {
		$s_username = get_post_var('username');
		$s_username_status = user_ajax::username_status($s_username);
		switch ($s_username_status) {
		case 'blank':
				return 'print error[*note*]The username is blank';
		case 'taken':
				return 'print error[*note*]That username is already taken.';
		case 'available':
				return 'print success[*note*]That username is available.';
		}
	}

	public static function create_user() {
		$s_username = trim(get_post_var('username'));
		$s_password = trim(get_post_var('password'));
		$s_email = trim(get_post_var('email'));

		if (strlen($s_username) == 0)
				return 'print error[*note*]The username is blank.';
		if (strlen($s_password) == 0)
				return 'print error[*note*]The password is blank.';
		if (strlen($s_email) == 0)
				return 'print error[*note*]The email is blank.';

		if (!user_funcs::create_user($s_username, $s_password, $s_email))
				return 'print error[*note*]Error creating user';

		mail($s_email, 'banwebplus account', 'You just created an account on banwebplus.com with the username "'.$s_username.'."
Log in to your new account from www.banwebplus.com.

If you ever forget your password you can reset it from the main page by clicking on the "forgot password" link (once I have it functioning).', 'From: noreply@banwebplus.com');
		return 'print success[*note*]Success! You can now use the username '.$s_username.' to log in from the main page!';
	}

	/**
	 * Used to send a password reset link to an user.
	 * Only needs one valid username/email.
	 * @param  string $s_username The username of the user to reset the password for
	 *     Uses $_GET['username'] if not set
	 * @param  string $s_email    The email of the user to reset the password for
	 *     Uses $_GET['email'] if not set
	 * @return string             An array with either TRUE/FALSE, and one of 'A verification email has been sent to [email]', 'Please provide a username or email address', 'That username can't be found', 'That email can't be found', 'That username/email combination can't be found', 'Too many attempts have been made to reset the password. Please try again in [minutes] minutes.'
	 */
	public static function forgot_password($s_username = "", $s_email = "") {

		global $maindb;
		global $o_access_object;

		// get the username or email, and the access object
		if ($s_username == "")
				$s_username = trim(get_post_var('username'));
		if ($s_email == "")
				$s_email = trim(get_post_var('email'));

		// determine which of the credentials were provided
		$b_username_provided = $s_username != "";
		$b_email_provided = $s_email != "";
		if (!$b_username_provided && !$b_email_provided)
				return array(FALSE, "Please provide a username or email address");

		// verify that the username and/or email exists
		$a_users = db_query("SELECT `username`,`email` FROM `[maindb]`.`students` WHERE `username`='[username]'", array('maindb'=>$maindb, 'username'=>$s_username));
		$b_user_exists = count($a_users) > 0;
		if ($b_user_exists) {
				$s_email = $a_users[0]['email'];
		} else {
				$a_users = db_query("SELECT `username`,`email` FROM `[maindb]`.`students` WHERE `email`='[email]'", array('maindb'=>$maindb, 'email'=>$s_email));
				$b_user_exists = count($a_users) > 0;
				if ($b_user_exists)
						$s_username = $a_users[0]['username'];
		}

		// check if there have been too many password reset attempts recently
		if ($b_user_exists) {
				$i_seconds_to_next_trial = $o_access_object->check_reset_access($s_username);
		} else {
				$i_seconds_to_next_trial = $o_access_object->check_reset_access("");
		}
		if ($i_seconds_to_next_trial > 0) {
				$i_minutes = (int)($i_seconds_to_next_trial / 60);
				return array(FALSE, "Too many attempts have been made to reset the password. Please try again in {$i_minutes} minutes.");
		}

		// return false if the email/username wasn't found
		if (!$b_user_exists) {
				if (!$b_username_provided)
						return array(FALSE, "That email can't be found");
				if (!$b_email_provided)
						return array(FALSE, "That username can't be found");
				return array(FALSE, "That username/email combination can't be found");
		}

		// send the verification email
		$s_reset_key = $o_access_object->get_reset_key($s_username, TRUE);
		$i_reset_time = $o_access_object->get_reset_expiration($s_username, TRUE);
		$i_reset_minutes = (int)(($i_reset_time - strtotime('now')) / 60);
		mail($s_email, "Request to Reset Banwebplus Password", "A password reset attempt has been made with banwebplus.com for the user {$s_username}, registered with this email address. If you did not request this reset please ignore this email.\n\nYou have {$i_reset_minutes} minutes to click the link below to reset your password. Ignore this email if you do not want your password reset.\nhttp://banwebplus.com/pages/users/reset_password.php?username={$s_username}&key={$s_reset_key}", "From: noreply@banwebplus.com");
		return array(TRUE, "A verification email has been sent to {$s_email}");
	}

	public static function forgot_password_ajax() {
		$s_username = trim($_POST['username']);
		$s_email = trim($_POST['email']);
		$a_retval = self::forgot_password($s_username, $s_email);
		if ($a_retval[0]) {
			return "print success[*note*]".$a_retval[1];
		} else {
			return "print error[*note*]".$a_retval[1];
		}
	}

	public static function reset_password_ajax() {
		$s_username = trim($_POST['username']);
		$s_key = trim($_POST['key']);
		$s_password = trim($_POST['password']);
		$a_retval = reset_password($s_username, $s_key, $s_password);
		if ($a_retval[0]) {
			return "print success[*note*]".$a_retval[1];
		} else {
			return "print error[*note*]".$a_retval[1];
		}
	}
}

if (isset($_POST['draw_create_user_page']))
		echo "load page[*note*]/pages/users/create.php[*post*]draw_create_user_page[*value*]1";
else if (isset($_POST['draw_forgot_password_page']))
		echo "load page[*note*]/pages/users/forgot_password.php[*post*]draw_forgot_password_page[*value*]1";
else if (isset($_POST['username']) && !isset($_POST['command']))
		$_POST['command'] = 'check_username';
if (isset($_POST['command'])) {
		$o_ajax = new user_ajax();
		$s_command = $_POST['command'];
		if (method_exists($o_ajax, $s_command)) {
				echo user_ajax::$s_command();
		} else {
				echo 'bad command';
		}
}

?>