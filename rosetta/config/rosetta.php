<?php
return [
	'blocked_duration' => 3 * 24 * 60 * 60,
	'cool_down_start_time' => 60 * 60,//#820
	'cool_down_end_time' => (4 * 24 * 60 * 60) + (60 * 60),//#820
	'advance_cart_duration' => 7 * 24 * 60 * 60,//might not be required as part of #810 and #873
	'return_duration' => 90,
	'request_source' => 'CONSOLE', //#719,
	'enable_token' => env('ENABLE_TOKEN',false),//#832
	'time_zone' => 'Asia/Kolkata',//#840,
	"mobile_app_url" => env('MOBILE_APP_URL')
];