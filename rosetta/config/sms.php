<?php
return [
	"service" => env('SMS_SERVICE','fake'),
	"fake" => [
		"url" => env("FAKE_SMS_URL",null),
	],
	'ssd' => [
		"url" => env('SMS_SSD_URL'),
		"authkey" => env('SMS_SSD_AUTHKEY'),
		"sender" => env('SMS_SSD_SENDER'),
		"route" => env('SMS_SSD_ROUTE',4),
	]
];