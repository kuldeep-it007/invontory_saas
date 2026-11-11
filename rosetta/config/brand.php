<?php
return [
	"brand" => env('BRAND','Rosetta'),
	"brand_short" => env('BRAND_SHORT','ROS'),
	"queue_prefix" => env('QUEUE_PREFIX', 'ros'),
	'theme' => env('BRAND_THEME','skin-blue'),
	'tnc' => env('TNC',false),
	'new_approach' => env('NEW_APPROACH',0),
	"api_user_email" => env('API_USER_EMAIL'),
	"api_user_password" => env('API_USER_PASSWORD'),
];