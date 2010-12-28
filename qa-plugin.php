<?php

/*
	Question2Answer 1.3 (c) 2010, SLonoed

	http://www.question2answer.org/

	
	File: qa-plugin/loginza-login/qa-loginza.php
	Version: 1.3
	Date: 2010-11-23 06:34:00 GMT
	Description: Initiates Loginza login plugin



*/

/*
	Plugin Name: Loginza Login
	Plugin URI: 
	Plugin Description: Allows users to log in via Loginza
	Plugin Version: 1.0
	Plugin Date: 2010-10-31
	Plugin Author: SLonoed
*/


	if (!defined('QA_VERSION')) { // don't allow this page to be requested directly from browser
		header('Location: ../../');
		exit;
	}


	qa_register_plugin_module('login', 'qa-loginza-login.php', 'qa_loginza_login', 'loginza');
	

/*
	Omit PHP closing tag to help avoid accidental output
*/