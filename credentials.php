<?php
define('LOCALSERVER',          'http://localhost/') ;

// Add Bandwidth credentials here
define('BANDWIDTH_API_TOKEN',	't-tttttttttttttttttttttttttt');
define('BANDWIDTH_API_SECRET', 	'xxxxxxxxxxxxxxxxxxxxxxxxxxxx');
define('BANDWIDTH_USER_ID', 	'u-uuuuuuuuuuuuuuuuuuuuuuuuuu');
define('BANDWIDTH_API_URL',     "https://api.catapult.inetwork.com/v1/users/" . BANDWIDTH_USER_ID . "/");
//Optional URL if you want callbacks from Bandwidth
define('BANDWIDTH_API_CALLBACK', LOCALSERVER . "mycallbacks.php");

// Add mailgun credentials here
define('MAILGUN_KEY', 			'key-kkkkkkkkkkkkkkkkkkkkkkkk');
define('DOMAIN',				'sms.mydomain.com');
?>
