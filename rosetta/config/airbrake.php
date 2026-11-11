<?php

return [
	'enabled' => env('ENABLE_AIRBRAKE',false),
    'projectId'     => '1',
    'projectKey'    => env('AIRBRAKE_PROJECT_KEY',false),
    'environment'   => env('APP_ENV', 'production'),

    //leave the following options empty to use defaults

    'appVersion'    => '',
    'host'          => env('AIRBRAKE_HOST',false),
    'rootDirectory' => '',
    'httpClient'    => '',
];
