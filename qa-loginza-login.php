<?php
///////////////////////////////////////////////////////////////////////////////
//
// Question2answer login plugin. Use Loginza service
// Loginza it's OpenID provider that support most popular in
// Commonwealth of Independent States social services
//
// Develop by Dmitry Manannikov aka SLonoed
//
// This code is totaly free. But it would be nice if you not delete
// this comments. Thanks!
//
// Question2Answer - http://question2answer.org/
// Loginza - http://loginza.ru/
// Repository of this - https://github.com/SLonoed/Q2A_loginza_plugin
// My blog - http://blog.slonoed.ru
// My twitter - http://twitter.com/SLonoed
// email - slonoed@gmail.com
//
// How to use:
// Create directory "loginza-login" in "/qa-plugin/" and put in
// files "qa-plugin.php" and "qa-loginza-login.php"
// Than change settings bellow
// Enjoy!
//
///////////////////////////////////////////////////////////////////////////////

	class qa_loginza_login
	{	
		// Loginza settings.
		// More info - https://loginza.ru/api-overview (Russian)
	
		// Providers. You can use:
		// google, yandex, mailruapi, mailru, vkontakte, facebook, twitter, loginza, 
		// myopenid, webmoney, rambler, flickr, lastfm, verisign, aol, steam, openid
		var $LOGINZA_PROVIDERS = "vkontakte,facebook,twitter,google";
		
		// Use iframe. If false - use JS widget.
		// Warning!!! If you use iframe, change theme file, because iframe have 300px height.
		// How to create your own theme look http://www.question2answer.org/advanced.php
		// topic "Creating an advanced theme for Question2Answer"
		// Easy way: ovveride in qa-theme.php
		//	function nav_list($navigation, $navtype)
		//	{
		//		$this->output('<UL CLASS="qa-nav-'.$navtype.'-list">');
		//		foreach ($navigation as $key => $navlink)
		//		{
		//			if (!strcmp($key, "Loginza"))
		//				continue;
		//			$this->nav_item($key, $navlink, $navtype);
		//		}
		//		$this->output('</UL>');
		//	}
		var $LOGINZA_IS_IFRAME = true;
		
		// Language. Can use: 
		// ru - Russian
		// en - English
		// uk - Ukrainian
		var $LOGINZA_LANG = "ru";		
		
		// Change to your site login page
		var $LOGINZA_RETURN_URL = "http://quastion.slonoed.ru/"; 
		
		// CSS style to remember button
		// TODO good button style
		var $LOGINZA_REMEMBER_BTN_STYLE = '#lgzbtn {padding:15px;background:#ddd;}\n#lgzbtn .on {background:green;}';

		// Cookies expire time (hours)
		var $LOGINZA_COOKIES_EXPIRE_TIME = 48;
		
		// end Loginza settings

		var $directory;
		var $urltoroot;
		var $translate;

		function load_module($directory, $urltoroot)
		{	
			$this->directory=$directory;
			$this->urltoroot=$urltoroot;
			
			switch ($this->LOGINZA_LANG)
			{
			case "ru":
				$this->translate["remember_me"] = "Запомнить";
				$this->translate["remember"] = "Запомнен";
				break;
			case "uk":
				$this->translate["remember_me"] = "Запомнить";
				$this->translate["remember"] = "Запомнить";
				break;
			default:
				$this->translate["remember_me"] = "Remember Me";
				$this->translate["remember"] = "Remember";
				break;
			}			
		}

		function check_login()
		{
			require_once QA_INCLUDE_DIR.'qa-db-users.php';
			require_once QA_INCLUDE_DIR.'qa-db-selects.php';
			require_once QA_INCLUDE_DIR.'qa-db.php';

			$gologin = false; // login?
			$userdata = null; 
			$identity = '';
			$setcookie = false;
			
			// if cookies is set
			if (isset($_COOKIE["qa_loginza_id"]) && isset($_COOKIE["qa_loginza_scr"]))
			{
				$uid = $_COOKIE['qa_loginza_id'];
				$cook = $_COOKIE['qa_loginza_scr'];
				
				//TODO userIp checking
				//	$userip = isset($_SERVER['HTTP_X_REAL_IP']) ? $_SERVER['HTTP_X_REAL_IP'] : $_SERVER['REMOTE_ADDR'];

				$useraccount = qa_db_select_with_pending(qa_db_user_account_selectspec($uid, true));
				$secret = $useraccount['passcheck'];
				$lastip = $useraccount['loginip'];
				if (!strcmp($secret, $cook) /*&& !strcmp($lastip, $userip)*/)
				{
					// Get identity from db
					$sub = qa_db_read_all_values(qa_db_query_sub('SELECT identifier FROM ^userlogins WHERE userid=$',$uid));
					$identity = $sub[0];
					$gologin = true;
					$setcookie = true;
				}							
			}

			// if login throwout Loginza
			if (isset($_REQUEST["token"]))
			{			
				$rawuser = qa_retrieve_url('http://loginza.ru/api/authinfo?token='.$_POST['token']);
				if (strlen($rawuser)) 
				{			
					include_once 'JSON.php';
					$json=new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
					$user=$json->decode($rawuser);
					
					if (is_array($user))
					{
						$gologin = true;
						$userdata = $user;
						$identity = $userdata['identity'];

						// If user set remember option
						if (isset($_REQUEST["remember"]))
							$setcookie = true;
					}
				}
			}
			
			if ($gologin)
			{
				//TODO add userdata convert to userfields
				$userfields = null;
				qa_log_in_external_user('loginza', $identity, $userfields);
			
				// This code, if user sucses loged in

				$secret = '';
				$uid = qa_get_logged_in_userid();

				// When external user login, Q2A not set passcheck for him. Do  it
				if (!qa_get_logged_in_user_field('passsalt') || !qa_get_logged_in_user_field('passcheck'))
				{
					$randompassword = rand_str(15);
					qa_db_user_set_password($uid, $randompassword);
				}

				$useraccount = qa_db_select_with_pending(qa_db_user_account_selectspec($uid, true));
				$secret = $useraccount['passcheck'];

				if ($setcookie)
				{
					// 2 days cookie
					$expire = time() + $this->LOGINZA_COOKIES_EXPIRE_TIME * 60 * 60;
					$expire2 = time() + $this->LOGINZA_COOKIES_EXPIRE_TIME * 60 * 60;
					setcookie('qa_loginza_id',  $uid, $expire);
					setcookie('qa_loginza_scr', $secret, $expire2);
				}
			}
		}
		
		
		function match_source($source)
		{
			return $source=='loginza';
		}		
				
		function login_html($tourl, $context)
		{
		
			?>
				<script type="text/javascript">
				function ChangeRememberStatus()
				{
					var btn = document.getElementById('lgzbtn');
					var frame = document.getElementById('lgzframe');
					if (btn.className == 'on')
					{
						btn.innerHTML = "<?echo $this->translate["remember_me"]?>";
						btn.className = '';
						frame.src = "http://loginza.ru/api/widget?overlay=loginza&token_url=<?echo urlencode($this->LOGINZA_RETURN_URL);?>&providers_set=<?echo $this->LOGINZA_PROVIDERS;?>&lang=<?echo $this->LOGINZA_LANG;?>";
					}
					else
					{
						btn.innerHTML = "<?echo $this->translate["remember"]?>";
						btn.className = 'on';
						frame.src = "http://loginza.ru/api/widget?overlay=loginza&token_url=<?echo urlencode($this->LOGINZA_RETURN_URL . "?remember=true");?>&providers_set=<?echo $this->LOGINZA_PROVIDERS;?>&lang=<?echo $this->LOGINZA_LANG;?>";
					}
				}
				</script>
			<?			
			if ($this->LOGINZA_IS_IFRAME)
			{
			?>
				<script src="http://loginza.ru/js/widget.js" type="text/javascript"></script>
				<iframe src="http://loginza.ru/api/widget?overlay=loginza&token_url=<?echo urlencode($this->LOGINZA_RETURN_URL);?>&providers_set=<?echo $this->LOGINZA_PROVIDERS;?>&lang=<?echo $this->LOGINZA_LANG;?>" style="width:359px;height:300px;" scrolling="no" frameborder="no" id="lgzframe"></iframe>
				<style type="text/css">
				<?echo $this->LOGINZA_REMEMBER_BTN_STYLE?>
				</style>
				<a href="#" onclick="ChangeRememberStatus()" id="lgzbtn"><?echo $this->translate["remember_me"]?></a>
			<?
			}
			else
			{
			?>
				<script src="https://s3-eu-west-1.amazonaws.com/s1.loginza.ru/js/widget.js" type="text/javascript"></script>
				<a href="http://loginza.ru/api/widget?token_url=<?echo urlencode($this->LOGINZA_RETURN_URL);?>&providers_set=<?echo$this->LOGINZA_PROVIDERS;?>&lang=<?echo $this->LOGINZA_LANG;?>" class="loginza"><img src="http://loginza.ru/img/sign_in_button_gray.gif" alt="Войти через loginza"/></a>
			<?
			}
		} 
		
		function logout_html($tourl)
		{
			// Delete cookies when logout and redirect to ./logout
			?>
			<script type="text/javascript">
				function DelLoginzaCookies() {
					//alert("is Work");
					document.cookie = 'qa_loginza_id=; expires=Thu, 01-Jan-70 00:00:01 GMT;';
					document.cookie = 'qa_loginza_scr=; expires=Thu, 01-Jan-70 00:00:01 GMT;';
					document.location = '/logout';
				}
			</script>

			<a href="#" onclick="DelLoginzaCookies()">Exit</a>
			<?
		}

		// Generate a random character string
		function rand_str($length = 32, $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890')
		{
			// Length of character list
			$chars_length = (strlen($chars) - 1);

			// Start our string
			$string = $chars{rand(0, $chars_length)};

			// Generate random string
			for ($i = 1; $i < $length; $i = strlen($string))
			{
				// Grab a random character from our list
				$r = $chars{rand(0, $chars_length)};

				// Make sure the same two characters don't appear next to each other
				if ($r != $string{$i - 1}) $string .=  $r;
			}

			// Return the string
			return $string;
		}
	};
