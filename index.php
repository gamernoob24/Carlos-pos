<?php
/**
 * Carlo's Burger POS — front controller
 * ------------------------------------------------------------
 * Every screen is reached through  index.php?page=...
 *
 * auth:  public = anyone | user = any signed-in user | admin = manager only
 * (SRS: cashiers get the checkout screen and their own shift session;
 *  managers additionally get menu, inventory, sales and reports.)
 */

require_once __DIR__ . '/app/bootstrap.php';

$routes = [
    // page          controller     action          auth
    'login'      => ['auth',      'login',      'public'],
    'logout'     => ['auth',      'logout',     'public'],
    'pos'        => ['pos',       'pos',        'user'],
    'kitchen'    => ['kitchen',   'kitchen',    'user'],
    'shifts'     => ['shifts',    'shifts',     'user'],
    'sale'       => ['sales',     'sale_view',  'user'],   // receipt printing
    'settings'   => ['settings',  'settings',   'user'],   // business settings admin-gated inside
    'products'   => ['products',  'products',   'admin'],
    'categories' => ['products',  'categories', 'admin'],
    'inventory'  => ['inventory', 'ingredients','admin'],
    'recipes'    => ['inventory', 'recipes',    'admin'],
    'modifiers'  => ['inventory', 'modifiers',  'admin'],
    'sales'      => ['sales',     'sales',      'admin'],
    'reports'    => ['reports',   'reports',    'admin'],
    'users'      => ['settings',  'users',      'admin'],
];

$page = get('page', 'pos');

if (!isset($routes[$page])) {
    http_response_code(404);
    $page = '404';
    [$controllerFile, $action] = ['errors', 'not_found'];
} else {
    [$controllerFile, $action] = $routes[$page];
}

/* ---- access control ---- */
if ($routes[$page][2] === 'admin') {
    require_admin();
} elseif ($routes[$page][2] === 'user') {
    require_login();
}
csrf_guard();

require_once __DIR__ . '/app/controllers/' . $controllerFile . '.php';

$handler = $action . '_controller';
if (!function_exists($handler)) {
    http_response_code(500);
    exit('Controller handler "' . e($handler) . '" is missing.');
}

$data = $handler();

/* ---------- render ---------- */
$layout   = ($action === 'login') ? 'blank' : 'app';
$viewFile = __DIR__ . '/app/views/' . $action . '.php';

require __DIR__ . '/app/views/layout/header.php';

if (file_exists($viewFile)) {
    require $viewFile;
} else {
    echo '<div class="card"><p>View "' . e($action) . '" is missing.</p></div>';
}

require __DIR__ . '/app/views/layout/footer.php';
