<?php

use Illuminate\Support\Facades\Route;

// Public storefront auth
require __DIR__.'/auth.php';

// Public storefront routes
require __DIR__.'/storefront.php';

// Authenticated buyer routes
require __DIR__.'/account.php';

// Authenticated admin panel
require __DIR__.'/admin.php';

// Authenticated super admin panel
require __DIR__.'/superadmin.php';

require __DIR__.'/seller.php';
