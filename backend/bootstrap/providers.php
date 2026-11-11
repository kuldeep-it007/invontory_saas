<?php

use App\Providers\AiServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\TenancyPipelineServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    AiServiceProvider::class,
    TenancyServiceProvider::class,
    // Registered AFTER TenancyServiceProvider so its middleware-priority
    // adjustment (HTTPS before tenancy identification) wins. See the provider.
    TenancyPipelineServiceProvider::class,
];
