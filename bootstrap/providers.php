<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\PermissionRegistrar;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    PermissionRegistrar::class,
];
