<?php

/*
|--------------------------------------------------------------------------
| Plesk deployment compatibility
|--------------------------------------------------------------------------
|
| Some Plesk Git deployments place the repository root directly in the
| configured document root. Forward those requests to Laravel's public
| front controller while keeping the regular public/ document root valid.
|
*/

require __DIR__.'/public/index.php';
